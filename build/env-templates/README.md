# Environment Templates

Annotated `.env` templates for staging and production, used by
`docs/LAUNCH_READINESS.md` step A2 (DEPLOYMENT_RUNBOOK Session 3).

## Usage

1. Copy the matching template to `.env` on the target host.
2. Replace **every** `CHANGE_ME` value. Generate keys with:
   `php -r "echo 'hex2bin:' . bin2hex(random_bytes(32)) . PHP_EOL;"`
3. `encryption.key` and `encryption.blindIndexKey` must be independent and
   unique per environment. `blindIndexKey` falls back to `encryption.key`
   when unset — set it explicitly regardless.
   **Existing deployments (e.g. live staging): do NOT generate new keys.**
   Carry over the current `encryption.key`, the existing
   `encryption.previousKeys` value (verbatim — older ciphertext still needs
   those keys), and the effective blind-index key.
   Replacing `encryption.key` without moving the old value to
   `encryption.previousKeys` breaks decryption; changing `blindIndexKey`
   breaks email/phone lookups until all blind indexes are rebuilt.
   If `blindIndexKey` was unset, setting it to the current `encryption.key`
   is a **temporary compatibility state only** and violates the
   key-independence policy in `SECURITY.md`. Record it as a release-gate
   exception and schedule a migration: generate an independent
   `blindIndexKey`, rebuild all blind indexes, and verify lookups before
   switching. Never switch the key without a verified rebuild.
   Cutover: `DataEncryption` hashes lookups with whichever key is
   configured, so an in-place rebuild while the app serves traffic makes
   already-rebuilt rows unfindable. Do it in a maintenance window: block
   **all** application traffic at the edge (Nginx maintenance page / deny
   upstream) — the app `MaintenanceMode` filter still lets system admins
   through, so it alone does not stop contact writes — then take a verified
   backup, rebuild every blind index with the new key, set
   `encryption.blindIndexKey` to the new key, restart PHP, verify
   email/phone lookups from an operator-only path, then lift the edge block.
   On failure, restore the old key; restore the backup only per
   `docs/DEPLOYMENT_RUNBOOK.md` rollback rules (explicit operator approval
   and confirmed database target before any restore).
4. Verify without printing secrets: `php spark config:check App`,
   `config:check Logger`, `config:check Session`,
   `config:check 'CodeIgniter\Shield\Config\Auth'`. Never run
   `config:check` for `Database`, `Encryption`, or `Email` (prints secrets).
5. `.env` is gitignored; never commit it.

## Local `.env` key-coverage audit (2026-11-21)

Keys present in `.env.example` but absent from local `.env` (all have
code defaults; set explicitly where noted):

- `encryption.blindIndexKey` — falls back to `encryption.key`; set
  independently in staging/production.
- `app.CSPEnabled`, `app.forceGlobalSecureRequests` — production requires
  `true` / resolved-true under `CI_ENVIRONMENT=production`.
- `backup.db.*`, `backup.outputDir`, `backup.retentionDays` — required for
  `build/backup_database.sh --check` / `build/restore_database.sh --check`.
- `monitoring.*`, `logger.threshold` — set explicitly for production.
- `security.apiRateLimit*`, `security.loginRateLimit*`,
  `security.login_2fa_scope`, `security.trustedDeviceTtlDays`,
  `session.maxConcurrent`, `MFA_REQUIRED_FOR_ADMIN_OPS`,
  `SYSTEM_ADMIN_PRIMARY_TRANSFER_OTP_ENABLED` — set explicitly.
- `security.disableDeviceVerificationForTesting` — must be `0`/absent in
  staging+production (local dev sets `true`).
- `families.enforceSingleFamilyMembership` — policy decision pending;
  audit duplicates first:
  `SELECT user_id, COUNT(DISTINCT family_id) AS family_count FROM family_member GROUP BY user_id HAVING family_count > 1;`
- `sms.provider`, `sms.twilio_*` — `log_only` unless SMS delivery needed.
- `email.SMTPTimeout`, `database.default.charset`, `DBCollat` — defaults fine.

Local-only keys not in `.env.example` (now documented):

- `email.googleClientId`, `email.googleClientSecret` — Gmail API OAuth2
  transport (`app/Config/Email.php`); added commented to `.env.example`.
- `email.mailType`, `app.indexPage` — CI4 framework keys; harmless.
