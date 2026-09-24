<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Echo the current CSRF token back on AJAX responses.
 *
 * Session-based CSRF with `regenerate = true` rotates the token after every
 * successful mutating request, but nothing otherwise exposes the new token
 * to the page — without this header every second fetch()/$.ajax POST from
 * the same page fails verification with "The action you requested is not
 * allowed." Frontends read the header name from the csrf-header meta tag
 * (CsrfFetch / per-view helpers) and update the csrf-token meta with it.
 *
 * Only AJAX requests get the header: full-page POSTs redirect and re-render
 * with a fresh meta token anyway.
 */
class CsrfTokenHeaderFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || ! $request->isAJAX()) {
            return null;
        }

        $response->setHeader(config('Security')->headerName, csrf_hash());

        return null;
    }
}
