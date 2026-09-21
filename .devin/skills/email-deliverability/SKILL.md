---
name: email-deliverability
description: Diagnose scoped SMTP, SPF, DKIM, DMARC, Gmail, and test-email delivery failures
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
  - exec
---

Diagnose and safely work with outbound email deliverability in EkklesiaRD.

Key sources:
- `docs/EMAIL_DELIVERABILITY.md` is the canonical provider-alignment and diagnostic runbook.
- `app/Services/EmailScopeService.php` resolves sender identity and SMTP across Parish → Diocese → Province → Organisation → System → `Config\Email`.
- `app/Services/EmailSettingsService.php` persists encrypted settings, sends tests, and classifies failures.
- `app/Controllers/EmailSettingsTrait.php` applies permissions, audit logging, and the AJAX error contract.
- `app/Libraries/DataEncryption.php` provides the `enc:v1:` text-safe credential envelope.
- `docs/OBSERVABILITY_POLICY.md` governs credential/contact-data redaction.

Security boundaries:
- Never print or log SMTP passwords, encrypted password values, App Passwords, tokens, full SMTP transcripts, recipient addresses, or complete message headers.
- Safe metadata includes host, port, crypto mode, sender domain, SMTP username domain, whether a password is present, and decrypted password length.
- Authentication, magic-link, 2FA, device-verification, and OTP mail must use System transport.
- Do not send a real test email unless the user explicitly authorizes it and supplies or confirms the recipient.

Diagnostic workflow:
1. Resolve the relevant entity scope and confirm the operator is authorized for it.
2. Inspect only safe stored metadata and verify `email.smtp_pass` is `is_encrypted=1` and starts with `enc:v1:`. Never display the remainder.
3. Resolve `EmailScopeService::smtpConfig()` for the exact scope and report only safe metadata.
4. Verify the SMTP transport is authorized for the visible From address:
   - Consumer Gmail: `smtp.gmail.com`, TLS, port 587, same full Gmail username/From address, 16-character App Password.
   - Google Workspace custom domain: Google SMTP/relay plus Admin-console DKIM and published SPF/DKIM DNS records.
   - Other provider: provider must authenticate the From domain using SPF or DKIM with DMARC alignment.
5. Map failures:
   - `535` / bad credentials → `SMTP_AUTHENTICATION_FAILED`.
   - connection refused/timeout → `SMTP_CONNECTION_FAILED`.
   - TLS negotiation error → `SMTP_TLS_FAILED`.
   - `550 5.7.30` / DKIM did not pass → `EMAIL_AUTHENTICATION_FAILED`.
   - Apple `554 5.7.1 [BS01]` / local policy → `RECIPIENT_POLICY_REJECTED`.
6. If authorized, run an application-level test with `EmailSettingsService::sendTest()` and report only `success`, `code`, and localized message/reference.
7. Treat `EMAIL_TEST_SENT` as SMTP-provider acceptance, not guaranteed inbox placement. Ask the user to confirm receipt and inspect `Authentication-Results` for `spf=pass`, `dkim=pass`, and aligned `dmarc=pass` where applicable.
8. For missing Outlook/iCloud delivery, check the Gmail Sent folder, delayed Mail Delivery Subsystem notices, recipient Junk/Spam and rules, Outlook's Other tab, blocked senders, and Microsoft 365 message trace where available.
9. Check for a later bounce before declaring final delivery successful.

Important interpretation:
- CodeIgniter does not sign these messages with DKIM. The selected SMTP provider does.
- Do not configure a private DKIM key in EkklesiaRD for Gmail SMTP.
- A tenant From address sent through unrelated System SMTP can fail DKIM/DMARC. Require an authorized tenant transport or use a System-aligned From address with tenant Reply-To.
- For Gmail consumer addresses, the Google Workspace custom-domain DKIM setup instructions do not apply; Gmail signs messages accepted through the authorized Gmail account.
- Outlook.com commonly uses `smtp-mail.outlook.com` with STARTTLS/587, but Microsoft 365 tenants may disable basic SMTP AUTH or require OAuth, which is not currently implemented.
- iCloud commonly uses `smtp.mail.me.com` with STARTTLS/587 and an Apple app-specific password.

When changing code:
1. Keep SMTP resolution block-atomic; never mix host/user/password between hierarchy tiers.
2. Fail closed for tenant SMTP errors; do not silently relay tenant branding through System transport.
3. Return the standard API/AJAX error contract with a stable `error.code`.
4. Audit only the scope and classified result code.
5. Add en/es/fr language keys for user-facing messages.
6. Add regression tests for classification, redaction, encrypted storage, and button-state restoration.
7. Run targeted tests, `tests/unit/AccessibilityAndStaticAnalysisTest.php`, and the full PHPUnit suite.
