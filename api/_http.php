<?php
/**
 * Reliable request method detection (Hostinger/LiteSpeed redirect quirks).
 */
function api_request_method(): string
{
    $method = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? '')));

    if ($method === 'POST' || $method === 'OPTIONS') {
        return $method;
    }

    if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        return strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
    }

    // Some hosts report GET after an HTTP→HTTPS redirect even when the client sent JSON POST
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $ct  = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if ($len > 0 && stripos($ct, 'application/json') !== false) {
        return 'POST';
    }

    return $method;
}
