# ekklesiaONLINE — Always-On Project Rules

## Architecture

- CodeIgniter 4.7+ with Shield 1.4+ (v1.4.1) for auth.
- Dependency management via Composer.
- Backend: PHP 8.5+, MySQL 8.0. Frontend: Bootstrap 5, jQuery/vanilla JS, Chart.js.
- Hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is the single source of truth for landing-path resolution.
- `BaseController::isElevatedAdmin()` is the single source of truth for top-tier admins.
- Deanery is a geographic scope (`diocese → deanery → parish`), not an administrative tier; never create Shield groups for holy orders or ecclesiastical offices (`bishop`, `priest`, `deacon`, `rector`, `dean`).
- New parish-tier dashboards live in the `Parish/` namespace under `/parish/*`.
- Old `/dashboard` and `/treasurer/dashboard` URLs are deprecated redirect shims.

## Code & Domain

- Use CI4’s `Validation` class and Shield helpers (`auth()->user()`).
- Validate requests; scope queries by `parish_id` unless the user is elevated.
- Model casts must use nullable prefix form: `?int`, `?string` (never `int?`, `string?`).
- Use `php spark` for code generation, not manual scaffolding.
- Resolve email sender identity and SMTP only through `EmailScopeService`; feed `Email::setFrom()` only from `senderConfig()` output (it sanitizes `from_name` of RFC 5322 specials that break Exchange header parsing).
- Credential-bearing mail (activation, magic link, OTP, device verification, password-change notices) must go through `EmailScopeService::credentialMailer($scope)` — System transport, System-aligned From mailbox, tenant display name/Reply-To.
- Write tests with CIUnitTestCase.
- Run tests with `vendor/bin/phpunit --no-coverage`; this project does not provide a `php spark test` command.

## Documentation & Bootstrapping

- Canonical documentation index: `docs/DOCUMENTATION_CANONICAL.md`.
- Fresh environment seeding: `php spark db:seed DatabaseSeeder`.
- Role-to-menu permissions are enforced by `PermissionRoleSyncSeeder`; canonical policy table is `docs/ROLE_MENU_PERMISSION_POLICY.md`.
- API/AJAX JSON contract: `docs/API_AJAX_ERROR_CONTRACT.md`.

## Terminology

- Prefer `parish` in code, routes, models, and new namespaces. `parish` is acceptable only in user-facing labels or legacy aliases (e.g., `parishes` → `parish_list` redirect).

## Git Workflow

- Always `git push` immediately after committing on `main` — never leave local commits unpushed (they diverge from `origin/main` while the remote moves via PR merges/web edits).
- If `main` does diverge, reconcile with `git pull --rebase origin main` (the local convention is linear history for unpushed local work).

## When In Doubt

- For view/HTML rules, see `.agents/rules/views.md` (or `.devin/rules/views.md`).
- For CI4/Shield coding rules, see `.agents/rules/codeigniter-shield.md` (or `.devin/rules/codeigniter.md`).
- For domain invariants, see `.agents/rules/membership-domain.md` (membership status, person identity, counts), `.agents/rules/sacraments.md` (sacramental records), `.agents/rules/hierarchy-authz.md` (scopes, elevated admins, registration approvals, clergy/deanery governance), and `.agents/rules/parish-finance.md` (donations, funds, stewardship) — or the `.devin/rules/` mirrors.
- For diagnostic playbooks, invoke the relevant skill (`/debug-no-parish`, `/debug-person-not-found`, `/sacrament-sync`, `/data-integrity`, `/email-deliverability`, etc.).
- For the visitor → regular_attender → member lifecycle and promotion flow, see `docs/VISITOR_LIFECYCLE.md` or `/visitor-lifecycle`; for follow-up sequences/dispatch, see `docs/FOLLOWUP_WORKFLOW_SYSTEM.md` or `/visitor-followup`.
- For permission/scope issues, use `/parish-permissions` or `/permission-denied`.
- For audit, observability, and localization questions, use `/audit-logging` or `/localization`.
- For Sunday attendance chart rules, use `/sunday-attendance-charts`.
- For clergy ordination, canonical status, deaneries, and scoped authorization planning, use `/episcopal-clergy-authz-planning`.

