# Implementation Plan: Bug Fixes for Onboarding, Visitor Conversion, and Role Scoping

This plan details the steps required to resolve the 5 confirmed, reproducible bugs identified during codebase exploration.

---

## 1. Objectives & Guidelines

- **Zero regression policy**: Run the full test suite (`vendor/bin/phpunit --no-coverage`) before and after all changes.
- **Architectural consistency**:
  - Shield group names must consistently use `Config\AuthGroups` constants (e.g. `AuthGroups::DIOCESE_ADMIN = 'dioceseadmin'`).
  - Outbound emails must preserve `EmailScopeService` sender resolution and transport scoping.
  - Credential/OTP emails must use `EmailScopeService::credentialMailer()`.
  - Database writes must respect MySQL table constraints (`profile.user_id` unique key).

---

## 2. Tasks & Proposed Changes

### Task 1: Fix `RegistrationNotificationService` Sender Headers and Diocese Admin Role
**Files to modify**:
- [`app/Services/RegistrationNotificationService.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/app/Services/RegistrationNotificationService.php)
- [`tests/unit/RegistrationNotificationServiceTest.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/tests/unit/RegistrationNotificationServiceTest.php) (new unit test)

**Implementation details**:
1. In `parishAdminRecipients()` (line 143), update `'diocesanadmin'` to `\Config\AuthGroups::DIOCESE_ADMIN`.
2. In `notifyParishAdminsOfPendingReview()` (lines 89–109):
   - Do not call `$email->clear(true)` in a way that wipes the `From` and `Reply-To` headers configured by `EmailScopeService`.
   - Either re-apply `setFrom` and `setReplyTo` per iteration using `$senderConfig = $this->emailScope->senderConfig(['parish_id' => $parishId])`, or re-instantiate `$this->emailScope->mailer(['parish_id' => $parishId])` per recipient.
3. Create `tests/unit/RegistrationNotificationServiceTest.php` testing:
   - Diocesan admins in scope are returned in recipient lists.
   - Headers (`From`, `Reply-To`) remain intact across multiple recipient iterations.

---

### Task 2: Fix `ProvinceStaffDirectoryService` Diocesan Staff Role Filter
**Files to modify**:
- [`app/Services/ProvinceStaffDirectoryService.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/app/Services/ProvinceStaffDirectoryService.php)
- [`tests/unit/ProvinceStaffDirectoryServiceTest.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/tests/unit/ProvinceStaffDirectoryServiceTest.php) (new unit test)

**Implementation details**:
1. On line 60 of `ProvinceStaffDirectoryService::diocesanStaff()`, update the query filter:
   ```php
   ->whereIn('agu.group', [
       \Config\AuthGroups::DIOCESE_ADMIN,
       \Config\AuthGroups::PARISH_ADMIN,
       \Config\AuthGroups::CLERGY,
   ])
   ```
2. Create `tests/unit/ProvinceStaffDirectoryServiceTest.php` verifying that users with group `'dioceseadmin'` are returned when listing diocesan staff within a province.

---

### Task 3: Align Administrative Role Exclusion Filter Across Dashboards and Reports
**Files to modify**:
- [`app/Services/ParishDashboardDataService.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/app/Services/ParishDashboardDataService.php) (line 146)
- [`MemberPortal/Controllers/Standing.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/MemberPortal/Controllers/Standing.php) (lines 146, 158, 174, 185)
- [`Treasurer/Controllers/Reports.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/Treasurer/Controllers/Reports.php) (line 10 `ADMIN_EXCLUDED_GROUPS` and lines 504, 516, 539, 649, 661, 684)
- [`Province/Controllers/Dashboard.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/Province/Controllers/Dashboard.php) (lines 80, 88, 96, 104)

**Implementation details**:
1. In `Treasurer/Controllers/Reports.php`:
   ```php
   private const ADMIN_EXCLUDED_GROUPS = ['provinceadmin', 'dioceseadmin', 'diocesanadmin'];
   ```
2. In all SQL queries performing `LEFT JOIN auth_groups_users agu`, update the condition:
   ```php
   'agu.user_id = u.id AND agu.group IN ("provinceadmin", "dioceseadmin", "diocesanadmin")'
   ```
