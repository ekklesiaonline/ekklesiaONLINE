<?php

// PHPStan stubs for CodeIgniter 4 helper functions.
// These functions are defined by CI4 at runtime but PHPStan can't discover them.

namespace {
    // CI4 bootstraps these path constants at runtime.
    const ROOTPATH = __DIR__ . '/';
    const APPPATH = __DIR__ . '/app/';
    const SYSTEMPATH = __DIR__ . '/vendor/codeigniter4/framework/system/';
    const WRITEPATH = __DIR__ . '/writable/';
    const SUPPORTPATH = __DIR__ . '/tests/_support/';
    const TESTPATH = __DIR__ . '/tests/';
    const FCPATH = __DIR__ . '/public/';

    function db_connect(?string $group = null): \CodeIgniter\Database\BaseConnection
    {
    }
    function model(string $name, bool $getShared = true, bool &$conn = null): object
    {
    }
    function service(string $name, bool $getShared = true): object
    {
    }
    function auth(): \CodeIgniter\Shield\Auth
    {
    }
    function config(string $name): object
    {
    }
    function lang(string $line, array $args = [], ?string $locale = null): string|array
    {
    }
    function esc($value, ?string $context = null, ?string $encoding = null): string
    {
    }
    function base_url(string $uri = ''): string
    {
    }
    function csrf_hash(): string
    {
    }
    function csrf_field(): string
    {
    }
    function csrf_token(): string
    {
    }
    function csrf_header(): string
    {
    }
    function env(?string $key = null, $default = null): mixed
    {
    }
    function redirect(): \CodeIgniter\HTTP\RedirectResponse
    {
    }
    function view(string $name, array $data = [], array $options = []): string
    {
    }
    function session(): \CodeIgniter\Session\Session
    {
    }
    function old(string $key, $default = null): mixed
    {
    }
    function route_to(string $method, ...$params): string
    {
    }
    function site_url(string $uri = ''): string
    {
    }
    function current_url(): string
    {
    }
    function previous_url(): string
    {
    }
    function timer(?string $name = null): \CodeIgniter\Debug\Timer
    {
    }
    function log_message(string $level, string $message, array $context = []): void
    {
    }
    function helper(array|string $filename): void
    {
    }
    function emailer(bool $getShared = true): object
    {
    }
    function setting(?string $key = null, $value = null): mixed
    {
    }
    function cache(?string $key = null, $value = null): object
    {
    }
    function cookies(): \CodeIgniter\HTTP\CookieStore
    {
    }
    function request(): \CodeIgniter\HTTP\IncomingRequest
    {
    }
    function response(): \CodeIgniter\HTTP\Response
    {
    }
    function single_service(string $name, array $params = []): object
    {
    }
    function trace(): void
    {
    }
    function app_timezone(): string
    {
    }
    function csp(): object
    {
    }
    function htmlext(array $options = []): string
    {
    }
    function force_https(int $duration = 31536000, ?\CodeIgniter\HTTP\RequestInterface $request = null, ?\CodeIgniter\HTTP\ResponseInterface $response = null): void
    {
    }
    function app_cli_route(string $from, array $to = []): void
    {
    }
}