## Security & Onboarding Conventions

- `RegistrationGateFilter` must query registration status through `UserRegistrationRequestModel`; do not hardcode the singular table name `user_registration_request` because the deployed table is `user_registration_requests`.
- Device-verification endpoints (`/session/device-verify`, `/session/device-verify/resend`) must be rate-limited via the `loginRateLimit` filter; they are already included in `Config/Filters.php`.
- In development, device verification can be disabled with `security.disableDeviceVerificationForTesting = true` in `.env`.
- CI4's `Email` class does not support a `log` protocol; verification-code flows require SMTP or a mail catcher in test environments.

## Session 7: Final Verification Summary

- Full automated suite: `vendor/bin/phpunit --no-coverage` — 605 tests, 2,700 assertions, 6 skipped.
- Manual curl-based walkthroughs completed:
  - Registration → email activation → profile completion → pending review → admin approval (via `RegistrationApprovalService`).
  - Login → device-verification challenge (when enabled) → `/me` dashboard.
  - Magic-link login and force-password-reset flow.
  - Banned, dismissed, and deactivated accounts are blocked from authenticated routes.
  - Concurrent-session limit enforces a maximum of three tracked sessions per user.
- Fixed a regression in `RegistrationGateFilter` where a hardcoded singular onboarding table name caused banned/dismissed/pending_profile gating to be silently skipped. The filter now delegates table resolution to `UserRegistrationRequestModel`.

## Session 8: From-Header Sanitization

