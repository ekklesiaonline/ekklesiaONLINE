# Email Deliverability and DKIM

## Purpose

EkklesiaRD resolves outbound email across System, Organisation, Province,
Diocese, and Parish scopes. This document describes the transport/authentication
requirements for scoped senders and the diagnostic process for SMTP, SPF, DKIM,
and DMARC failures.

## Verified parish configuration (2026-09-21)

The parish account involved in the DKIM incident resolves to:

- SMTP host: `smtp.gmail.com`
- SMTP port: `587`
- Encryption: `TLS`
- SMTP username and From address: the same Gmail account
- Password: encrypted at rest with the `enc:v1:` text-safe envelope and
  successfully resolved as a 16-character App Password

An authorized application-level test submitted a message through the scoped
parish mailer and returned `EMAIL_TEST_SENT`. This confirms that Gmail accepted
the SMTP submission. Final inbox placement and the delivered message's
`Authentication-Results` header remain receiving-provider outcomes.

## What `550 5.7.30` means

Gmail returns `550 5.7.30` when a message presented for delivery does not pass
required DKIM authentication. The common causes are:

1. A tenant From address is sent through an unrelated System SMTP account.
2. A custom domain has not enabled DKIM at its mail provider or published the
   provider's DKIM DNS record.
3. SPF/DKIM authenticate a different domain than the visible From domain, so
   DMARC alignment fails.
4. A forwarding or relay path modifies a DKIM-signed message.

Do not add a DKIM private key to EkklesiaRD for Gmail SMTP. CodeIgniter submits
the message to Gmail, and Gmail signs accepted outbound mail. For a consumer
`@gmail.com` account, use that same account as SMTP username and From address.
For Google Workspace custom domains, the Workspace administrator must enable
DKIM and publish the Google-provided DNS TXT record.

## What `550 5.1.20` means

Exchange Online Protection returns
`550 5.1.20 Multiple From addresses are not allowed without a Sender address`
when the `From:` header parses as more than one mailbox. In EkklesiaRD the
trigger was a scoped `email.from_name` containing both a comma and a non-ASCII
character (e.g. `Iglesia Episcopal La Transfiguración, Bani RD`): CI4's
`Email::setFrom()` Q-encodes non-ASCII display names via
`iconv_mime_encode()`, which leaves the comma literal inside the encoded word,
so Exchange reads two addresses and — with no `Sender:` header — rejects.