3. Add a test in `tests/unit/AdminExcludedGroupsTest.php` or extend existing dashboard tests to verify that users holding `dioceseadmin` are excluded from parish member birthday/standing counts.

---

### Task 4: Fix `PersonConversionService::linkToUser` Profile Linking & Unique Constraint Collision
**Files to modify**:
- [`app/Services/PersonConversionService.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/app/Services/PersonConversionService.php) (lines 119–143)
- [`tests/unit/VisitorConversionPrefillTest.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/tests/unit/VisitorConversionPrefillTest.php)

**Implementation details**:
1. In `PersonConversionService::linkToUser()`, query `ProfileModel` by `user_id = $userId` rather than `user_id AND person_id`:
   ```php
   $existingProfile = model(ProfileModel::class)
       ->where('user_id', $userId)
       ->first();

   if (is_array($existingProfile)) {
       model(ProfileModel::class)->update((int) $existingProfile['id'], [
           'person_id'     => $personId,
           'parish_id'     => $parishId,
           'portal_access' => 'enabled',
           'is_confirmed'  => 1,
           'updated_at'    => $now,
       ]);
       (new PersonProfileSyncService())->syncFromProfile((int) $existingProfile['id']);
   } else {
       $profileId = model(ProfileModel::class)->insert([
           'user_id'           => $userId,
           'person_id'         => $personId,
           'parish_id'         => $parishId,
           'first_name'        => (string) ($person['first_name'] ?? ''),
           'last_name'         => (string) ($person['last_name'] ?? ''),
           'email'             => (string) ($person['email'] ?? '') ?: null,
           'phone'             => (string) ($person['phone'] ?? '') ?: null,
           'portal_access'     => 'enabled',
           'is_confirmed'      => 1,
           'created_at'        => $now,
           'updated_at'        => $now,
       ], true);

       if (is_int($profileId) && $profileId > 0) {
           (new PersonProfileSyncService())->syncFromProfile($profileId);
       }
   }
   ```
2. Extend `tests/unit/VisitorConversionPrefillTest.php` with a test case where the linked user already possesses a `profile` row prior to conversion, verifying that `person_id` is linked properly and `syncFromProfile` succeeds.

---

### Task 5: Handle Mailer Send Failure in `SystemPrimary::transferPrimarySystemAdmin`
**Files to modify**:
- [`app/Controllers/SystemPrimary.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/app/Controllers/SystemPrimary.php) (lines 94–106)
- [`tests/database/SystemPrimaryTransferTest.php`](file:///Users/juanrosariodelacruz/Sites/ekklesiaONLINE/tests/database/SystemPrimaryTransferTest.php)

**Implementation details**:
1. Switch to `credentialMailer()` for the OTP email:
   ```php
   $emailService = (new \App\Services\EmailScopeService())->credentialMailer();
   ```
2. Check the boolean return of `$emailService->send(false)`:
   ```php
   $sent = $emailService->send(false);
   if (! $sent) {
       $this->clearPrimaryTransferOtpChallenge();
       return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.otpSendFailed'));
   }
   ```
3. Add or update test coverage for primary transfer OTP issuance and delivery failure fallback.

---

## 3. Verification Plan

1. **Unit & Integration Suite**:
   Run targeted tests for each modified service/controller:
   - `vendor/bin/phpunit --no-coverage tests/unit/RegistrationNotificationServiceTest.php`
   - `vendor/bin/phpunit --no-coverage tests/unit/ProvinceStaffDirectoryServiceTest.php`
   - `vendor/bin/phpunit --no-coverage tests/unit/VisitorConversionPrefillTest.php`
   - `vendor/bin/phpunit --no-coverage tests/unit/EmailScopeServiceTest.php`
2. **Full Regression Suite**:
   - `vendor/bin/phpunit --no-coverage`
   Must achieve 100% pass rate with zero regression across all 814+ tests.
3. **Clean Scratch Artifacts**:
   - Ensure temporary reproduction test files in `scratch/` are archived or cleaned up.
