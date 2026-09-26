<?php

namespace App\Models;

use CodeIgniter\Model;

class FollowupTemplateModel extends Model
{
    protected $table            = 'followup_template';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'diocese_id',
        'source_template_id',
        'channel',
        'name',
        'subject',
        'body_text',
        'body_html',
        'variables',
        'is_customized',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'channel'   => 'required|in_list[text,email,call,mail,gift]',
        'name'      => 'required|min_length[3]|max_length[150]',
        'body_text' => 'required',
        'subject'   => 'permit_empty|max_length[255]',
        'parish_id'          => 'permit_empty|is_not_unique[parish.id]',
        'diocese_id'         => 'permit_empty|is_not_unique[diocese.id]',
        'source_template_id' => 'permit_empty|is_not_unique[followup_template.id]',
        'is_customized'      => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Return templates available to a parish for a given channel.
     * Includes parish rows plus upstream rows: global and the parish's
     * diocese.
     */
    public function getForParishAndChannel(int $parishId, string $channel): array
    {
        return $this->availableToParishQuery($parishId)
            ->where('channel', $channel)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Check whether the template's channel matches the supplied channel.
     */
    public function channelMatches(int $templateId, string $channel): bool
    {
        $row = $this->select('channel')->where('id', $templateId)->first();

        return is_array($row) && strtolower((string) $row['channel']) === strtolower($channel);
    }

    /**
     * Return all templates available to a parish (all channels), including
     * upstream diocesan/global rows.
     */
    public function getAllForParish(int $parishId): array
    {
        return $this->availableToParishQuery($parishId)
            ->orderBy('channel', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Whether the template is usable for actions in a sequence with the
     * given scope. A template is usable when it is global, or shares the
     * sequence's scope (same parish/diocese).
     */
    public function usableAtScope(array $template, array $sequence): bool
    {
        $scope = \App\Models\FollowupSequenceModel::scopeOf($sequence);

        return match ($scope) {
            'global'   => $template['parish_id'] === null
                && $template['diocese_id'] === null,
            'diocese'  => $template['parish_id'] === null
                && ($template['diocese_id'] === null
                    || (int) $template['diocese_id'] === (int) $sequence['diocese_id']),
            default    => (int) ($template['parish_id'] ?? 0) === (int) $sequence['parish_id']
                || ($template['parish_id'] === null && $template['diocese_id'] === null),
        };
    }

    /**
     * Scope classifier for a template row.
     */
    public static function scopeOf(array $template): string
    {
        if (!empty($template['parish_id'])) {
            return 'parish';
        }
        if (!empty($template['diocese_id'])) {
            return 'diocese';
        }

        return 'global';
    }

    /**
     * Base query: parish rows + upstream rows (global, parish's diocese).
     */
    private function availableToParishQuery(int $parishId): self
    {
        $dioceseId = model('FollowupSequenceModel')->parishDioceseId($parishId);

        return $this->groupStart()
            ->where('parish_id', $parishId)
            ->orGroupStart()
                ->where('parish_id IS NULL', null, false)
                ->where('diocese_id IS NULL', null, false)
            ->groupEnd()
            ->when($dioceseId > 0, static fn($q) => $q->orWhere('diocese_id', $dioceseId))
            ->groupEnd();
    }
}
