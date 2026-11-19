<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 *
 * Suggested reference for explanations:
 *
 * @see https://www.html5rocks.com/en/tutorials/security/content-security-policy/
 */
class ContentSecurityPolicy extends BaseConfig
{
    // -------------------------------------------------------------------------
    // Broadbrush CSP management
    // -------------------------------------------------------------------------

    /**
     * Default CSP report context
     */
    public bool $reportOnly = false;

    /**
     * Specifies a URL where a browser will send reports
     * when a content security policy is violated.
     */
    public ?string $reportURI = null;

    /**
     * Specifies a reporting endpoint to which violation reports ought to be sent.
     */
    public ?string $reportTo = null;

    /**
     * Instructs user agents to rewrite URL schemes, changing
     * HTTP to HTTPS. This directive is for websites with
     * large numbers of old URLs that need to be rewritten.
     */
    public bool $upgradeInsecureRequests = false;

    // -------------------------------------------------------------------------
    // CSP DIRECTIVES SETTINGS
    // NOTE: once you set a policy to 'none', it cannot be further restricted
    // -------------------------------------------------------------------------

    /**
     * Will default to `'self'` if not overridden
     *
     * @var list<string>|string|null
     */
    public $defaultSrc = 'self';

    /**
     * Lists allowed scripts' URLs.
     *
     * External script origins actually used by the views:
     * cdn.jsdelivr.net (Bootstrap, Chart.js, Select2, SweetAlert2) and
     * code.jquery.com (jQuery). Inline <script> blocks are authorized
     * via the per-request nonce (autoNonce + {csp-script-nonce} tags).
     *
     * @var list<string>|string
     */
    public $scriptSrc = ['self', 'https://cdn.jsdelivr.net', 'https://code.jquery.com'];

    /**
     * Specifies valid sources for JavaScript <script> elements.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcElem = ['self', 'https://cdn.jsdelivr.net', 'https://code.jquery.com'];

    /**
     * Specifies valid sources for JavaScript inline event
     * handlers and JavaScript URLs.
     *
     * NOTE: 'unsafe-inline' is required here — nonces cannot authorize
     * inline event-handler attributes (onclick=, onsubmit=, onchange=,
     * onerror=, onload=), which legacy views still use. Nonce-based
     * blocking of inline <script> blocks is unaffected because this
     * directive only governs attributes. Migrate handlers to
     * addEventListener over time and drop this keyword.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcAttr = 'unsafe-inline';

    /**
     * Lists allowed stylesheets' URLs.
     *
     * @var list<string>|string
     */
    public $styleSrc = ['self', 'https://cdn.jsdelivr.net'];

    /**
     * Specifies valid sources for stylesheets <link> elements.
     *
     * @var list<string>|string
     */
    public array|string $styleSrcElem = ['self', 'https://cdn.jsdelivr.net'];

    /**
     * Specifies valid sources for stylesheets inline
     * style attributes and `<style>` elements.
     *
     * NOTE: 'unsafe-inline' is required here — nonces cannot authorize
     * style="" attributes, which are used pervasively in views.
     * <style> blocks themselves are authorized by nonce instead.
     *
     * @var list<string>|string
     */
    public array|string $styleSrcAttr = 'unsafe-inline';

    /**
     * Defines the origins from which images can be loaded.
     *
     * 'data:' covers inline/data-URI images (avatars, signatures,
     * generated charts) rendered into <img> or CSS.
     *
     * @var list<string>|string
     */
    public $imageSrc = ['self', 'data:'];

    /**
     * Restricts the URLs that can appear in a page's `<base>` element.
     *
     * Will default to self if not overridden
     *
     * @var list<string>|string|null
     */
    public $baseURI = 'self';

    /**
     * Lists the URLs for workers and embedded frame contents
     *
     * @var list<string>|string
     */
    public $childSrc = 'self';

    /**
     * Limits the origins that you can connect to (via XHR,
     * WebSockets, and EventSource).
     *
     * @var list<string>|string
     */
    public $connectSrc = 'self';

    /**
     * Specifies the origins that can serve web fonts.
     *
     * cdn.jsdelivr.net serves the bootstrap-icons woff/woff2 files
     * referenced by the CDN stylesheet.
     *
     * @var list<string>|string
     */
    public $fontSrc = ['self', 'https://cdn.jsdelivr.net'];

    /**
     * Lists valid endpoints for submission from `<form>` tags.
     *
     * @var list<string>|string
     */
    public $formAction = 'self';

    /**
     * Specifies the sources that can embed the current page.
     * This directive applies to `<frame>`, `<iframe>`, `<embed>`,
     * and `<applet>` tags. This directive can't be used in
     * `<meta>` tags and applies only to non-HTML resources.
     *
     * @var list<string>|string|null
     */
    public $frameAncestors = 'self';

    /**
     * The frame-src directive restricts the URLs which may
     * be loaded into nested browsing contexts.
     *
     * 'self' covers same-origin iframes and srcdoc previews
     * (e.g. follow-up template preview).
     *
     * @var list<string>|string|null
     */
    public $frameSrc = 'self';

    /**
     * Restricts the origins allowed to deliver video and audio.
     *
     * @var list<string>|string|null
     */
    public $mediaSrc = 'self';

    /**
     * Allows control over Flash and other plugins.
     *
     * The app does not embed plugin content.
     *
     * @var list<string>|string
     */
    public $objectSrc = 'none';

    /**
     * @var list<string>|string|null
     */
    public $manifestSrc = 'self';

    /**
     * @var list<string>|string
     */
    public array|string $workerSrc = 'self';

    /**
     * Limits the kinds of plugins a page may invoke.
     *
     * @var list<string>|string|null
     */
    public $pluginTypes;

    /**
     * List of actions allowed.
     *
     * @var list<string>|string|null
     */
    public $sandbox;

    /**
     * Nonce placeholder for style tags.
     */
    public string $styleNonceTag = '{csp-style-nonce}';

    /**
     * Nonce placeholder for script tags.
     */
    public string $scriptNonceTag = '{csp-script-nonce}';

    /**
     * Replace nonce tag automatically?
     */
    public bool $autoNonce = true;
}
