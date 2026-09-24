<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use CodeIgniter\Database\BaseConnection;

/**
 * CanonicalTestSchema
 *
 * The PHPUnit suite shares a single SQLite :memory: connection across test
 * classes. Individual tests historically created tables with whichever
 * columns they happened to need, so the first test to create a table decided
 * its shape for every later test (CREATE TABLE IF NOT EXISTS + shared
 * connection = order-dependent schema pollution).
 *
 * This helper defines the canonical union schema for the shared domain
 * tables — every column the production code and tests may reference —
 * and applies it idempotently:
 *
 *   - Missing table  -> CREATE TABLE with the full column set.
 *   - Existing table -> ALTER TABLE ADD COLUMN for any missing column.
 *
 * It never drops or narrows columns, so it is safe to call from any test
 * setUp() and from the PHPUnit bootstrap regardless of execution order.
 *
 * It only runs on SQLite (the tests connection); other drivers are skipped.
 */
final class CanonicalTestSchema
{
    /**
     * Union schema for shared tables. Column types use SQLite affinities;
     * "id" columns are emitted as INTEGER PRIMARY KEY AUTOINCREMENT.
     *
     * @var array<string, array<string, string>>
     */
    private const SCHEMA = [
        'province' => [
            'province_number' => 'TEXT',
            'name'            => 'TEXT',
            'sort_order'      => 'INTEGER',
            'created_at'      => 'TEXT',
            'updated_at'      => 'TEXT',
        ],
        'diocese' => [
            'province_id' => 'INTEGER',
            'name'        => 'TEXT',
            'alias_norm'  => 'TEXT',
            'created_at'  => 'TEXT',
            'updated_at'  => 'TEXT',
        ],
        'parish' => [
            'name'             => 'TEXT',
            'parish_ueid'      => 'TEXT',
            'is_active'        => 'INTEGER DEFAULT 1',
            'logo'             => 'TEXT',
            'banner'           => 'TEXT',
            'diocese_id'       => 'INTEGER',
            'street'           => 'TEXT',
            'mailing_street'   => 'TEXT',
            'city'             => 'TEXT',
            'mailing_city'     => 'TEXT',
            'state'            => 'TEXT',
            'mailing_state'    => 'TEXT',
            'zip'              => 'TEXT',
            'mailing_zip'      => 'TEXT',
            'county'           => 'TEXT',
            'mailing_county'   => 'TEXT',
            'country'          => 'TEXT',
            'mailing_country'  => 'TEXT',
            'tax_id'           => 'TEXT',
            'address'          => 'TEXT',
            'phone'            => 'TEXT',
            'email'            => 'TEXT',
            'website'          => 'TEXT',
            'public_token'     => 'TEXT',
            'created_at'       => 'TEXT',
            'updated_at'       => 'TEXT',
            'deleted_at'       => 'TEXT',
        ],
        'users' => [
            'username'       => 'TEXT',
            'email'          => 'TEXT',
            'first_name'     => 'TEXT',
            'last_name'      => 'TEXT',
            'status'         => 'TEXT',
            'status_message' => 'TEXT',
            'active'         => 'INTEGER DEFAULT 1',
            'last_active'    => 'TEXT',
            'created_at'     => 'TEXT',
            'updated_at'     => 'TEXT',
            'deleted_at'     => 'TEXT',
        ],
        'auth_identities' => [
            'user_id'      => 'INTEGER',
            'type'         => 'TEXT',
            'name'         => 'TEXT',
            'secret'       => 'TEXT',
            'secret2'      => 'TEXT',
            'expires'      => 'TEXT',
            'extra'        => 'TEXT',
            'force_reset'  => 'INTEGER DEFAULT 0',
            'last_used_at' => 'TEXT',
            'created_at'   => 'TEXT',
            'updated_at'   => 'TEXT',
        ],
        'auth_groups_users' => [
            'user_id'    => 'INTEGER',
            '"group"'    => 'TEXT',
            'created_at' => 'TEXT',
        ],
        'auth_permissions_users' => [
            'user_id'    => 'INTEGER',
            'permission' => 'TEXT',
            'created_at' => 'TEXT',
        ],
        'user_person' => [
            'user_id'    => 'INTEGER',
            'person_id'  => 'INTEGER',
            'is_primary' => 'INTEGER DEFAULT 0',
            'linked_at'  => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
        ],
        'person' => [
            'first_name'               => 'TEXT',
            'last_name'                => 'TEXT',
            'email'                    => 'TEXT',
            'email_hash'               => 'TEXT',
            'phone'                    => 'TEXT',
            'phone_hash'               => 'TEXT',
            'birthdate'                => 'TEXT',
            'gender'                   => 'TEXT',
            'address'                  => 'TEXT',
            'preferred_contact_method' => 'TEXT',
            'preferred_timezone'       => 'TEXT',
            'opted_out_channels'       => 'TEXT',
            'source'                   => 'TEXT',
            'interests'                => 'TEXT',
            'is_active'                => 'INTEGER DEFAULT 1',
            'deleted_at'               => 'TEXT',
            'created_at'               => 'TEXT',
            'updated_at'               => 'TEXT',
        ],
        'profile' => [
            'user_id'            => 'INTEGER',
            'person_id'          => 'INTEGER',
            'profile_picture'    => 'TEXT',
            'parish_id'          => 'INTEGER',
            'province_id'        => 'INTEGER',
            'diocese_id'         => 'INTEGER',
            'first_name'         => 'TEXT',
            'middle_name'        => 'TEXT',
            'nickname'           => 'TEXT',
            'last_name'          => 'TEXT',
            'phone'              => 'TEXT',
            'phone_country_code' => 'TEXT',
            'phone_hash'         => 'TEXT',
            'address'            => 'TEXT',
            'address_line_2'     => 'TEXT',
            'city'               => 'TEXT',
            'state'              => 'TEXT',
            'zip'                => 'TEXT',
            'country'            => 'TEXT',
            'locale'             => 'TEXT',
            'social_media'       => 'TEXT',
            'birth_date'         => 'TEXT',
            'baptism_date'       => 'TEXT',
            'confirmation_date'  => 'TEXT',
            'is_confirmed'       => 'INTEGER DEFAULT 0',
            'spiritual_gifts'    => 'TEXT',
            'portal_access'      => 'TEXT',
            'portal_enabled_at'  => 'TEXT',
            'is_dependent'       => 'INTEGER DEFAULT 0',
            'created_at'         => 'TEXT',
            'updated_at'         => 'TEXT',
            'deleted_at'         => 'TEXT',
        ],
        'bank_account' => [
            'parish_id'             => 'INTEGER',
            'chart_account_id'      => 'INTEGER',
            'name'                  => 'TEXT',
            'institution_name'      => 'TEXT',
            'masked_account_number' => 'TEXT',
            'routing_last4'         => 'TEXT',
            'currency_code'         => "TEXT DEFAULT 'USD'",
            'opening_balance'       => 'REAL DEFAULT 0',
            'opened_on'             => 'TEXT',
            'is_active'             => 'INTEGER DEFAULT 1',
            'created_at'            => 'TEXT',
            'updated_at'            => 'TEXT',
        ],
        'bank_transaction' => [
            'bank_account_id'  => 'INTEGER',
            'transaction_date' => 'TEXT',
            'amount'           => 'REAL DEFAULT 0',
            'description'      => 'TEXT',
            'reference'        => 'TEXT',
            'status'           => "TEXT DEFAULT 'unmatched'",
            'created_at'       => 'TEXT',
            'updated_at'       => 'TEXT',
        ],
        'bank_reconciliation' => [
            'bank_account_id'   => 'INTEGER',
            'statement_date'    => 'TEXT',
            'statement_balance' => 'REAL DEFAULT 0',
            'status'            => "TEXT DEFAULT 'open'",
            'closed_by'         => 'INTEGER',
            'closed_at'         => 'TEXT',
            'created_at'        => 'TEXT',
            'updated_at'        => 'TEXT',
        ],
        'bank_reconciliation_item' => [
            'bank_reconciliation_id' => 'INTEGER',
            'bank_transaction_id'    => 'INTEGER',
            'is_cleared'             => 'INTEGER DEFAULT 0',
            'created_at'             => 'TEXT',
        ],
        'bank_transaction_journal_link' => [
            'bank_transaction_id' => 'INTEGER',
            'journal_entry_id'    => 'INTEGER',
            'created_at'          => 'TEXT',
        ],
        'small_group' => [
            'parish_id'    => 'INTEGER',
            'name'         => 'TEXT',
            'description'  => 'TEXT',
            'meeting_day'  => 'TEXT',
            'meeting_time' => 'TEXT',
            'location'     => 'TEXT',
            'capacity'     => 'INTEGER',
            'is_active'    => 'INTEGER DEFAULT 1',
            'created_by'   => 'INTEGER',
            'created_at'   => 'TEXT',
            'updated_at'   => 'TEXT',
            'deleted_at'   => 'TEXT',
        ],
        'group_membership' => [
            'small_group_id' => 'INTEGER',
            'person_id'      => 'INTEGER',
            'role'           => "TEXT DEFAULT 'member'",
            'status'         => "TEXT DEFAULT 'active'",
            'joined_on'      => 'TEXT',
            'left_on'        => 'TEXT',
            'created_at'     => 'TEXT',
            'updated_at'     => 'TEXT',
        ],
        'team' => [
            'parish_id'   => 'INTEGER',
            'name'        => 'TEXT',
            'description' => 'TEXT',
            'leader_id'   => 'INTEGER',
            'created_at'  => 'TEXT',
            'updated_at'  => 'TEXT',
        ],
        'team_member' => [
            'team_id'    => 'INTEGER',
            'user_id'    => 'INTEGER',
            'person_id'  => 'INTEGER',
            'role'       => 'TEXT',
            'joined_at'  => 'TEXT',
            'created_at' => 'TEXT',
        ],
        'parish_affiliation' => [
            'parish_id'         => 'INTEGER',
            'person_id'         => 'INTEGER',
            'linked_user_id'    => 'INTEGER',
            'membership_status' => 'TEXT',
            'pathway_stage'     => 'INTEGER',
            // Mirrors the production generated column: is_member is the
            // on-register flag = membership_status IN ('member','inactive').
            // VIRTUAL (not STORED) so repairColumns can ADD COLUMN it.
            'is_member'         => "INTEGER GENERATED ALWAYS AS (CASE WHEN \"membership_status\" IN ('member','inactive') THEN 1 ELSE 0 END) VIRTUAL",
            'is_primary'             => 'INTEGER DEFAULT 0',
            'followed_up'            => 'INTEGER DEFAULT 0',
            'converted_to_member_at' => 'TEXT',
            'converted_by_user_id'   => 'INTEGER',
            'source'                 => 'TEXT',
            'start_date'             => 'TEXT',
            'end_date'               => 'TEXT',
            'created_at'             => 'TEXT',
            'updated_at'             => 'TEXT',
            'deleted_at'             => 'TEXT',
        ],
        'followup_log' => [
            'person_id'     => 'INTEGER',
            'parish_id'     => 'INTEGER',
            'action_id'     => 'INTEGER',
            'assigned_to'   => 'INTEGER',
            'executed_at'   => 'TEXT',
            'completed_at'  => 'TEXT',

            'response_metadata' => 'TEXT',
            'action_type'   => 'TEXT',
            'channel'       => 'TEXT',
            'status'        => 'TEXT',
            'scheduled_date' => 'TEXT',
            'sent_at'       => 'TEXT',
            'sequence_id'   => 'INTEGER',
            'week_number'   => 'INTEGER',
            'notes'         => 'TEXT',
            'created_by'    => 'INTEGER',
            'created_at'    => 'TEXT',
            'updated_at'    => 'TEXT',
        ],
        'followup_sequence' => [
            'parish_id'   => 'INTEGER',
            'name'        => 'TEXT',
            'description' => 'TEXT',
            'is_active'   => 'INTEGER DEFAULT 1',
            'created_at'  => 'TEXT',
            'updated_at'  => 'TEXT',
        ],
        'followup_action' => [
            'sequence_id'  => 'INTEGER',
            'week_number'  => 'INTEGER',
            'day_offset'   => 'INTEGER',
            'channel'      => 'TEXT',
            'template_id'  => 'INTEGER',
            'assigned_to'  => 'INTEGER',
            'instructions' => 'TEXT',
            'is_automated' => 'INTEGER DEFAULT 0',
            'created_at'   => 'TEXT',
            'updated_at'   => 'TEXT',
        ],
        'followup_template' => [
            'parish_id' => 'INTEGER',
            'channel'   => 'TEXT',
            'name'      => 'TEXT',
            'subject'   => 'TEXT',
            'body_text' => 'TEXT',
            'body_html' => 'TEXT',
            'variables' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
        ],
        'call_task' => [
            'parish_id'      => 'INTEGER',
            'person_id'      => 'INTEGER',
            'log_id'         => 'INTEGER',
            'assigned_to'    => 'INTEGER',
            'task_type'      => "TEXT DEFAULT 'call'",
            'title'          => 'TEXT',
            'phone'          => 'TEXT',
            'notes'          => 'TEXT',
            'status'         => "TEXT DEFAULT 'pending'",
            'scheduled_date' => 'TEXT',
            'completed_at'   => 'TEXT',
            'created_at'     => 'TEXT',
            'updated_at'     => 'TEXT',
        ],
        'person_membership_details' => [
            'person_id'              => 'INTEGER',
            'parish_id'              => 'INTEGER',
            'is_baptized'            => 'INTEGER DEFAULT 0',
            'is_confirmed'           => 'INTEGER DEFAULT 0',
            'is_communicant'         => 'INTEGER DEFAULT 0',
            'is_dependent'           => 'INTEGER DEFAULT 0',
            'has_pledge'             => 'INTEGER DEFAULT 0',
            'pledge_status'          => 'TEXT',
            'good_standing_override' => 'INTEGER DEFAULT 0',
            'created_at'             => 'TEXT',
            'updated_at'             => 'TEXT',
        ],
        'person_note' => [
            'person_id'  => 'INTEGER',
            'parish_id'  => 'INTEGER',
            'user_id'    => 'INTEGER',
            'note'       => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
            'deleted_at' => 'TEXT',
        ],
        'person_onboarding' => [
            'person_id'         => 'INTEGER',
            'parish_id'         => 'INTEGER',
            'onboarding_status' => 'TEXT',
            'started_at'        => 'TEXT',
            'completed_at'      => 'TEXT',
            'created_at'        => 'TEXT',
            'updated_at'        => 'TEXT',
        ],
        'person_status_history' => [
            'person_id'   => 'INTEGER',
            'parish_id'   => 'INTEGER',
            'status_type' => 'TEXT',
            'from_status' => 'TEXT',
            'to_status'   => 'TEXT',
            'changed_by'  => 'INTEGER',
            'reason'      => 'TEXT',
            'created_at'  => 'TEXT',
        ],
        'person_visit' => [
            'person_id'      => 'INTEGER',
            'parish_id'      => 'INTEGER',
            'visit_date'     => 'TEXT',
            'visit_type'     => 'TEXT',
            'service_id'     => 'INTEGER',
            'attended_event' => 'TEXT',
            'gift_given'     => 'INTEGER DEFAULT 0',
            'notes'          => 'TEXT',
            'created_at'     => 'TEXT',
            'updated_at'     => 'TEXT',
        ],
        'leadership_position' => [
            'user_id'              => 'INTEGER',
            'person_id'            => 'INTEGER',
            'scope_type'           => 'TEXT',
            'parish_id'            => 'INTEGER',
            'diocese_id'           => 'INTEGER',
            'province_id'          => 'INTEGER',
            'title'                => 'TEXT',
            'role_code'            => 'TEXT',
            'active'               => 'INTEGER DEFAULT 1',
            'started_at'           => 'TEXT',
            'ended_at'             => 'TEXT',
            'notes'                => 'TEXT',
            'appointed_by_user_id' => 'INTEGER',
            'employment_type'      => 'TEXT',
            'role_category'        => 'TEXT',
            'clergy_order'         => 'TEXT',
            'created_at'           => 'TEXT',
            'updated_at'           => 'TEXT',
        ],
        'sacramental_record' => [
            'parish_id'           => 'INTEGER',
            'person_id'           => 'INTEGER',
            'sacrament_type'      => 'TEXT',
            'sacrament_date'      => 'TEXT',
            'register_ref'        => 'TEXT',
            'certificate_number'  => 'TEXT',
            'officiant_person_id' => 'INTEGER',
            'location_notes'      => 'TEXT',
            'notes'               => 'TEXT',
            'created_at'          => 'TEXT',
            'updated_at'          => 'TEXT',
        ],
        'member_standing_summary' => [
            'user_id'            => 'INTEGER',
            'year'               => 'INTEGER',
            'is_active_member'   => 'INTEGER DEFAULT 0',
            'attendance_count'   => 'INTEGER DEFAULT 0',
            'communion_count'    => 'INTEGER DEFAULT 0',
            'has_pledged'        => 'INTEGER DEFAULT 0',
            'is_under_discipline' => 'INTEGER DEFAULT 0',
            'is_good_standing'   => 'INTEGER DEFAULT 0',
            'is_integrated'      => 'INTEGER DEFAULT 0',
            'last_updated'       => 'TEXT',
        ],
        'member_discipline' => [
            'user_id'    => 'INTEGER',
            'reason'     => 'TEXT',
            'start_date' => 'TEXT',
            'end_date'   => 'TEXT',
            'is_active'  => 'INTEGER DEFAULT 0',
            'notes'      => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
        ],
        'message_campaign' => [
            'parish_id'         => 'INTEGER',
            'channel'           => 'TEXT',
            'subject'           => 'TEXT',
            'body'              => 'TEXT',
            'audience'          => 'TEXT',
            'status'            => "TEXT DEFAULT 'draft'",
            'total_recipients'  => 'INTEGER DEFAULT 0',
            'sent_count'        => 'INTEGER DEFAULT 0',
            'skipped_count'     => 'INTEGER DEFAULT 0',
            'failed_count'      => 'INTEGER DEFAULT 0',
            'created_by'        => 'INTEGER',
            'sent_at'           => 'TEXT',
            'created_at'        => 'TEXT',
            'updated_at'        => 'TEXT',
        ],
        'message_recipient' => [
            'campaign_id' => 'INTEGER',
            'person_id'   => 'INTEGER',
            'user_id'     => 'INTEGER',
            'name'        => 'TEXT',
            'first_name'  => 'TEXT',
            'last_name'   => 'TEXT',
            'address'     => 'TEXT',
            'status'      => "TEXT DEFAULT 'pending'",
            'skip_reason' => 'TEXT',
            'queue_id'    => 'INTEGER',
            'error'       => 'TEXT',
            'sent_at'     => 'TEXT',
            'created_at'  => 'TEXT',
            'updated_at'  => 'TEXT',
        ],
        'message_template' => [
            'parish_id'  => 'INTEGER',
            'name'       => 'TEXT',
            'channel'    => "TEXT DEFAULT 'any'",
            'subject'    => 'TEXT',
            'body'       => 'TEXT',
            'is_active'  => 'INTEGER DEFAULT 1',
            'created_by' => 'INTEGER',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
            'deleted_at' => 'TEXT',
        ],
        'notification' => [
            'scope_type'   => 'TEXT',
            'scope_id'     => 'INTEGER',
            'category'     => 'TEXT',
            'severity'     => 'TEXT',
            'title'        => 'TEXT',
            'body'         => 'TEXT',
            'action_url'   => 'TEXT',
            'action_label' => 'TEXT',
            'status'       => 'TEXT',
            'publish_at'   => 'TEXT',
            'expires_at'   => 'TEXT',
            'created_by'   => 'INTEGER',
            'created_at'   => 'TEXT',
            'updated_at'   => 'TEXT',
        ],
        'notification_recipient' => [
            'notification_id' => 'INTEGER',
            'user_id'         => 'INTEGER',
            'delivery_status' => 'TEXT',
            'delivered_at'    => 'TEXT',
            'read_at'         => 'TEXT',
            'dismissed_at'    => 'TEXT',
            'failure_reason'  => 'TEXT',
            'created_at'      => 'TEXT',
            'updated_at'      => 'TEXT',
        ],
        'sms_queue' => [
            'to_number'     => 'TEXT',
            'body'          => 'TEXT',
            'from_number'   => 'TEXT',
            'parish_id'     => 'INTEGER',
            'status'        => "TEXT DEFAULT 'pending'",
            'attempts'      => 'INTEGER DEFAULT 0',
            'max_attempts'  => 'INTEGER DEFAULT 3',
            'message_id'    => 'TEXT',
            'error_message' => 'TEXT',
            'scheduled_at'  => 'TEXT',
            'sent_at'       => 'TEXT',
            'created_at'    => 'TEXT',
            'updated_at'    => 'TEXT',
        ],
        'service' => [
            'parish_id'      => 'INTEGER',
            'service_date'   => 'TEXT',
            'service_time'   => 'TEXT',
            'liturgical_day' => 'TEXT',
            'service'        => 'TEXT',
            'place'          => 'TEXT',
            'preacher'       => 'TEXT',
            'celebrant'      => 'TEXT',
            'notes'          => 'TEXT',
            'created_at'     => 'TEXT',
            'updated_at'     => 'TEXT',
        ],
        'service_metric' => [
            'service_id'                => 'INTEGER',
            'format_of_service'         => 'TEXT',
            'attendance_classification' => 'TEXT',
            'service_family'            => 'TEXT',
            'service_schedule'          => 'TEXT',
            'reported_present_count'    => 'INTEGER DEFAULT 0',
            'reported_communion_count'  => 'INTEGER DEFAULT 0',
            'online_live_views'         => 'INTEGER DEFAULT 0',
            'online_recorded_views_7d'  => 'INTEGER DEFAULT 0',
            'online_peak_viewers'       => 'INTEGER DEFAULT 0',
            'languages_json'            => 'TEXT',
            'baptism_adult'             => 'INTEGER DEFAULT 0',
            'baptism_child'             => 'INTEGER DEFAULT 0',
            'confirmation_adult'        => 'INTEGER DEFAULT 0',
            'confirmation_teen'         => 'INTEGER DEFAULT 0',
            'reception_count'           => 'INTEGER DEFAULT 0',
            'marriage_count'            => 'INTEGER DEFAULT 0',
            'burial_count'              => 'INTEGER DEFAULT 0',
            'eucharist_count'           => 'INTEGER DEFAULT 0',
            'daily_office_count'        => 'INTEGER DEFAULT 0',
            'online_live_viewers'       => 'INTEGER DEFAULT 0',
            'online_recorded_views'     => 'INTEGER DEFAULT 0',
            'place_labels_json'         => 'TEXT',
            'created_at'                => 'TEXT',
            'updated_at'                => 'TEXT',
        ],
        'service_people' => [
            'service_id'  => 'INTEGER',
            'person_name' => 'TEXT',
            'role_name'   => 'TEXT',
            'sort_order'  => 'INTEGER DEFAULT 0',
            'created_at'  => 'TEXT',
            'updated_at'  => 'TEXT',
        ],
        'attendance' => [
            'service_id'  => 'INTEGER',
            'parish_id'   => 'INTEGER',
            'user_id'     => 'INTEGER',
            'person_id'   => 'INTEGER',
            'attended'    => 'INTEGER DEFAULT 0',
            'communicant' => 'INTEGER DEFAULT 0',
            'created_at'  => 'TEXT',
        ],
        'annual_pledge' => [
            'user_id'            => 'INTEGER',
            'person_id'          => 'INTEGER',
            'parish_id'          => 'INTEGER',
            'pledge_year'        => 'INTEGER',
            'pledged_amount'     => 'REAL',
            'fulfilled_amount'   => 'REAL',
            'pledged_at'         => 'TEXT',
            'fulfillment_status' => 'TEXT',
            'status_id'          => 'INTEGER',
            'notes'              => 'TEXT',
            'created_at'         => 'TEXT',
            'updated_at'         => 'TEXT',
        ],
        'event_calendar' => [
            'parish_id'   => 'INTEGER',
            'province_id' => 'INTEGER',
            'diocese_id'  => 'INTEGER',
            'scope_level' => "TEXT DEFAULT 'parish'",
            'name'        => 'TEXT',
            'color_hex'   => 'TEXT',
            'is_active'   => 'INTEGER DEFAULT 1',
            'sort_order'  => 'INTEGER DEFAULT 0',
            'created_by'  => 'INTEGER',
            'created_at'  => 'TEXT',
            'updated_at'  => 'TEXT',
        ],
        'event' => [
            'parish_id'             => 'INTEGER',
            'calendar_id'           => 'INTEGER',
            'scope_level'           => "TEXT DEFAULT 'parish'",
            'scope_diocese_id'      => 'INTEGER',
            'scope_province_id'     => 'INTEGER',
            'organizer_type'        => 'TEXT',
            'organizer_parish_id'   => 'INTEGER',
            'organizer_province_id' => 'INTEGER',
            'organizer_diocese_id'  => 'INTEGER',
            'host_parish_id'        => 'INTEGER',
            'visibility'            => 'TEXT',
            'audience_team_id'      => 'INTEGER',
            'audience_family_id'    => 'INTEGER',
            'schedule_mode'         => 'TEXT',
            'is_all_day'            => 'INTEGER DEFAULT 0',
            'title'                 => 'TEXT',
            'description'           => 'TEXT',
            'cover_path'            => 'TEXT',
            'event_type'            => 'TEXT',
            'location'              => 'TEXT',
            'start_at'              => 'TEXT',
            'end_at'                => 'TEXT',
            'capacity'              => 'INTEGER',
            'requires_registration' => 'INTEGER DEFAULT 0',
            'status'                => "TEXT DEFAULT 'draft'",
            'publish_at'            => 'TEXT',
            'recurrence_pattern'    => 'TEXT',
            'recurrence_days_json'  => 'TEXT',
            'recurrence_until'      => 'TEXT',
            'created_by'            => 'INTEGER',
            'created_at'            => 'TEXT',
            'updated_at'            => 'TEXT',
            'deleted_at'            => 'TEXT',
        ],
        'settings' => [
            'class'      => 'TEXT',
            '"key"'      => 'TEXT',
            'value'      => 'TEXT',
            'type'       => 'TEXT',
            'context'    => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
        ],
        'user_registration_request' => [
            'user_id'      => 'INTEGER',
            'parish_id'    => 'INTEGER',
            'status'       => 'TEXT',
            'reviewed_by'  => 'INTEGER',
            'reviewed_at'  => 'TEXT',
            'decision'     => 'TEXT',
            'notes'        => 'TEXT',
            'created_at'   => 'TEXT',
            'updated_at'   => 'TEXT',
        ],
        'user_session' => [
            'user_id'    => 'INTEGER',
            'session_id' => 'TEXT',
            'session_token' => 'TEXT',
            'ip_address' => 'TEXT',
            'user_agent' => 'TEXT',
            'device_fingerprint' => 'TEXT',
            'is_new_device' => 'INTEGER',
            'last_active' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
        ],
        'user_trusted_device' => [
            'user_id'         => 'INTEGER',
            'selector'        => 'TEXT',
            'token_hash'      => 'TEXT',
            'display_name'    => 'TEXT',
            'last_ip_address' => 'TEXT',
            'first_seen_at'   => 'TEXT',
            'last_seen_at'    => 'TEXT',
            'expires_at'      => 'TEXT',
            'revoked_at'      => 'TEXT',
            'revoked_by'      => 'INTEGER',
            'created_at'      => 'TEXT',
            'updated_at'      => 'TEXT',
        ],
    ];

