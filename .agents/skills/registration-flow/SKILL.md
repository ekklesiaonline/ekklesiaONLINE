---
name: registration-flow
description: Explain user registration, pending review, auto-approval, and approval workflows
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with the user registration and approval workflow.

Key rules:
- `RegisterController::registerView()` and `RegisterController::parishHierarchyValid()` require populated `parish`, `diocese`, and `province` tables (`INNER JOIN` chain). If `/register` has no parishes or rejects all selections with `Auth.parishSelectionRequired`, run `php spark directory:verify-readiness --strict` and populate the hierarchy per `docs/HOSTGATOR_DEPLOY.md` §4.1 (`parish_hierarchy_transfer.sh` or `directory:sync-ueid --apply`).
- `BaseController::canBypassPendingReviewGate()` allows `systemadmin`, `provinceadmin`, and `dioceseadmin` to bypass parish-level `pending_review`.
- Higher-tier admin requests are excluded from parish/diocesan pending queues (`UserRegistrationRequestModel::getPendingByParish`, `getPendingByDiocese`, `countPendingByDiocese`).
- `BaseController::getCurrentRegistrationRequest()` returns the most recent request (`orderBy id DESC`).
- All approve/dismiss/ban/delete actions should go through `RegistrationApprovalService`.
- `AdminHierarchyGuard` prevents lower-tier admins from acting on higher-tier admin requests.
- `RegistrationApprovalService` preserves existing higher-tier Shield groups when assigning a lower role.
- Activation email sends (`ScopedEmailActivator::show()`) are capped per recipient address: max `env('security.activationEmailMaxPerDay')` (default 5) emails per address per fixed 24h window (starts at the first send; later sends do not extend it) via `RecipientSendLimiter` (`activation_email_rl_<md5(email)>`), on top of the 60s per-session resend cooldown. When the cap trips the code-entry form still renders and the previously issued code stays valid — no new code or email is issued.

Pending registration UI:
- Dashboard alert banner links to `people#pendingRegistrationsCard`.
- Approval card is rendered by `Clergy/Views/People/index.php`.
- Bulk action endpoint: `POST /members/registrations/bulk-action`.
- Individual endpoints: `POST /members/registrations/approve/{id}` and `POST /members/registrations/dismiss/{id}`.

When asked:
1. Trace the registration request from submission through review to approval.
2. Reference the relevant controller, service, and model files.
3. Note auto-approval rules and hierarchy guards.
4. Cite specific files and line ranges.
