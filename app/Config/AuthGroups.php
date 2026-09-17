<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'member';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('provinceadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public const SYSTEM_ADMIN = 'systemadmin';
    public const PROVINCE_ADMIN = 'provinceadmin';
    public const DIOCESE_ADMIN = 'dioceseadmin';
    public const PARISH_ADMIN = 'parishadmin';
    public const CLERGY = 'clergy';
    public const TREASURER = 'treasurer';
    public const VOLUNTEER_COORDINATOR = 'volunteer_coordinator';
    public const VESTRY_MEMBER = 'vestry_member';
    public const WARDEN = 'warden';
    public const CLERK = 'clerk';
    public const SECRETARY = 'secretary';
    public const MEMBER = 'member';

    public array $groups = [
        'systemadmin' => [
            'title'       => 'System Administrator',
            'description' => 'Global system administrator with CRUD over provinces, dioceses, and scoped admin users.',
        ],
        'provinceadmin' => [
            'title'       => 'Province Administrator',
            'description' => 'Views diocesan attendance/membership summaries within its assigned province, with no parish CRUD, finance access, or admin account management.',
        ],
        'dioceseadmin' => [
            'title'       => 'Diocesan Administrator',
            'description' => 'Full diocesan admin with parish, users, finance, and settings capabilities.',
        ],
        'parishadmin' => [
            'title'       => 'Parish Administrator',
            'description' => 'Full access to manage their own parish data.',
        ],
        'clergy' => [
            'title'       => 'Clergy',
            'description' => 'View members, mark attendance, view financial summaries.',
        ],
        'treasurer' => [
            'title'       => 'Treasurer',
            'description' => 'Manage parish finances (donations, expenses, reports).',
        ],
        'volunteer_coordinator' => [
            'title'       => 'Volunteer Coordinator',
            'description' => 'Manage volunteer teams and schedules.',
        ],
        'vestry_member' => [
            'title'       => 'Vestry Member',
            'description' => 'View vestry governance records, meetings, and motions.',
        ],
        'warden' => [
            'title'       => 'Warden',
            'description' => 'Vestry governance with report approval authority.',
        ],
        'clerk' => [
            'title'       => 'Clerk',
            'description' => 'Vestry governance with report certification authority.',
        ],
        'secretary' => [
            'title'       => 'Secretary',
            'description' => 'Manage vestry governance records, view and export reports.',
        ],
        'followup_volunteer' => [
            'title'       => 'Follow-up Volunteer',
            'description' => 'Read-only access to follow-up workflow and call queue.',
        ],
        'member' => [
            'title'       => 'Member',
            'description' => 'General parish member with basic access.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        // Admin Levels
        'system.admin'              => 'System admin access to global governance operations',
        'admin.system'              => 'Legacy alias for system.admin (kept for backward compatibility)',
        'province.admin'            => 'Province admin access (view diocesan attendance/membership summaries only; no parish CRUD, no finance)',
        'province.diocese_admin.manage' => 'Manage diocesan administrator accounts within the assigned province',
        'diocese.admin'             => 'Diocese admin access to all diocesan operations',
        'parish.edit'               => 'Edit parish contact information',
        'parishes.edit'             => 'Edit parish profiles (diocesan admin scope)',
        'parishes.delete'           => 'Delete parishes (diocesan admin scope)',
        'parish.manage'             => 'Manage parishes (create, delete)',
        'parish.manage.official'    => 'Edit official parish identification (diocese, tax ID, address)',
        'parishes.manage.official'  => 'Edit official parish identification (diocese, tax ID, address) — plural form used by controllers',
        'portal.manage'             => 'Manage member portal features and family management',

        // Members Management
        'member.view'          => 'View member list',
        'member.create'        => 'Add new members',
        'member.edit'          => 'Edit member details',
        'member.delete'        => 'Delete members',
        'member.export'        => 'Export member data',
        'profile.edit'         => 'Edit member profile',

        // Family Management
        'family.view'          => 'View families',
        'family.manage'        => 'Create and manage families',

        // Small Groups
        'groups.view'          => 'View small groups',
        'groups.manage'        => 'Create and manage small groups',
        'groups.assign'        => 'Assign members to small groups',

        // Bulk Messaging
        'messaging.view'       => 'View bulk message campaigns',
        'messaging.send'       => 'Compose and send bulk messages; manage templates',

        // Service & Attendance
        'service.view'         => 'View services',
        'service.create'       => 'Create services',
        'service.edit'         => 'Edit services',
        'service.delete'       => 'Delete services',
        'attendance.view'       => 'View attendance records',
        'attendance.mark'       => 'Mark attendance',
        'attendance.reports'    => 'View attendance reports',

        // Guest Follow-up
        'guest.view'           => 'View guests',
        'guest.create'         => 'Add new guests',
        'guest.edit'           => 'Edit guest information',
        'guest.delete'         => 'Delete guests',
        'guest.manage'         => 'Create and manage guests',
        'guest.followup'       => 'Manage guest follow-ups',
        'followup.view'        => 'View follow-up workflows',
        'followup.manage'      => 'Manage follow-up workflows',

        // Finance
        'finance.view'          => 'View financial reports',
        'finance.manage'        => 'Full financial management',
        'donation.view'        => 'View donations',
        'donation.create'      => 'Record donations',
        'donation.edit'        => 'Edit donations',
        'donation.delete'      => 'Delete donations',
        'expense.view'         => 'View expenses',
        'expense.create'       => 'Record expenses',
        'expense.edit'         => 'Edit expenses',
        'expense.delete'       => 'Delete expenses',
        'expense.approve'      => 'Approve expenses',

        // Volunteers
        'volunteer.view'       => 'View volunteer teams',
        'volunteer.manage'     => 'Manage volunteer teams',
        'volunteer.assign'     => 'Assign volunteers to teams',
        'volunteer.schedule'   => 'Manage volunteer schedules',

        // Events
        'event.view'           => 'View parish events',
        'event.create'         => 'Create events',
        'event.edit'           => 'Edit events',
        'event.delete'         => 'Delete events',
        'event.register'       => 'Register attendees for events',
        'event.checkin'        => 'Check in and update event registrations',
        'event.manage.registration' => 'Manage event registrations',

        // Province & National Events
        'province.event.create'     => 'Create province-scoped events',
        'province.event.edit'       => 'Edit province-scoped events',
        'province.event.delete'     => 'Delete province-scoped events',
        'province.calendar.manage'  => 'Manage province-level event calendars',
        'national.event.manage'     => 'Create, edit, and delete national Episcopal events',
        'national.calendar.manage'  => 'Manage the national Episcopal event calendar',

        // Worship Planning
        'worship.view'          => 'View worship planning module',
        'worship.manage'        => 'Manage worship plans and song library',
        'worship.song'          => 'Manage worship songs',
        'worship.plan'          => 'Plan worship services',
        'worship.report'        => 'View worship reports',

        // Reporting & Dashboard
        'report.view'          => 'View reports',
        'report.export'        => 'Export reports',
        'report.parochial'     => 'Access parochial reports',
        'dashboard.view'        => 'View dashboard',

        // Member pathway and leadership
        'annual_pledge.manage'      => 'Manage annual pledges',
        'leadership_position.manage' => 'Manage leadership positions',
        'standing.refresh'          => 'Refresh member standing',

        // Diocesan operations
        'diocese.coa'          => 'Manage diocesan chart of accounts',
        'diocese.calendar'     => 'Manage diocesan liturgical calendar',
        'diocese.dashboard'    => 'View diocesan dashboard',

        // Governance workflow
        'governance.vestry.view'            => 'View vestry governance records',
        'governance.vestry.manage'          => 'Manage vestry governance records',
        'governance.report.certify_clerk'   => 'Certify reports as vestry clerk',
        'governance.report.approve_warden'  => 'Approve reports as warden/vestry',
        'governance.report.finalize_clergy' => 'Finalize reports as clergy authority',
        'governance.diocese.approve'        => 'Diocesan canonical approval authority',

        // Vestry meetings, motions, and votes
        'vestry.meeting.view'   => 'View vestry meetings',
        'vestry.meeting.create' => 'Create vestry meetings',
        'vestry.meeting.edit'   => 'Edit vestry meetings',
        'vestry.meeting.delete' => 'Delete vestry meetings',
        'vestry.motion.view'    => 'View vestry motions',
        'vestry.motion.create'  => 'Create vestry motions',
        'vestry.motion.edit'    => 'Edit vestry motions',
        'vestry.motion.vote'    => 'Cast votes on vestry motions',

        // System admin
        'system.primary' => 'Primary system admin operations',
        'system.admin.create' => 'Create new system admin accounts',
        'system.admin.update' => 'Edit non-primary system admin accounts',
        'system.admin.delete' => 'Delete or demote non-primary system admin accounts',
        'system.admin.reset_password' => 'Reset passwords for non-primary system admin accounts',
        'system.province.create' => 'Create province administrator accounts',
        'system.diocese.create' => 'Create diocese administrator accounts',

        // Diocese admin primary / delegation
        'diocese.primary'            => 'Primary diocesan administrator',
        'diocese.admin.create'       => 'Create diocesan administrator accounts',
        'diocese.admin.update'       => 'Edit diocesan administrator accounts',
        'diocese.admin.delete'       => 'Delete diocesan administrator accounts',
        'diocese.admin.reset_password' => 'Reset diocesan administrator passwords',

        // Province admin primary / delegation
        'province.primary'            => 'Primary provincial administrator',
        'province.admin.create'       => 'Create provincial administrator accounts',
        'province.admin.update'       => 'Edit provincial administrator accounts',
        'province.admin.delete'       => 'Delete provincial administrator accounts',
        'province.admin.reset_password' => 'Reset provincial administrator passwords',

        // Parish admin primary / delegation
        'parish.primary'            => 'Primary parish administrator',
        'parish.admin.create'       => 'Create parish administrator accounts',
        'parish.admin.update'       => 'Edit parish administrator accounts',
        'parish.admin.delete'       => 'Delete parish administrator accounts',
        'parish.admin.reset_password' => 'Reset parish administrator passwords',

        // Settings
        'settings.manage'       => 'Manage parish settings',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'systemadmin' => [
            'system.admin',
            'system.primary',
            'system.province.create',
            'system.diocese.create',
            'dashboard.view',
            'report.view',
            'settings.manage',
            'profile.edit',
            'diocese.coa',
            'diocese.calendar',
            'diocese.dashboard',
            'report.export',
            'report.parochial',
            'governance.vestry.view',
            'governance.vestry.manage',
            'governance.report.certify_clerk',
            'governance.report.approve_warden',
            'governance.report.finalize_clergy',
            'governance.diocese.approve',
            'national.event.manage',
            'national.calendar.manage',
            'parishes.manage.official',
        ],
        'provinceadmin' => [
            'province.admin',
            'province.diocese_admin.manage',
            'province.primary',
            'province.admin.create',
            'province.admin.update',
            'province.admin.delete',
            'province.admin.reset_password',
            'province.event.create',
            'province.event.edit',
            'province.event.delete',
            'province.calendar.manage',
            'member.view',
            'groups.view',
            'messaging.view',
            'dashboard.view',
            'profile.edit',
            'report.export',
            'report.parochial',
            'governance.vestry.view',
        ],
        'dioceseadmin' => [
            'diocese.admin',
            'parishes.edit',
            'parishes.delete',
            'member.create',
            'member.edit',
            'member.view',
            'groups.view',
            'messaging.view',
            'service.view',
            'event.view',
            'event.create',
            'event.edit',
            'event.delete',
            'event.register',
            'event.checkin',
            'dashboard.view',
            'profile.edit',
            'diocese.coa',
            'diocese.calendar',
            'diocese.dashboard',
            'report.export',
            'report.parochial',
            'governance.vestry.view',
            'governance.diocese.approve',
        ],
        'parishadmin' => [
            'parish.edit',
            'parish.primary',
            'parish.admin.create',
            'parish.admin.update',
            'parish.admin.delete',
            'parish.admin.reset_password',
            'member.*',
            'family.*',
            'groups.*',
            'messaging.*',
            'service.*',
            'attendance.*',
            'guest.*',
            'followup.*',
            'finance.*',
            'donation.*',
            'expense.*',
            'volunteer.*',
            'event.*',
            'worship.*',
            'report.*',
            'dashboard.view',
            'settings.manage',
            'profile.edit',
            'member.import',
            'member.tag.update',
            'budget.manage',
            'volunteer.remind',
            'guest.manage',
            'event.manage.registration',
            'worship.song',
            'worship.plan',
            'worship.report',
            'report.export',
            'report.parochial',
            'annual_pledge.manage',
            'leadership_position.manage',
            'standing.refresh',
            'governance.vestry.view',
            'governance.vestry.manage',
            'governance.report.certify_clerk',
            'governance.report.approve_warden',
        ],
        'clergy' => [
            'member.view',
            'family.view',
            'groups.view',
            'groups.manage',
            'groups.assign',
            'messaging.view',
            'messaging.send',
            'service.view',
            'service.create',
            'service.edit',
            'attendance.view',
            'attendance.mark',
            'attendance.reports',
            'guest.view',
            'guest.create',
            'guest.edit',
            'followup.manage',
            'event.view',
            'event.create',
            'event.edit',
            'event.register',
            'event.checkin',
            'worship.view',
            'worship.manage',
            'finance.view',
            'donation.view',
            'dashboard.view',
            'report.view',
            'worship.song',
            'worship.plan',
            'worship.report',
            'report.export',
            'report.parochial',
            'standing.refresh',
            'governance.vestry.view',
            'governance.report.finalize_clergy',
        ],
        'treasurer' => [
            'member.view',
            'finance.view',
            'finance.manage',
            'donation.*',
            'expense.*',
            'report.view',
            'dashboard.view',
            'budget.manage',
            'report.export',
        ],
        'volunteer_coordinator' => [
            'member.view',
            'volunteer.*',
            'service.view',
            'event.view',
            'event.register',
            'event.checkin',
            'worship.view',
            'dashboard.view',
            'volunteer.remind',
            'event.manage.registration',
        ],
        'vestry_member' => [
            'governance.vestry.view',
            'vestry.meeting.view',
            'vestry.motion.view',
            'vestry.motion.vote',
            'member.view',
            'dashboard.view',
        ],
        'warden' => [
            'governance.vestry.view',
            'governance.report.approve_warden',
            'vestry.meeting.view',
            'vestry.motion.view',
            'vestry.motion.vote',
            'member.view',
            'dashboard.view',
        ],
        'clerk' => [
            'governance.vestry.view',
            'governance.report.certify_clerk',
            'vestry.meeting.view',
            'vestry.motion.view',
            'vestry.motion.vote',
            'member.view',
            'dashboard.view',
            'report.view',
        ],
        'secretary' => [
            'governance.vestry.view',
            'governance.vestry.manage',
            'vestry.meeting.view',
            'vestry.meeting.create',
            'vestry.meeting.edit',
            'vestry.meeting.delete',
            'vestry.motion.view',
            'vestry.motion.create',
            'vestry.motion.edit',
            'member.view',
            'dashboard.view',
            'report.view',
            'report.export',
        ],
        'followup_volunteer' => [
            'guest.view',
            'followup.view',
            'dashboard.view',
        ],
        'member' => [
            'dashboard.view',
            'profile.edit',
        ],
    ];
}
