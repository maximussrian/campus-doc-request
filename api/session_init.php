<?php
/**
 * Session bootstrap — 30-day cookie, cookie path scoped to app for reliability.
 * Uses separate sessions so developer, registrar, and teller can stay logged in without overwriting each other.
 */
if (session_status() === PHP_SESSION_NONE) {
    $referer = strtolower($_SERVER['HTTP_REFERER'] ?? '');
    $realmHeader = strtolower(trim($_SERVER['HTTP_X_SESSION_REALM'] ?? ''));
    $uri = strtolower($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '');

    $refererIsDev = strpos($referer, 'developer-dashboard') !== false || strpos($referer, 'developer-login') !== false;
    $refererIsTeller = strpos($referer, 'teller-dashboard') !== false || strpos($referer, 'teller-login') !== false;
    $refererIsRegistrar = strpos($referer, 'admin-dashboard') !== false || strpos($referer, 'admin-login') !== false || strpos($referer, 'admin-activate') !== false;

    $uriIsDev = strpos($uri, 'developer-dashboard') !== false || strpos($uri, 'developer-login') !== false;
    $uriIsTeller = strpos($uri, 'teller-dashboard') !== false || strpos($uri, 'teller-login') !== false || strpos($uri, '/api/teller-') !== false;
    $uriIsRegistrar = strpos($uri, 'admin-dashboard') !== false || strpos($uri, 'admin-login') !== false || strpos($uri, 'admin-activate') !== false;

    // Resolve explicit realm first, then prefer the referer over ambiguous API filenames.
    if ($realmHeader === 'developer') {
        session_name('DEV_SESS');
    } elseif ($realmHeader === 'teller') {
        session_name('TELLER_SESS');
    } elseif ($realmHeader === 'registrar') {
        session_name('PHPSESSID');
    } elseif ($refererIsDev) {
        session_name('DEV_SESS');
    } elseif ($refererIsTeller) {
        session_name('TELLER_SESS');
    } elseif ($refererIsRegistrar) {
        session_name('PHPSESSID');
    } elseif ($uriIsDev) {
        session_name('DEV_SESS');
    } elseif ($uriIsTeller && !$uriIsRegistrar) {
        session_name('TELLER_SESS');
    } else {
        session_name('PHPSESSID'); // Registrar/admin by default
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $lifetime = 2592000; // 30 days
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, '/', '', $secure, true);
    }
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '10000');
    $sessDir = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessDir)) @mkdir($sessDir, 0700, true);
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
}