`EmailScopeService::senderConfig()` now strips RFC 5322 address-list specials
(`,;:@<>"()[]\`) from the resolved `from_name`, so this cannot recur for any
send path. Do not reintroduce raw settings values into `setFrom()` calls.

## Sender/transport alignment policy

- Credential-bearing mail always uses System transport.
- Tenant pastoral/operational mail may use tenant SMTP only when host, username,
  and password form a complete block at one hierarchy tier. Resolution is
  block-atomic: a partial block is skipped (never mixed with another tier) and
  the next tier up is tried.
- `email.enabled` may be set to `0` at any tier to disable that tier's `email.*`
  settings without deleting them; the next tier up applies. A System-tier lock
  on `email.smtp_host`/`email.smtp_user`/`email.smtp_pass` forces the
  System-tier block.
- A Gmail From address must be submitted through the authorized Gmail account,
  using `smtp.gmail.com`, TLS, port 587, and a Google App Password.
- A custom-domain From address must use an SMTP provider authorized by that
  domain's SPF record and/or DKIM configuration.
- Never route a tenant-branded From address through System SMTP unless the
  System provider is explicitly authorized to send for that domain.
- SMTP passwords must remain encrypted and must never appear in logs, audit
  rows, debugger output, documentation, or test output.

## Gmail setup

For consumer Gmail:

1. Enable two-step verification on the Google account.
2. Generate a 16-character App Password.
3. Configure:
   - Host: `smtp.gmail.com`
   - Port: `587`
   - Encryption: `TLS`
   - Username: full Gmail address
   - Password: App Password, not the regular account password
   - From: same Gmail address
4. Save before running the test send.

For Google Workspace custom domains:

1. Configure the same authenticated SMTP submission or the approved Workspace
   relay.
2. Enable DKIM in the Google Admin console.
3. Publish the Google-provided DKIM TXT record at the domain DNS provider.
4. Publish an SPF record authorizing Google.
5. Publish DMARC after SPF/DKIM alignment has been confirmed.

## Outlook and iCloud SMTP

The settings form does not restrict SMTP to Gmail. It supports providers that
accept CodeIgniter's username/password SMTP authentication.

For Outlook.com consumer mailboxes, the usual submission endpoint is
`smtp-mail.outlook.com`, port 587 with STARTTLS. The From address must be the
authenticated mailbox or an alias permitted by Microsoft. Microsoft 365 tenants
may disable SMTP AUTH or require OAuth; EkklesiaRD does not currently implement
Microsoft OAuth SMTP, so those tenants need an approved SMTP relay or a future
OAuth transport integration.

For iCloud Mail, use `smtp.mail.me.com`, port 587 with STARTTLS, the full iCloud
mail address, and an Apple app-specific password. The From address must be an
address/alias enabled on that Apple account.

A provider returning SMTP success has accepted responsibility for the message,
but it can still generate a delayed bounce or filter the message later.

## Safe diagnostic procedure

1. Inspect settings metadata only:
   - host, port, crypto, username domain, From domain;
   - whether a password exists and decrypts;
   - never print the password or ciphertext.
2. Resolve `EmailScopeService::smtpConfig()` for the relevant scope and confirm
   that the expected tier supplies a complete SMTP block.
3. Confirm the SMTP username and visible From address are authorized aliases at
   the provider.
4. Run `EmailSettingsService::sendTest()` to an address the operator controls.
5. Interpret the result code:
   - `SMTP_AUTHENTICATION_FAILED`: credentials/App Password rejected.
   - `SMTP_CONNECTION_FAILED`: host, port, firewall, or timeout problem.
   - `SMTP_TLS_FAILED`: encryption mode/port mismatch.
   - `EMAIL_AUTHENTICATION_FAILED`: SPF/DKIM/DMARC rejection such as Gmail
     `550 5.7.30`.
   - `EMAIL_TEST_SENT`: SMTP provider accepted the message.
6. At the receiving mailbox, inspect `Authentication-Results`. For a healthy
   custom-domain message, expect aligned SPF or DKIM; preferably both, plus
   `dmarc=pass`.
7. Check spam/quarantine and asynchronous bounce messages before declaring
   final delivery successful.

## Gmail sender to Outlook or iCloud recipients

When Gmail accepts a message but an Outlook or iCloud recipient does not see it,
EkklesiaRD cannot treat SMTP acceptance as proof of final delivery. Each test
message includes a unique reference in its subject/body so the accepted request,
bounce, provider trace, and received message can be correlated. Check in this
order:

1. Confirm the message appears in the authenticated Gmail account's Sent
   folder. If it does not, the expected Gmail transport was not used.
2. Check the sender mailbox for a delayed Mail Delivery Subsystem notice. A
   later bounce contains the recipient provider's final rejection reason.
3. Ask the recipient to check Junk/Spam, Outlook's Other tab, iCloud rules, and
   blocked-sender settings.
4. For Microsoft 365 recipients, use the tenant message trace if available.
5. Inspect `Authentication-Results` on a delivered copy. Gmail SMTP should add
   a valid Gmail DKIM signature; the application itself does not DKIM-sign.
6. Use a unique recipient-controlled address for each test so forwarding rules
   do not obscure the result.

The settings page therefore reports **SMTP provider accepted** rather than
**email delivered**. Delivery confirmation requires a received message or a
provider delivery/message-trace event.

Apple `554 5.7.1 [BS01]` is a permanent recipient-policy rejection, not an SMTP
login failure. Apple states that iCloud evaluates IP/domain reputation, message
content, user feedback, SPF, DKIM, DMARC, RFC 5321/5322 compliance, consistency,
and bounce handling; it does not offer a sender allow list. For mail submitted
through consumer Gmail, Google controls the sending IP and DKIM signature. If
BS01 persists after confirming the message is in Gmail Sent and the From account
matches the authenticated Gmail account, retain the Apple transaction ID and
contact Apple Support/Postmaster as described in the rejection URL.

Outlook can silently place accepted messages in Junk, Other, quarantine, or a
Microsoft 365 policy queue. If there is no bounce, use the unique test reference
in Microsoft 365 message trace or ask the recipient administrator to search the
trace. Absence from an Outlook recipient's mailbox is not evidence that the
message was absent from the Gmail sender's Sent folder; those are separate
mailboxes.

## Logging and audit rules

Test-send failures may log only:

- scope type;
- numeric scope ID;
- classified result code.

Do not log SMTP transcripts because they can contain email addresses, IP
addresses, message IDs, infrastructure details, or provider diagnostics.
Audit events use `email_settings.test_send` and store the classified result,
not the recipient or credentials.

## Relevant implementation

- `app/Services/EmailScopeService.php`: hierarchy resolution and mailer creation.
- `app/Services/EmailSettingsService.php`: settings persistence, safe test send,
  and failure classification.
- `app/Controllers/EmailSettingsTrait.php`: authorization, audit event, and API
  response contract.
- `app/Views/partials/_email_settings.php`: settings and test-send UI.
- `app/Libraries/DataEncryption.php`: text-safe encrypted credential envelope.
- `app/Libraries/EmailFailureClassifier.php`: shared transcript-to-code
  classifier; all send paths log/store only the classified code.
- `docs/OBSERVABILITY_POLICY.md`: redaction and retention requirements.
