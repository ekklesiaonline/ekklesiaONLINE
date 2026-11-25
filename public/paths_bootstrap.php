<?php

declare(strict_types=1);

function ekklesiaResolvePathsFile(string $fcPath): string
{
    $appRoot = getenv('EKKLESIA_APP_ROOT') ?: ($_SERVER['EKKLESIA_APP_ROOT'] ?? '');

    if (is_string($appRoot) && $appRoot !== '') {
        return rtrim($appRoot, '/\\') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Paths.php';
    }

    $standardPath = $fcPath . '../app/Config/Paths.php';

    if (is_file($standardPath)) {
        return $standardPath;
    }

    // HostGator split layout: public_html and the private ~/ekklesia app
    // root are siblings.
    return $fcPath . '../ekklesia/app/Config/Paths.php';
}
