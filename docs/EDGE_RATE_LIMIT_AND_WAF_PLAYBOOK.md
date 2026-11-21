# Edge Rate Limit and WAF Playbook

## Purpose

This playbook configures reverse-proxy and CDN/WAF protections for section 2.5 of the deep analysis report.

## 1. Nginx Reverse-Proxy Controls

1. Include [build/nginx/ekklesiaONLINE-rate-limit-http.conf](../build/nginx/ekklesiaONLINE-rate-limit-http.conf) in the `http {}` block (zones and maps are only valid there), then include [build/nginx/ekklesiaONLINE-rate-limit.conf](../build/nginx/ekklesiaONLINE-rate-limit.conf) in the target `server {}` block.
   - The authenticated write quota is keyed on the `X-Authenticated-User` header. Only rely on it behind a trusted auth gateway that overwrites/strips any client-supplied value and is the only path to this listener; otherwise clients can claim the higher quota. The shipped config therefore gives every client the anonymous quota; only with such a gateway, uncomment the `"1" 1;` entry in the `$is_authenticated` map (keep the map and its `default 0;` line, since the write-key maps reference `$is_authenticated`).
   - The login limit counts only `POST /login`; `GET /login` (form and CSRF token) is not throttled at the edge.
2. Reload and validate:
   - `nginx -t`
   - `systemctl reload nginx`
3. Confirm effective limits:
   - Login: `10 requests/minute` per IP, burst `10`
   - Authenticated high-risk writes: `60 requests/minute` per IP, burst `30`
   - Anonymous high-risk writes: `20 requests/minute` per IP, burst `10`
4. Confirm request body limits:
   - `client_max_body_size 12m`
   - `client_body_timeout 15s`

## 2. Cloudflare WAF and Rate-Limit Rules

Create these rules in Cloudflare (Security -> WAF -> Custom Rules / Rate Limiting Rules).

### 2.1 Rule: Repeated 429 Offenders

- Name: `ekklesiaONLINE-429-repeat-offender`
- Expression:
  - `(http.host eq "ekklesiaONLINE.local" and cf.response.1xxx_code eq 429)`
- Action: `Managed Challenge` (or `Block` if abuse persists)
- Threshold: `>= 10` matches in `5 minutes` from same source IP
- Mitigation timeout: `15 minutes`

### 2.2 Rule: Bot Traffic on Write Paths

- Name: `ekklesiaONLINE-bot-write-paths`
- Expression:
  - `(http.host eq "ekklesiaONLINE.local" and http.request.method in {"POST" "PUT" "PATCH" "DELETE"} and cf.bot_management.score lt 30 and http.request.uri.path matches "^/(province/((diocese-admins|events|calendar|event-calendars)/|(email-settings|quick-create-parish))|members/|guests/|followup/|events/|services/|attendance/|donations/|expenses/|budgets/save|volunteers/|families/|pledges/|leadership/|standing/refresh|profile/|guest-conversions/)")`
- Action: `Managed Challenge`

### 2.3 Rule: Absolute Burst Guard

- Name: `ekklesiaONLINE-write-burst-guard`
- Expression:
  - `(http.host eq "ekklesiaONLINE.local" and http.request.method in {"POST" "PUT" "PATCH" "DELETE"} and http.request.uri.path matches "^/(members|guests|followup|events|services|attendance|donations|expenses|volunteers|families|pledges|leadership|guest-conversions|profile)")`
- Threshold: `120 requests/minute` per IP
- Action: `Block`
- Mitigation timeout: `10 minutes`

## 3. Staging Stress Test Procedure

Run from repository root. `BASE_URL` must reach the application without the Nginx edge limits above (e.g. a staging-only origin listener): the probe reloads the login form before every failed login, and edge `login_per_ip`/anonymous write throttling would answer with 503 before the application limiter returns 429 + `Retry-After`. Validate the edge quotas separately.

```bash
chmod +x build/stress_rate_limit_test.sh
BASE_URL=https://staging.ekklesiaONLINE.local \
REQUESTS=50 \
LOGIN_EMAIL=your-staging-admin@example.org \
LOGIN_PASSWORD='your-staging-password' \
TARGET_PATH='province/quick-create-parish' \
sh build/stress_rate_limit_test.sh
```

Expected assertions:

- At least one `429`
- `Retry-After` header present on throttled responses

## 4. Tuning Profile

Use this table after each run:

- If `TOTAL_429 = 0`: decrease burst or base rate in Nginx and app filter.
- If `TOTAL_429 > 70%` for normal admin workflow tests: increase burst by `+5` or rate by `+10 r/m`.
- If 429 appears immediately on first few valid writes: check shared NAT/proxy source IP concentration and move identity to user+IP where possible.
- If bots still pass with low scores: tighten bot score threshold from `< 30` to `< 40` and switch action to `Block` for non-interactive endpoints.

Recommended starting profile:

- Login: `10 r/m`, burst `10`
- Authenticated writes: `60 r/m`, burst `30`
- Anonymous writes: `20 r/m`, burst `10`

## 5. Validation Checklist

- `nginx -t` passes
- Nginx reload succeeds
- App logs include `rate_limit.exceeded`
- Cloudflare dashboard shows triggered rules for synthetic burst tests
- Staging stress test ends with `ASSERTION_OK`
