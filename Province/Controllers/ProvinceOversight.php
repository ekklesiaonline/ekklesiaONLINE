<?php

namespace Province\Controllers;

use App\Controllers\BaseController;

class ProvinceOversight extends BaseController
{
    public function directory()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $rows = db_connect()->table('diocese d')
            ->select('d.id, d.name, COUNT(DISTINCT c.id) AS parish_count, COUNT(DISTINCT CASE WHEN agu.group = "dioceseadmin" AND u.active = 1 THEN u.id END) AS active_admin_count')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->join('profile p', 'p.diocese_id = d.id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id AND agu.group = "dioceseadmin"', 'left')
            ->join('users u', 'u.id = p.user_id', 'left')
            ->where('d.province_id', $provinceId)->groupBy('d.id, d.name')->orderBy('d.name')->get()->getResultArray();

        return view('Province\Views\oversight\directory', array_merge($this->getCommonViewData(lang('App.navProvinceDirectory')), ['rows' => $rows]));
    }

    public function health()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }
        $days = min(365, max(1, (int) service('systemSettings')->get('province_dashboard.reporting_overdue_days', 30)));
        $threshold = date('Y-m-d', strtotime('-' . $days . ' days'));
        $rows = db_connect()->table('diocese d')
            ->select('d.id, d.name, MAX(s.service_date) AS last_service_date, COUNT(DISTINCT CASE WHEN agu.group = "dioceseadmin" AND u.active = 1 THEN u.id END) AS active_admin_count')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->join('service s', 's.parish_id = c.id', 'left')
            ->join('profile p', 'p.diocese_id = d.id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id AND agu.group = "dioceseadmin"', 'left')
            ->join('users u', 'u.id = p.user_id', 'left')
            ->where('d.province_id', $provinceId)->groupBy('d.id, d.name')->orderBy('d.name')->get()->getResultArray();
        $rows = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['active_admin_count'] === 0 || empty($row['last_service_date']) || $row['last_service_date'] < $threshold));

        return view('Province\Views\oversight\health', array_merge($this->getCommonViewData(lang('App.navProvinceHealth')), compact('rows', 'days')));
    }

    protected function provinceId(): ?int
    {
        $user = auth()->user();
        if (!$user || !$user->inGroup('provinceadmin') || !$user->can('province.admin')) {
            return null;
        }
        $profile = model('ProfileModel')->where('user_id', (int) auth()->id())->first();
        $provinceId = (int) ($profile['province_id'] ?? 0);
        return $provinceId > 0 && db_connect()->table('province')->where('id', $provinceId)->countAllResults() > 0 ? $provinceId : null;
    }
}
