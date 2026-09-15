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
- `BaseController::canBypassPendingReviewGate()` allows `systemadmin`, `provinceadmin`, and `dioceseadmin` to bypass parish-level `pending_review`.
- Higher-tier admin requests are excluded from parish/diocesan pending queues (`UserRegistrationRequestModel::getPendingByParish`, `getPendingByDiocese`, `countPendingByDiocese`).
- `BaseController::getCurrentRegistrationRequest()` returns the most recent request (`orderBy id DESC`).
- All approve/dismiss/ban/delete actions should go through `RegistrationApprovalService`.
- `AdminHierarchyGuard` prevents lower-tier admins from acting on higher-tier admin requests.
- `RegistrationApprovalService` preserves existing higher-tier Shield groups when assigning a lower role.

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