    /**
     * Ensure every shared table exists with the full union column set.
     * Creates missing tables and adds missing columns; never drops anything.
     */
    public static function ensure(BaseConnection $db): void
    {
        if ($db->DBDriver !== 'SQLite3') {
            return;
        }

        $prefix = $db->getPrefix();

        foreach (self::SCHEMA as $table => $columns) {
            $physical = $prefix . $table;

            if (! self::tableExists($db, $physical)) {
                self::createTable($db, $physical, $columns);
                continue;
            }

            self::repairColumns($db, $physical, $columns);

            if ($table === 'parish_affiliation') {
                self::ensureGeneratedIsMember($db);
            }
        }
    }

    /**
     * Convert a plain is_member column on parish_affiliation into the
     * generated register flag. Tests that recreate the table through Forge
     * get a normal column; this restores the production semantics.
     */
    public static function ensureGeneratedIsMember(BaseConnection $db, string $table = 'parish_affiliation'): void
    {
        $physical = $db->getPrefix() . $table;
        if ($db->DBDriver !== 'SQLite3' || ! self::tableExists($db, $physical)) {
            return;
        }

        $definition = 'INTEGER GENERATED ALWAYS AS (CASE WHEN "membership_status" '
            . "IN ('member','inactive') THEN 1 ELSE 0 END) VIRTUAL";

        foreach ($db->query('PRAGMA table_xinfo("' . $physical . '")')->getResultArray() as $row) {
            if (($row['name'] ?? '') === 'is_member') {
                if ((int) ($row['hidden'] ?? 0) !== 0) {
                    return; // already generated
                }
                $db->query('ALTER TABLE "' . $physical . '" DROP COLUMN "is_member"');
                break;
            }
        }

        $db->query('ALTER TABLE "' . $physical . '" ADD COLUMN "is_member" ' . $definition);
        $db->resetDataCache();
    }

