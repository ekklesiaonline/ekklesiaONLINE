<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function audit(bool $getShared = true): \App\Services\AuditService
    {
        if ($getShared) {
            /** @var \App\Services\AuditService */
            return static::getSharedInstance('audit');
        }

        return new \App\Services\AuditService();
    }

    public static function monitoring(bool $getShared = true): \App\Services\MonitoringService
    {
        if ($getShared) {
            /** @var \App\Services\MonitoringService */
            return static::getSharedInstance('monitoring');
        }

        return new \App\Services\MonitoringService();
    }

    public static function memberAccess(bool $getShared = true): \App\Services\MemberAccessService
    {
        if ($getShared) {
            /** @var \App\Services\MemberAccessService */
            return static::getSharedInstance('memberAccess');
        }

        return new \App\Services\MemberAccessService();
    }

    public static function eventVisibility(bool $getShared = true): \App\Services\EventVisibilityService
    {
        if ($getShared) {
            /** @var \App\Services\EventVisibilityService */
            return static::getSharedInstance('eventVisibility');
        }

        return new \App\Services\EventVisibilityService();
    }

    public static function systemSettings(bool $getShared = true): \App\Services\SystemSettingService
    {
        if ($getShared) {
            /** @var \App\Services\SystemSettingService */
            return static::getSharedInstance('systemSettings');
        }

        return new \App\Services\SystemSettingService();
    }

    public static function profileLookup(bool $getShared = true): \App\Services\ProfileLookupService
    {
        if ($getShared) {
            /** @var \App\Services\ProfileLookupService */
            return static::getSharedInstance('profileLookup');
        }

        return new \App\Services\ProfileLookupService(new \App\Models\ProfileModel());
    }

    public static function parishScopeValidator(bool $getShared = true): \App\Services\ParishScopeValidator
    {
        if ($getShared) {
            /** @var \App\Services\ParishScopeValidator */
            return static::getSharedInstance('parishScopeValidator');
        }

        return new \App\Services\ParishScopeValidator();
    }
}
