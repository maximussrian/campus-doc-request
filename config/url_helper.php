<?php

/**
 * Build app-relative paths and full URLs (local /docu_request vs production root).
 */
function app_base_path(): string
{
    return defined('APP_BASE') ? APP_BASE : '';
}

function app_path(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');
    if ($base === '' && $path === '') {
        return '';
    }
    if ($base === '') {
        return '/' . $path;
    }
    return $path === '' ? $base : $base . '/' . $path;
}

function site_url(string $path = ''): string
{
    if (defined('BASE_URL') && BASE_URL !== '') {
        $origin = rtrim(BASE_URL, '/');
    } else {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $origin = ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $origin . app_path($path);
}

function getBaseUrl(): string
{
    return site_url();
}
