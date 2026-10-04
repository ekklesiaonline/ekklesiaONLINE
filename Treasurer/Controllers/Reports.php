<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;
use App\Services\TreasurerReportsDataService;

class Reports extends BaseController
{
    protected TreasurerReportsDataService $reportData;

    public function __construct()
    {
        $this->reportData = new TreasurerReportsDataService();
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $isProvinceAdmin = $this->isProvinceAdmin();
        $provinceScopeId = $isProvinceAdmin ? $this->resolveAdminProvinceId() : 0;

        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        if ($isProvinceAdmin && $provinceScopeId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.reportProvinceScopeFailed'));
        }

        $scope = $isProvinceAdmin ? 'diocese' : 'parish';
        $selectedParishId = $isProvinceAdmin ? 0 : (int) ($this->getOperationalParishId() ?? 0);

        if (!$isProvinceAdmin && $selectedParishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $summary = $this->reportData->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $dioceseBreakdown = $isProvinceAdmin
            ? $this->reportData->buildDioceseBreakdown($provinceScopeId, $startDate, $endDate)
            : [];
        $fundBreakdown = [];
        $accountBreakdown = [];
        if (!$isProvinceAdmin) {
            $fundBreakdown = $this->reportData->buildParishFundBreakdown($selectedParishId, $startDate, $endDate);
            $accountBreakdown = $this->reportData->buildParishAccountCategoryBreakdown($selectedParishId, $startDate, $endDate);
        }

        $data = array_merge($this->getCommonViewData(lang('App.navReports')), [
            'report_start_date' => $startDate,
            'report_end_date' => $endDate,
            'report_summary' => $summary,
            'report_diocese_breakdown' => $dioceseBreakdown,
            'report_fund_breakdown' => $fundBreakdown,
            'report_account_breakdown' => $accountBreakdown,
            'report_is_provinceadmin' => $isProvinceAdmin,
        ]);

        return view('Treasurer\Views\Reports/index', $data);
    }

    public function summaryApi()
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorForbidden'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $isProvinceAdmin = $this->isProvinceAdmin();
        $provinceScopeId = $isProvinceAdmin ? $this->resolveAdminProvinceId() : 0;
        $startDate = (string) ($this->request->getGet('start_date') ?? date('Y-01-01'));
        $endDate = (string) ($this->request->getGet('end_date') ?? date('Y-m-d'));

        if (!$this->reportData->isValidDate($startDate)) {
            $startDate = date('Y-01-01');
        }
        if (!$this->reportData->isValidDate($endDate)) {
            $endDate = date('Y-m-d');
        }

        if ($isProvinceAdmin && $provinceScopeId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.reportProvinceScopeFailed'), 422, 'REPORT_PROVINCE_SCOPE_FAILED'), 422);
        }

        $scope = $isProvinceAdmin ? 'diocese' : 'parish';
        $selectedParishId = $isProvinceAdmin ? 0 : (int) ($this->getOperationalParishId() ?? 0);
        if (!$isProvinceAdmin && $selectedParishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 422, 'PARISH_SCOPE_REQUIRED'), 422);
        }

        $summary = $this->reportData->buildSummary($scope, $selectedParishId, $startDate, $endDate, $provinceScopeId);
        $fundBreakdown = [];
        $accountBreakdown = [];
        if (!$isProvinceAdmin) {
            $fundBreakdown = $this->reportData->buildParishFundBreakdown($selectedParishId, $startDate, $endDate);
            $accountBreakdown = $this->reportData->buildParishAccountCategoryBreakdown($selectedParishId, $startDate, $endDate);
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'scope' => $scope,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'summary' => $summary,
                'fund_breakdown' => $fundBreakdown,
                'account_breakdown' => $accountBreakdown,
            ],
        ]);
    }
}
