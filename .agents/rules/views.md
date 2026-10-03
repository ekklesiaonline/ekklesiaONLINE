---
description: Bootstrap 5 view consistency rules for ekklesiaRD
trigger: always_on
---

# View / UI Consistency Rules

## Titles & Actions

- Do not duplicate page titles inside content views when the top header bar already shows `page_title`.
- For form/detail pages, use a standardized top action row: `d-flex justify-content-start align-items-center mb-3`.
- Keep primary navigation/back/create actions in that row; avoid right-aligned one-off wrappers unless there is an approved exception.

## Buttons & Badges

- Prefer Bootstrap outline variants (`btn-outline-*`).
- All badges must be outline-first: `border border-{color} text-{color} fw-normal`. Never filled `bg-*` badges.
- Extract dynamic badge colors to a PHP variable first; do not inline complex ternaries in both class positions.

## Tables

- Never add hardcoded color classes to `<thead>` (`table-light`, `table-dark`, etc.). Leave `<thead>` unstyled so `data-bs-theme` works.

## CSRF for Native Fetch

All native `fetch()` POST calls must follow this pattern:

```javascript
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
const csrfHeader =
    document.querySelector('meta[name="csrf-header"]')?.content ??
    "X-CSRF-TOKEN";

const headers = {
    "X-Requested-With": "XMLHttpRequest",
    "Content-Type": "application/x-www-form-urlencoded",
};
if (csrfMeta) {
    headers[csrfHeader] = csrfMeta.content;
}

fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers,
    body: new URLSearchParams({ field: value }).toString(),
})
    .then(function (r) {
        const newToken = r.headers.get(csrfHeader);
        if (newToken && csrfMeta) {
            csrfMeta.content = newToken;
        }
        return r.json();
    })
    .catch(function () {
        showAppToast(errFallback, "danger");
    });
```

- Read the `csrf-token` meta tag once before the fetch.
- Send form-encoded bodies (`URLSearchParams`), never `JSON.stringify`.
- Refresh the token from the response header.

## Shared Partials

- Extract repeated modal/form blocks into named partials and include them.
- Prefix partials with `_` (e.g., `_non_portal_modal.php`).
- Use `view()` with an explicit data array when the partial needs custom variables; use `$this->include()` only when controller-injected data is sufficient.
- Document required variables at the top of the partial.

## Collapsible Filter Panels

- Every list/index view with search/filter controls must use a collapsible filter panel.
- Compute a `$xyzFiltersActive` boolean before rendering; open automatically when true.
- Use `form-control-sm` / `form-select-sm`.
- Apply button: `btn btn-sm btn-primary flex-fill` with `bi-funnel`/`bi-search`.
- Clear/Reset link: `btn btn-sm btn-outline-secondary flex-fill` in a `d-flex gap-2` column.
- Preserve non-filter state on Clear.
- Use `lang('Services.apply')` and `lang('App.clear')`.

## Row Actions

- Replace 3+ inline action buttons with a single `⋮` dropdown (`bi-three-dots-vertical`, `btn-outline-secondary btn-sm`).
- Menu: `<ul class="dropdown-menu dropdown-menu-end shadow-sm">`.
- Use `<button class="dropdown-item">`, not `<a>`.
- Destructive actions go last, separated by a divider, with `text-danger`.
- Set `title="<?= esc(lang('App.colActions')) ?>"` on the trigger.

## Deletion UX

- Use split modals:
  - Single-item delete modal: show identity, irreversible warning, required reason.
  - Bulk-delete modal: show selected count, irreversible warning, required reason.
- If a bulk action is triggered with exactly one row, route to the single-item modal.

## Opportunistic Normalization

- When touching legacy views, normalize them to the same header/action/button/badge rules as part of the change.

## Toasts & Modals

- Use toast notifications for AJAX success/error feedback.
- Use modal forms for quick add/edit without leaving the page.
- Keep destructive modals separate from edit modals.

## Terminology

- Use `parish` in code, routes, and models. `parish` is acceptable only in user-facing labels or legacy aliases (e.g., `parishes` → `parish_list` redirect).