- Root cause of Exchange `550 5.1.20` ("Multiple From addresses are not allowed without a Sender address") bounces: a scoped `email.from_name` containing both a comma and a non-ASCII character (e.g. `Iglesia Episcopal La Transfiguración, Bani RD`). `Email::setFrom()` Q-encodes non-ASCII names via `iconv_mime_encode()`, leaving the comma literal inside the encoded word, so Exchange parses `From:` as two mailboxes.
- Fix: `EmailScopeService::senderConfig()` now sanitizes `from_name` through `sanitizeFromName()` (strips `,;:@<>"()[]\` runs to a single space). All send paths consume `senderConfig()`, so they are covered.
- Verified live: test emails accepted by both iCloud and Outlook.com after the fix.
- Full suite: 640 tests, 2,828 assertions, 6 skipped.

<!-- BEGIN AWS Agent Toolkit rules -->

# AWS Guidance for the new AWS experience

This user has signed up for the new AWS experience. This experience lets you sign into AWS using a social provider and requires the following additional context.

Where this guidance conflicts with the project's own instructions, the project's instructions take precedence.

## Context

### Terminology

- Say "project" instead of "account" — a project contains an AWS account and settings for sharing with other collaborators
- Say "team member" instead of "IAM user" — users are invited by email, not created or federated in IAM
- Say "AWS Settings" when referring to management tasks at [settings.aws.com](https://settings.aws.com/) (project management, billing, team members, spend limits). Users view their actual AWS resources in the AWS Management Console.
- Say "selected Region" when referring to the user's Region — not "home Region"
- The user has a managed IAM experience. This includes a managed service control policies (SCP) and resource control policies (RCP) that govern the use of AWS. They will still need to use IAM to create policies to let services work with each other. If there are questions about the SCPs or RCPs, go to the documentation at <https://docs.aws.amazon.com/accounts/latest/reference/scps-and-rcps-for-projects.html>

### Constraints

- All projects share a single AWS Region determined by the user's contact address. Resources cannot be created in other Regions
- When developing:
  - MUST create all Regional resources in the project's assigned Region
  - You CAN create AWS WAF and Cloudwatch Logs resources in us-east-1 when there are global resources (like a global WAF instance) that require a connection to dependencies in us-east-1. You should not use these for any other reason, because resources in the selected Region will provide lower cost (due to no cross-Region traffic), increased availability (due to no cross-Region traffic), and easier manageability (due to not needing to look in another Region). When you need to do an inventory of resources, you need to look in both the selected Region and us-east-1 for Cloudwatch Logs or WAF resources.
  - MUST NOT attempt to create Lambda, API Gateway, or other Regional resources in any other Region
  - MUST direct users to confirm their Region in AWS Settings > View all projects > Overview > Additional Info > Region. If the user cannot confirm their Region, check in ~/.aws/config
  - MUST NOT use Lambda@Edge — excluded from both Lambda and CloudFront
  - MUST NOT use CloudFormation StackSets — no multi-account or multi-Region deployments
  - MUST NOT attempt cross-Region actions — no cross-Region replication for DynamoDB/S3/RDS, no multi-Region KMS keys
  - MUST NOT use Route 53 cross-Region routing — geolocation, latency-based, and failover routing policies are not available
  - CloudFront is a global service and its actions ARE allowed in `us-east-1`. A user can create a CloudFront distribution pointing to their project-region Lambda function URL or API Gateway. However, Lambda and API Gateway themselves MUST NOT be created in `us-east-1` — they must be in the project Region.
  - Reduced availability in `eu-north-1` specifically: Amazon Rekognition, Amazon Textract, Amazon Personalize, AWS App Runner are not available in that Region.
- IAM permissions for human access are managed by AWS. Don't assign roles to team members unless absolutely necessary
- The user may have a spend limit if they are on the paid plan. The limit that pauses their project if it's exceeded. If resources suddenly become inaccessible, ask if they have a spend limit configured. Only project owners can modify a spend limit.
- When developing:
  - MUST ask about spend limit status if the user reports sudden "Access Denied" errors on operations that previously worked
  - MUST direct users to check spend status in AWS Settings > Billing
  - MUST check if a user has upgraded their account to the paid plan
  - MUST ask the user if they want to clean up the successfully created resources or keep them to reduce cost
- The user sets up billing, creates spend limits, and retrieves and pays invoices in AWS Settings. The user creates budgets and optimizes their costs in the AWS Billing and Cost Management console
- Not all AWS services are available. If a service isn't working, do the following:
  1. Run the command `aws freetier get-account-plan-state`
  2. If accountPlanType": "FREE", check the [Free Tier supported services list](https://docs.aws.amazon.com/accounts/latest/reference/supported-services-sign-up-new.html#supported-services-free-tier) next,
  3. If accountPlanType": "PAID", check the [Paid Tier supported services list](https://docs.aws.amazon.com/accounts/latest/reference/supported-services-sign-up-new.html#supported-services-paid-plan).
  4. If neither list shows the service, check the [Not supported for this experience list](https://docs.aws.amazon.com/accounts/latest/reference/supported-services-sign-up-new.html#unsupported-services). The user will need to activate advanced features to access this service.
- Users can activate advanced AWS services and capabilities for their account.
- Before starting a task, check whether a relevant AWS skill is available. Load the skill with retrieve_skill and prefer its guidance over general knowledge.

### Help level

- help_level (required): LOW, MEDIUM, or HIGH. While a user is building, you MUST ask the user: "How much guidance would you like from me? Low (I only flag security risks), medium (I ask a couple of clarifying questions if something seems off), or high (I explain what I'm doing, suggest alternatives, and flag best practices)."

You CAN update this rule file to save a user's help_level.

Constraints for each level:

**LOW:**

- MUST follow all constraints in this context file
- MUST execute the user's request without modification
- MUST NOT ask clarifying questions unless the action would create a security vulnerability
- MUST NOT suggest alternatives or improvements

**MEDIUM:**

- MUST execute the user's request
- MAY ask up to two clarifying questions per task if the request has ambiguity or a potential issue
- MUST NOT repeat a question or suggestion the user has already dismissed
- MUST NOT explain trade-offs or alternatives unless the user asks

**HIGH:**

- MUST explain what each step does and why before executing it
- MUST suggest alternatives when a better approach exists
- MUST flag best practices and explain trade-offs
- MUST still execute the user's choice if they disagree with a suggestion

<!-- END AWS Agent Toolkit rules -->
