---
name: localization
description: Explain locale resolution, supported locales, and adding language keys
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with localization (i18n) in ekklesiaONLINE.

Key facts:
- `BaseController::resolvePreferredLocale()` is the single source of truth for locale resolution.
- Resolution order: cookie → session → `profile.locale` → `Accept-Language` header → default (`en`).
- Supported locales are defined in `Config\App::$supportedLocales`.
- Locale aliases normalize variants: `sp`, `es-do`, `es_mx`, `es-mx` → `es`; `fr-ca`, `fr_fr`, `fr-fr` → `fr`.
- Common lang namespaces: `App`, `Dashboard`, `Diocesan`, `Services`, `Parishes`, etc.

When asked:
1. Explain how the current locale is chosen for a request.
2. Show where to add or update language keys (`app/Language/<locale>/*.php`).
3. Reference `BaseController::resolvePreferredLocale()` and any relevant language files.
4. Cite specific files and line ranges.
