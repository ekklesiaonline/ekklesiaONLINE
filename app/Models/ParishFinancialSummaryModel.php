<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ParishFinancialSummaryModel
 *
 * Report-year storage for Parochial Report persistence and historical snapshots.
 * One row per (parish_id, report_year).
 */
class ParishFinancialSummaryModel extends Model
{
    protected $table            = 'parish_report_storage';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'report_year',
        'mailing_address_1',
        'mailing_address_2',
        'mailing_city',
        'mailing_state',
        'mailing_zip',
        'mailing_county',
        'mailing_country',
        'report_prepared_by_name',
        'report_prepared_by_email',
        'report_prepared_by_phone',
        'financial_prepared_by_name',
        'financial_prepared_by_email',
        'financial_prepared_by_phone',
        'clerk_certified_by_name',
        'clerk_certified_by_email',
        'clerk_certified_by_phone',
        'clerk_certified_date',
        'treasurer_certified_by_name',
        'treasurer_certified_by_email',
        'treasurer_certified_by_phone',
        'treasurer_certified_date',
        'rector_certified_by_name',
        'rector_certified_by_email',
        'rector_certified_by_phone',
        'rector_certified_date',
        'warden_approved_by_name',
        'warden_approved_by_email',
        'warden_approved_by_phone',
        'warden_approved_date',
        'completion_participants_count',
        'completion_participant_roles',
        'outreach_beneficiaries_weekly',
        'nonmember_volunteers_weekly',
        'nonlive_views_estimated',
        'membership_previous_total',
        'membership_increase_total',
        'membership_decrease_total',
        'active_participants_total',
        'primary_worship_leader',
        'clergy_situation_json',
        'programs_json',
        'programs_other',
        'subsidiary_organizations_json',
        'subsidiary_organizations_other',
        'languages_other',
        'simultaneous_multilingual_service',
        'percent_white',
        'average_age_override',
        'reporting_currency',
        'confirmed_pledges_count',
        'confirmed_pledges_amount',
        'other_revenue_amount',
        'amount_drawn_operating',
        'amount_drawn_capital',
        'cash_balance',
        'endowment_restricted',
        'endowment_unrestricted',
        'investment_restricted',
        'investment_unrestricted',
        'pct_draw',
        'amount_drawn',
        'investment_net_gain_loss',
        'has_debt',
        'debt_amount',
        'debt_description',
        'owns_property',
        'rents_to_outside',
        'rental_income',
        'rental_expense',
        'carbon_footprint_notes',
        'exemplary_program_text',
        'future_hope_text',
        'report_status',
        'frozen_snapshot_json',
        'finalized_at',
        'finalized_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'parish_id'   => 'required|is_not_unique[parish.id]',
        'report_year' => 'required|integer|greater_than[1900]|less_than[2200]',
        'has_debt'         => 'permit_empty|in_list[0,1]',
        'owns_property'    => 'permit_empty|in_list[0,1]',
        'rents_to_outside' => 'permit_empty|in_list[0,1]',
        'nonlive_views_estimated' => 'permit_empty|in_list[0,1]',
        'simultaneous_multilingual_service' => 'permit_empty|in_list[0,1]',
        'completion_participants_count' => 'permit_empty|integer',
        'outreach_beneficiaries_weekly' => 'permit_empty|integer',
        'nonmember_volunteers_weekly' => 'permit_empty|integer',
        'membership_previous_total' => 'permit_empty|integer',
        'membership_increase_total' => 'permit_empty|integer',
        'membership_decrease_total' => 'permit_empty|integer',
        'active_participants_total' => 'permit_empty|integer',
        'pct_draw'         => 'permit_empty|decimal',
        'percent_white'    => 'permit_empty|decimal',
        'average_age_override' => 'permit_empty|decimal',
        'cash_balance'                => 'permit_empty|decimal',
        'confirmed_pledges_amount'    => 'permit_empty|decimal',
        'other_revenue_amount'        => 'permit_empty|decimal',
        'endowment_restricted'        => 'permit_empty|decimal',
        'endowment_unrestricted'      => 'permit_empty|decimal',
        'investment_restricted'       => 'permit_empty|decimal',
        'investment_unrestricted'     => 'permit_empty|decimal',
        'amount_drawn'                => 'permit_empty|decimal',
        'amount_drawn_operating'      => 'permit_empty|decimal',
        'amount_drawn_capital'        => 'permit_empty|decimal',
        'investment_net_gain_loss'    => 'permit_empty|decimal',
        'debt_amount'                 => 'permit_empty|decimal',
        'rental_income'               => 'permit_empty|decimal',
        'rental_expense'              => 'permit_empty|decimal',
        'report_status'               => 'permit_empty|in_list[draft,finalized]',
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Return the summary row for a specific parish and year, or null if not entered yet.
     */
    public function getForParishYear(int $parishId, int $year): ?array
    {
        return $this->where('parish_id', $parishId)
            ->where('report_year', $year)
            ->first();
    }

    /**
     * Upsert (insert or update) the annual summary row.
     *
     * @param array $data Must include parish_id and report_year.
     * @return bool
     */
    public function upsertForYear(array $data): bool
    {
        $existing = $this->getForParishYear((int) $data['parish_id'], (int) $data['report_year']);

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return (bool) $this->insert($data);
    }
}
