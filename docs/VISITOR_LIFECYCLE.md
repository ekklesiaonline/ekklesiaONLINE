# Visitor Lifecycle & Management

**Status**: Canonical — supersedes the guest-terminology docs.
**Module**: `Clergy` namespace (`/visitors/*`).
**Tables**: `person`, `parish_affiliation`, `person_visit`, `followup_*`, `call_task`.

---

## 1. Canonical lifecycle

`parish_affiliation.membership_status` is the single stored lifecycle state:

```
visitor → regular_attender → member
                 ↘ inactive / transferred / deceased   (exits)
```

- Every new affiliation defaults to `visitor` — registration approval,
  non-portal creation, `attachParish()`, and visitor intake all land there.
- `member` is only ever written by an explicit operator action: the member
  checkbox at creation, or `MembershipPathwayService` (promotion, member-tag
  toggling, pastoral edits).
- `is_member` is a generated column: `membership_status IN ('member','inactive')`
  (on the parish register). `MembershipStatusService::isMember()` is the
  stricter active-member check.
- Good standing (`member_standing_summary.is_good_standing`), pathway stage,
  and leadership (`leadership_position`) are orthogonal — never encoded in
  `membership_status`.
- Legacy aliases normalize via `MembershipStatusService`: `'guest'` →
  `'visitor'`, `'active'` → `'member'`. Never store the alias.

## 2. Intake paths (all land at `visitor`)

| Path                  | Entry point                          | Flow                                     |
| --------------------- | ------------------------------------ | ---------------------------------------- |
| Staff form            | `GET/POST /visitors/create | store`  | `VisitorDirectoryService::create()`      |
| CSV import            | `POST /visitors/import/process`      | `Import::processConnectionCards()`       |
| Public card           | `POST /connect/{token}`              | `Import::digitalCard()`                  |
| Registration approval | admin action                         | `RegistrationApprovalService`            |
| Non-portal creation   | `POST /members/store` (unchecked)    | `NonPortalMemberProvisioningService`     |

All non-registration paths funnel through `VisitorIntakeService::record()`,
which deduplicates by `email_hash`/`phone_hash` within the parish, provisions
`person` + `parish_affiliation(visitor)` on a miss, and always appends a
`person_visit` row (`visit_type` = `first` or `return`).

## 3. Visitor directory (`/visitors`)

`VisitorDirectoryService::scopeBuilder()` selects affiliations where
`membership_status IN ('visitor','regular_attender') OR converted_to_member_at IS NOT NULL`.

- Default tab: unconverted visitors + attenders.
- `?lifecycle=converted` is a filter value — converted rows are no longer a
  silent mixin. It accepts `&converted_window=30|90|365` to bound the list to
  conversions within that many days (empty = all time).
- `visit_count` (COUNT of `person_visit`) is selected per row.
- The hub "converted" tile counts conversions within a rolling window —
  system setting `visitor.converted_window_days` (default 30, editable at
  `/system/settings` under Visitors). The tile links to the converted list
  pre-filtered to the same window.

### Badge states (derived, evaluated in order)

| State | Condition |
| --- | --- |
| Converted | `converted_to_member_at IS NOT NULL` |
| Followed Up | `followed_up = 1` (manual staff assertion; auto-set on conversion) |
| In Progress | ≥1 sent/skipped log AND ≥1 pending log |
| Pending Follow-up | otherwise |

`followed_up` is never auto-derived from the log — it is an explicit
staff-contact assertion.

## 4. Managed `regular_attender` step

- `POST /visitors/promote-attender/{personId}` (`visitor.edit`) →
  `MembershipPathwayService::transitionForPerson(…, 'regular_attender', …)`.
- `POST /visitors/demote/{personId}` → backward move to `visitor`; no reason
  required by current pathway policy.
- Suggestion (never automatic): visitors with `visit_count >= threshold`
  get a "suggested attender" hint. Threshold comes from
  `ParishConfigService` key `visitor.attender_suggest_threshold` (default 2).

## 5. Promote to member (`POST /visitors/promote/{personId}`)

One endpoint, three modes chosen in the confirmation modal
(`visitor.edit`; `create_account` additionally requires `member.create`):

| Mode | Call |
| --- | --- |
| Mark member | `PersonConversionService::convertToMember($personId, $parishId, null, null, null, $actor)` |
| Link existing user | `…->convertToMember(…, $userId, null, null, $actor)` — target user must already hold an affiliation in this parish |
| Create account | `…->convertToMember(…, null, $email, $password, $actor)` — provisions user + `email_password` identity + `member` group + profile |

`convertToMember()` runs in one transaction: pathway transition to `member`
(writes `person_status_history`, syncs `pathway_stage`), user link/creation,
`linked_user_id` update, pending follow-up rows → `skipped`, and conversion
markers (`converted_to_member_at`, `converted_by_user_id`, `followed_up=1`).

Success redirects to `/people/view/{profile_id}` (profile resolved from
`person_id`; falls back to the visitor detail page when no profile exists).

`POST /visitors/revert-conversion/{personId}` reverses it (status back to
`visitor`, markers cleared) via `VisitorDirectoryService::revertConversion()`.

## 6. Deletion guard

`VisitorDirectoryService::delete()` refuses rows where
`converted_to_member_at IS NOT NULL` or `membership_status IN ('member','inactive')`.
Converted/register members are managed from the People directory.

## 7. Permissions & routes

| Permission | Gates |
| --- | --- |
| `visitor.view` | list, detail, dashboard, exports |
| `visitor.create` | store, CSV import, connection-card admin |
| `visitor.edit` | update, promote-attender, demote, promote, revert-conversion |
| `visitor.delete` | delete (with required reason) |
| `member.create` | promote `create_account` mode only |
| `followup.view` / `followup.manage` | follow-up reads / mutations |

Canonical routes live under `/visitors/*`. Legacy `/guests/*` GETs redirect;
legacy POSTs alias to the same actions (one-release compatibility shims).
The retired `guest-conversions`/`visitor-conversions` pages redirect to
`/visitors?lifecycle=converted`.

## 8. Follow-up engine

See `docs/FOLLOWUP_WORKFLOW_SYSTEM.md`. Key points: `person_visit` insert
(`visit_type='first'`) is the single auto-scheduling point; log mutations
are scoped to visitor-domain affiliations via
`VisitorDirectoryService::visitorAffiliationExists()`.

## 9. Notes feed

The visitor record's Notes tab (`/visitors/view/{personId}`) is a
chronological feed aggregated by `PersonNoteService::feedForPerson()`:

- `person_note` — appendable staff notes (`POST /visitors/notes/add/{personId}`,
  `visitor.edit`); author + timestamp shown. Note bodies are freeform and
  are never written to the audit trail.
- `person_visit.notes` — intake-form and per-visit notes.
- `followup_log.notes` — follow-up outcome notes (mark done / skip / fail).
- `call_task.notes` — queue notes, deduped when identical to the linked
  log's notes.

## 10. Event-registration "guests" are a different concept

`event_registration.guest_name|guest_email|guest_phone` are free-text
plus-one companions on a staff-entered registration. They do not create
`person`/`parish_affiliation` rows and are outside this lifecycle.
