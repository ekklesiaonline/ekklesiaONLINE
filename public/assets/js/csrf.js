/**
 * L4: Shared CSRF token management for native fetch() POST calls.
 *
 * Extracted from 32+ inline <script> blocks across module views.
 * This module provides a reusable fetch wrapper that handles:
 * - Reading the CSRF token from the meta tag
 * - Sending the token in the correct header
 * - Refreshing the token from the response header
 * - Sending form-encoded bodies (never JSON.stringify)
 *
 * Usage:
 *   const result = await CsrfFetch.post(url, { field: value });
 *   // result is the parsed JSON response
 *
 * Or lower-level:
 *   const headers = CsrfFetch.headers();
 *   const body = CsrfFetch.body({ field: value });
 */

window.CsrfFetch = (function () {
    'use strict';

    function getMeta() {
        return document.querySelector('meta[name="csrf-token"]');
    }

    function getHeaderName() {
        var headerMeta = document.querySelector('meta[name="csrf-header"]');
        return (headerMeta && headerMeta.content) ? headerMeta.content : 'X-CSRF-TOKEN';
    }

    function getToken() {
        var meta = getMeta();
        return meta ? meta.content : '';
    }

    function refreshToken(response) {
        var headerName = getHeaderName();
        var newToken = response.headers.get(headerName);
        if (newToken) {
            var meta = getMeta();
            if (meta) {
                meta.content = newToken;
            }
        }
    }

    return {
        /**
         * Get the CSRF headers for a fetch call.
         * @returns {Object} Headers object with CSRF token and AJAX header.
         */
        headers: function (extra) {
            var headerName = getHeaderName();
            var token = getToken();
            var headers = {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            };
            if (token) {
                headers[headerName] = token;
            }
            if (extra) {
                for (var key in extra) {
                    if (Object.prototype.hasOwnProperty.call(extra, key)) {
                        headers[key] = extra[key];
                    }
                }
            }
            return headers;
        },

        /**
         * Encode an object as a URL-encoded form body.
         * @param {Object} data Key-value pairs to encode.
         * @returns {string} URL-encoded body string.
         */
        body: function (data) {
            return new URLSearchParams(data || {}).toString();
        },

        /**
         * Make a CSRF-protected POST request.
         * @param {string} url The endpoint URL.
         * @param {Object} data Key-value pairs to send as form-encoded body.
         * @returns {Promise<Object>} Parsed JSON response.
         */
        post: function (url, data) {
            var self = this;
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: self.headers(),
                body: self.body(data)
            })
                .then(function (r) {
                    refreshToken(r);
                    return r.json();
                })
                .catch(function () {
                    if (typeof showAppToast === 'function') {
                        showAppToast('Request failed. Please try again.', 'danger');
                    }
                    return { success: false, message: 'Request failed.' };
                });
        }
    };
})();
