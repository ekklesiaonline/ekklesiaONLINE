# EkklesiaRD — Multilingual (i18n) Implementation Plan

**Supported locales:** English (`en`) · Spanish (`es`) · French (`fr`)
**Strategy:** User-preference locale (DB → session → filter) for authenticated routes; browser negotiation for login/register pages.
**Reference:** [CI4 Localization docs](https://codeigniter.com/user_guide/outgoing/localization.html)

---

## Overview of Phases

| Phase | Scope                                     | Risk   | Testing gate                                                     |
|-------|-------------------------------------------|--------|------------------------------------------------------------------|
| 1     | Foundation (config, DB, model)            | Low    | Smoke: no regressions                                            |
| 2     | LocaleFilter + BaseController hook        | Low    | Smoke: locale set in session resolves correctly                  |
| 3     | Language file skeletons (en/es/fr)        | None   | Unit: `lang()` returns correct strings per locale                |
| 4     | Auth/Login pages (browser negotiation)    | Low    | Manual: login page renders in Spanish/French on matching browser |
| 5     | Profile locale preference UI              | Low    | Manual: change locale in profile, page reflects it on next load  |
| 6     | Convert views — module by module          | Medium | Module regression tests after each module                        |
| 7     | Convert controller flash/JSON messages    | Low    | AJAX smoke after each controller                                 |
| 8     | Final QA & cleanup                        | —      | Full regression, accessibility check                             |

**Rule:** Do not proceed to the next phase until the testing gate for the current phase passes.

---

## Phase 1 — Foundation

### Steps--

#### 1.1 Update `App.php` — add locales

**File:** `app/Config/App.php`

```php
public string $defaultLocale = 'en';
public bool $negotiateLocale = false;          // stays false — filter handles it
public array $supportedLocales = ['en', 'es', 'fr'];
```

> `negotiateLocale` stays `false` globally. The `LocaleFilter` will negotiate for unauthenticated
> routes explicitly, giving us full control.

#### 1.2 Migration — add `locale` to `profile`

Create `app/Database/Migrations/YYYY_MM_DD_000000_add_locale_to_profiles.php`:

```php
public function up(): void
{
    $this->forge->addColumn('profile', [
        'locale' => [
            'type'       => 'VARCHAR',
            'constraint' => 10,
            'default'    => 'en',
            'null'       => false,
            'after'      => 'country',
        ],
    ]);
}

public function down(): void
{
    $this->forge->dropColumn('profile', 'locale');
}
```

Run: `php spark migrate`

#### 1.3 Update `ProfileModel`

Add `'locale'` to `$allowedFields`.

Add validation rule (optional but recommended):

```php
'locale' => 'required|in_list[en,es,fr]',
```

### Testing Gate 1 ✅

```bash
# 1a. Confirm migration ran cleanly
php spark migrate:status

# 1b. Confirm column exists
php spark db:table profile   # check locale column is present

# 1c. Confirm app still loads (no regressions)
curl -s -o /dev/null -w "%{http_code}" http://ekklesiard.local/
# → 200 or 302 (redirect to login)

# 1d. Quick model test (Tinker/spark console)
php spark repl
# > $p = new \App\Models\ProfileModel(); var_dump($p->getAllowedFields()); // locale present?
```

**Pass criteria:** Migration runs without error; `profile.locale` column visible; app still loads.

---

## Phase 2 — LocaleFilter + BaseController Hook

### Steps

#### 2.1 Create `app/Filters/LocaleFilter.php`

```php
<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $supported = config('App')->supportedLocales;

        if (auth()->loggedIn()) {
            // Authenticated: use profile preference (cached in session)
            $locale = session('user_locale');

            if (! $locale) {
                $profile = model('ProfileModel')->where('user_id', auth()->id())->first();
                $locale  = $profile['locale'] ?? 'en';
                session()->set('user_locale', $locale);
            }
        } else {
            // Unauthenticated: negotiate from Accept-Language header
            $locale = $request->negotiateLanguage($supported);
        }

        if (in_array($locale, $supported, true)) {
            $request->setLocale($locale);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing
    }
}
```

#### 2.2 Register filter in `app/Config/Filters.php`

```php
// aliases
'localeSet' => \App\Filters\LocaleFilter::class,

// globals before (add to existing array)
'before' => [
    // ... existing
    'localeSet',
],
```

> Because `localeSet` reads `auth()->loggedIn()`, it is safe to run globally.
> For unauthenticated routes it uses browser negotiation; for authenticated routes it uses the profile.

#### 2.3 Flush locale from session on logout

In the Shield event or your logout controller, add:

```php
session()->remove('user_locale');
```

Shield fires an `logout` event — hook into `app/Config/Events.php`:

```php
Events::on('shield-logout', function ($user) {
    session()->remove('user_locale');
});
```

### Testing Gate 2 ✅

```bash
# 2a. Log in with a profile where locale = 'es'
# Manually set: UPDATE profile SET locale = 'es' WHERE user_id = <your test user>;
# Then log in and add a debug line in any controller:
# var_dump(service('request')->getLocale()); exit;
# Expected: string(2) "es"

# 2b. Log out — confirm user_locale is removed from session
# Re-open login page with Accept-Language: fr header and confirm locale = 'fr'
# curl -H 'Accept-Language: fr,fr-FR;q=0.9' http://ekklesiard.local/login -v | grep -i locale

# 2c. No regression on routes that previously worked
curl -s -o /dev/null -w "%{http_code}" http://ekklesiard.local/dashboard   # → 302 (to login)
```

**Pass criteria:** `getLocale()` returns correct value per user preference; login page responds to browser language; no 500 errors.

---

## Phase 3 — Language File Skeletons

Language files are already created as part of this plan document delivery. They cover:

| File              | Module                                         |
|-------------------|------------------------------------------------|
| `App.php`         | Global nav, buttons, status labels, pagination |
| `Auth.php`        | Login, register, password reset, verification  |
| `Dashboard.php`   | Dashboard widgets, stats                       |
| `Members.php`     | Member list, detail, status                    |
| `Families.php`    | Family CRUD                                    |
| `Services.php`    | Service list, detail, form                     |
| `Attendance.php`  | Attendance recording, reports, KPIs            |
| `Guests.php`      | Guest list, follow-up tasks                    |
| `Finance.php`     | Donations, expenses, budgets                   |
| `Profile.php`     | User profile, locale selector                  |
| `Validation.php`  | Custom validation messages                     |

Directories created: `app/Language/en/`, `app/Language/es/`, `app/Language/fr/`

### Testing Gate 3 ✅

```bash
# 3a. Syntax check all language files
for f in app/Language/**/*.php; do php -l "$f"; done
# → No syntax errors

# 3b. Test lang() resolution from spark console
php spark repl
# > service('request')->setLocale('es');
# > echo lang('App.save');   // → "Guardar"
# > echo lang('App.cancel'); // → "Cancelar"

# > service('request')->setLocale('fr');
# > echo lang('App.save');   // → "Enregistrer"
```

**Pass criteria:** All files parse cleanly; `lang()` resolves correct strings for each locale.

---

## Phase 4 — Login & Register Pages (Browser Negotiation)

At this point `LocaleFilter` already negotiates for unauthenticated routes via `Accept-Language`.
This phase converts the hardcoded strings in the auth view to `lang()` calls.

### Steps-

#### 4.1 Convert `app/Views/auth/register.php`

Replace every hardcoded English string with `lang('Auth.key')`.
(Already partially done — some `lang()` calls exist.)

Ensure all labels, placeholders, and button text use `lang()`.

#### 4.2 Verify Shield's built-in auth language files

Shield ships `vendor/codeigniter4/shield/src/Language/{en}/Auth.php`.
Override keys at `app/Language/es/Auth.php` and `app/Language/fr/Auth.php` (already created).

CI4 merges app-level language files over vendor files, so only override what differs.

### Testing Gate 4 ✅

```bash
# 4a. Test with Spanish browser preference
curl -H 'Accept-Language: es,es-ES;q=0.9' http://ekklesiard.local/login
# → Response HTML contains "Iniciar sesión" (not "Login")

# 4b. Test with French browser preference
curl -H 'Accept-Language: fr' http://ekklesiard.local/login
# → Response HTML contains "Se connecter"

# 4c. Test English fallback (unsupported locale)
curl -H 'Accept-Language: zh' http://ekklesiard.local/login
# → Falls back to English — "Login"

# 4d. No visual regressions (layout intact)
# Open http://ekklesiard.local/login in browser — check layout is not broken
```

**Pass criteria:** Login/register pages render in the correct language based on browser preference; layout is unchanged; English fallback works.

---

## Phase 5 — Profile Locale Preference UI

### Steps---

#### 5.1 Add locale selector to Profile edit form

In `MemberPortal/Views/Profile/edit.php`, add a `<select>` for locale:

```html
<select name="locale" class="form-select">
    <option value="en" <?= $profile['locale'] === 'en' ? 'selected' : '' ?>>English</option>
    <option value="es" <?= $profile['locale'] === 'es' ? 'selected' : '' ?>>Español</option>
    <option value="fr" <?= $profile['locale'] === 'fr' ? 'selected' : '' ?>>Français</option>
</select>
```

#### 5.2 Update Profile controller to save `locale`

`locale` is already in `$allowedFields` (added in Phase 1).
After a successful profile update, clear `user_locale` from session so the filter reloads it:

```php
session()->remove('user_locale');
```

#### 5.3 Display current locale in Profile view

In `MemberPortal/Views/Profile/view.php`, show the locale label using `lang('Profile.language')`.

### Testing Gate 5 ✅

```bash
# 5a. Open Profile edit, change locale to 'es', save
# → After save, next page load should display in Spanish

# 5b. Verify DB was updated
# SELECT locale FROM profile WHERE user_id = <test_user>; → 'es'

# 5c. Verify session was cleared and reloaded
# After save, add debug: var_dump(session('user_locale')); → null (cleared), then 'es' on next request

# 5d. Log out, log in again — confirm locale persists across sessions
```

**Pass criteria:** Locale change is persisted to DB; page language switches on next request; locale survives logout/login cycle.

---

## Phase 6 — Convert Views (Module by Module)

Work through modules in this order (least complex to most):

| Order | Module | Views | Notes |
|---|---|---|---|
| 6.1 | Profile | `edit.php`, `view.php`, `complete.php` | Small; touches Phase 5 work |
| 6.2 | Families | `index.php`, `view.php`, `create.php`, `edit.php` | Already cleaned up icons |
| 6.3 | Guests & Follow-up | `Guests/`, `FollowUp/` | Self-contained |
| 6.4 | Services | `Services/index.php` through `view.php` | Table columns already standardized |
| 6.5 | Attendance | `Attendance/service.php`, `reports.php` | KPI tile labels, table headers |
| 6.6 | Finance | `Donations/`, `Expenses/`, `Budgets/` | Treasurer module |
| 6.7 | Dashboard | `Dashboard/index.php` | High-traffic — do last of visible pages |
| 6.8 | Layouts/nav | `app/Views/layouts/main.php` | Navigation labels, theme toggle label |
| 6.9 | Admin/error pages | Error views, `Admin/consistency.php` | Low priority |

### Per-module conversion process

For each module:

1. Open the view file.
2. For each hardcoded string, replace with `lang('Module.key')`.
3. Ensure the corresponding key exists in all three locale files (`en`, `es`, `fr`).
4. Run `php -l` on the modified view.
5. Open the page in browser — visual check in all three locales by temporarily forcing locale.

### Testing Gate 6 (per module) ✅

```bash
# Temporarily force locale in LocaleFilter for testing (comment out, hardcode):
# $request->setLocale('es');

# Open the module's main page, verify:
# - No literal English strings visible (all translated)
# - No missing translation keys (no raw "App.save" output)
# - Layout unchanged
# - AJAX operations still work (JSON responses unchanged in Phase 6)

# After each module, restore LocaleFilter and run:
php spark test --filter <ModuleTest>   # if unit tests exist
```

**Pass criteria (per module):** All visible strings are translated; no `lang()` key fallback showing raw key; no layout regressions; existing functionality unchanged.

---

## Phase 7 — Controller Flash Messages & JSON Responses

Translate all `session()->setFlashdata()` calls and JSON `'message'` keys that bubble up to the UI.

### Pattern

```php
// Before
return $this->response->setJSON(['message' => 'Member updated successfully.']);

// After
return $this->response->setJSON(['message' => lang('Members.updateSuccess')]);
```

### Steps

7.1 Search all controllers for `setFlashdata` and JSON `message` keys:

```bash
grep -rn "setFlashdata\|'message'" app/Controllers/ MemberPortal/Controllers/ Worship/Controllers/ \
  Treasurer/Controllers/ Volunteer/Controllers/ | grep -v '//'
```

7.2 Replace each string with a `lang()` call and add the key to all three locale files.

7.3 For AJAX toasts that display the returned `message`, no JS changes are needed — the translated string is already in the server response.

### Testing Gate 7 ✅

```bash
# 7a. With locale set to 'es', perform a CRUD action (e.g., add a family)
# → Toast/flash notification appears in Spanish

# 7b. Test error path: submit invalid form
# → Validation messages appear in Spanish (from Validation.php)

# 7c. Confirm JSON-consuming JS is unaffected (toast still displays the message)
```

**Pass criteria:** All user-visible messages from controllers appear in the active locale; no raw English strings in toast notifications.

---

## Phase 8 — Final QA & Cleanup

### Steps

8.1 **Missing key sweep** — search for any remaining hardcoded strings:

```bash
# Find hardcoded English strings in view files (heuristic — look for common words)
grep -rn '"Add\|"Edit\|"Delete\|"Save\|"Cancel\|"View\|">Add \|">Edit \|">Save ' \
  app/Views/ MemberPortal/Views/ Worship/Views/ | grep -v lang | grep -v "<!--"
```

8.2 **Locale completeness check** — confirm every EN key has an ES and FR equivalent:

```bash
# Count keys in each locale file (should match)
php -r "echo count(include 'app/Language/en/App.php'), ' en\n';"
php -r "echo count(include 'app/Language/es/App.php'), ' es\n';"
php -r "echo count(include 'app/Language/fr/App.php'), ' fr\n';"
```

8.3 **Accessibility** — confirm `lang` attribute on `<html>` reflects active locale:

In `app/Views/layouts/main.php`, update the `<html>` tag:

```php
<html lang="<?= service('request')->getLocale() ?>" data-bs-theme="...">
```

8.4 **RTL check** — EN, ES, FR are all LTR, no CSS changes needed.

8.5 **Performance** — CI4 caches language files automatically; no extra caching needed.

8.6 Remove any debug lines or hardcoded locale overrides used during testing.

### Testing Gate 8 ✅

```bash
# 8a. Full app walkthrough in each locale (en, es, fr) — no raw strings visible
# 8b. Confirm <html lang="es"> renders correctly
# 8c. Run full test suite
php spark test
# → All existing tests still pass

# 8d. Confirm no performance regression
# Open browser DevTools Network tab — no extra requests introduced
```

**Pass criteria:** Zero raw English strings in non-English locales; `<html lang>` attribute correct; all tests pass.

---

## Reference: File Locations

```
app/
├── Config/
│   ├── App.php              ← Phase 1.1: supportedLocales
│   └── Filters.php          ← Phase 2.2: register LocaleFilter
├── Database/Migrations/
│   └── ..._add_locale_to_profiles.php  ← Phase 1.2
├── Filters/
│   └── LocaleFilter.php     ← Phase 2.1 (NEW)
├── Language/
│   ├── en/
│   │   ├── App.php
│   │   ├── Auth.php
│   │   ├── Attendance.php
│   │   ├── Dashboard.php
│   │   ├── Families.php
│   │   ├── Finance.php
│   │   ├── Guests.php
│   │   ├── Members.php
│   │   ├── Profile.php
│   │   ├── Services.php
│   │   └── Validation.php
│   ├── es/                  ← same files, Spanish translations
│   └── fr/                  ← same files, French translations
└── Models/
    └── ProfileModel.php     ← Phase 1.3: add 'locale' to $allowedFields
```

---

## Quick Reference: CI4 `lang()` Usage

```php
// Simple key
lang('App.save')                    // → "Save" / "Guardar" / "Enregistrer"

// With parameters (positional)
lang('Members.foundCount', [42])    // → "42 members found"

// In view
<?= lang('Dashboard.recentServices') ?>

// In controller (flash)
session()->setFlashdata('success', lang('Families.createSuccess'));
```

---

## Estimated Effort per Phase

| Phase | Estimated effort |
|---|---|
| 1 — Foundation | 30 min |
| 2 — Filter + BaseController | 45 min |
| 3 — Language file skeletons | Done (part of this plan) |
| 4 — Auth pages | 30 min |
| 5 — Profile locale UI | 45 min |
| 6 — Views (all modules) | 4–6 hours |
| 7 — Controller messages | 1–2 hours |
| 8 — Final QA | 1 hour |
| **Total** | **~9–11 hours** |
