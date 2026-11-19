# Content Security Policy (CSP)

CSP is **enabled** (`App::$CSPEnabled = true`) and configured in
`app/Config/ContentSecurityPolicy.php`. The policy is enforced in every
environment (development, testing, production).

## Directive summary

| Directive | Value | Notes |
| --- | --- | --- |
| `default-src` | `'self'` | Fallback for everything else. |
| `script-src` / `script-src-elem` | `'self'` + per-request `'nonce-*'` + `cdn.jsdelivr.net`, `code.jquery.com` | CDN hosts cover Bootstrap, Chart.js, Select2, SweetAlert2, jQuery. |
| `script-src-attr` | `'unsafe-inline'` | **Required**: nonces cannot authorize inline event-handler attributes (`onclick=`, `onsubmit=`, `onchange=`, `onerror=`, `onload=`) still present in legacy views. This only weakens attribute handlers — inline `<script>` blocks remain nonce-gated. Migrate handlers to `addEventListener` and drop this keyword. |
| `style-src` / `style-src-elem` | `'self'` + per-request `'nonce-*'` + `cdn.jsdelivr.net` | CDN host covers Bootstrap and bootstrap-icons CSS. |
| `style-src-attr` | `'unsafe-inline'` | **Required**: `style="..."` attributes are used pervasively and cannot be nonced. `<style>` blocks are nonce-gated. |
| `img-src` | `'self' data:` | `data:` covers data-URI images. |
| `font-src` | `'self' https://cdn.jsdelivr.net` | bootstrap-icons woff/woff2 served from jsDelivr. |
| `connect-src` | `'self'` | XHR/fetch only same-origin. |
| `frame-src` / `child-src` | `'self'` | Covers same-origin/`srcdoc` iframes (e.g. follow-up template preview). |
| `frame-ancestors` | `'self'` | Clickjacking protection. |
| `base-uri`, `form-action` | `'self'` | |
| `object-src` | `'none'` | No plugin content is embedded. |
| `media-src`, `manifest-src`, `worker-src` | `'self'` | |

`upgrade-insecure-requests` stays **off** so local HTTP development
(`http://ekklesiaonline.local`) is unaffected; revisit at production
rollout if the deployment forces HTTPS end-to-end.

## How nonces work here

`$autoNonce = true` — CI4 does **not** scan HTML for `<script>` tags.
`ContentSecurityPolicy::generateNonces()` replaces the placeholder
tokens `{csp-script-nonce}` / `{csp-style-nonce}` in the response body
with a per-request `nonce="..."` attribute during
`Response::send()` → `CSP::finalize()`. Every `<script>`/`<style>` tag in
app and module views carries the placeholder, so inline blocks are
nonce-authorized automatically.

**When adding new inline scripts or `<style>` blocks in views**, include
the placeholder (or `<?= csp_script_nonce() ?>` / `<?= csp_style_nonce() ?>`):

```html
<script {csp-script-nonce}>
    // ...
</script>
```

Without the placeholder the block is rejected by `script-src` in the
browser. External `<script src="...">` tags also carry the placeholder;
new third-party origins must be added explicitly to the matching
directive in `app/Config/ContentSecurityPolicy.php` — do not widen
`default-src`.

`FeatureTestTrait` returns responses before `send()`, so
`tests/ContentSecurityPolicyTest.php` calls
`$response->getCSP()->finalize($response)` to assert the emitted header.