    /**
     * Drop-and-recreate a single shared table with the canonical shape.
     * For tests that need a guaranteed-empty table with the full schema.
     */
    public static function reset(BaseConnection $db, string $table): void
    {
        if ($db->DBDriver !== 'SQLite3' || ! isset(self::SCHEMA[$table])) {
            return;
        }

        $physical = $db->getPrefix() . $table;
        $db->query('DROP TABLE IF EXISTS "' . $physical . '"');
        self::createTable($db, $physical, self::SCHEMA[$table]);
    }

    private static function createTable(BaseConnection $db, string $physical, array $columns): void
    {
        $defs = ['"id" INTEGER PRIMARY KEY AUTOINCREMENT'];
        foreach ($columns as $name => $type) {
            $colName = str_starts_with($name, '"') ? $name : '"' . $name . '"';
            $defs[]  = $colName . ' ' . $type;
        }

        $db->query('CREATE TABLE "' . $physical . '" (' . implode(', ', $defs) . ')');
        $db->resetDataCache();
    }

    private static function repairColumns(BaseConnection $db, string $physical, array $columns): void
    {
        $existing = self::columnNames($db, $physical);
        if ($existing === []) {
            return;
        }

        $changed = false;
        foreach ($columns as $name => $type) {
            $plain = trim($name, '"');
            if (in_array($plain, $existing, true)) {
                continue;
            }

            $colName = str_starts_with($name, '"') ? $name : '"' . $name . '"';
            $db->query('ALTER TABLE "' . $physical . '" ADD COLUMN ' . $colName . ' ' . $type);
            $changed = true;
        }

        if ($changed) {
            $db->resetDataCache();
        }
    }

    private static function tableExists(BaseConnection $db, string $physical): bool
    {
        return $db->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$physical]
        )->getNumRows() > 0;
    }

    /**
     * @return list<string>
     */
    private static function columnNames(BaseConnection $db, string $physical): array
    {
        // table_xinfo (not table_info) so generated columns are included —
        // otherwise repairColumns would try to re-add them every call.
        $rows = $db->query('PRAGMA table_xinfo("' . $physical . '")')->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
