<?php

// Load .env (secrets) — never commit .env; use .env.example as template
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
require_once __DIR__ . '/../api/session_init.php';
define('DB_NAME', getenv('DB_NAME') ?: 'docu_request');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('BASE_URL', getenv('BASE_URL') ?: '');
/** Subfolder when hosted under /docu_request (XAMPP). Leave empty on Render. */
define('APP_BASE', rtrim(getenv('APP_BASE') ?: '', '/'));
define('DB_PORT', getenv('DB_PORT') ?: '3306');

require_once __DIR__ . '/url_helper.php';

define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: '');
define('BREVO_SMTP_KEY', getenv('BREVO_SMTP_KEY') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Document Request');

define('DEV_SUPPORT_EMAIL', getenv('DEV_SUPPORT_EMAIL') ?: '');
define('DEV_SUPPORT_PHONE', getenv('DEV_SUPPORT_PHONE') ?: '');

define('SESSION_TIMEOUT_MINUTES', (int)(getenv('SESSION_TIMEOUT_MINUTES') ?: 0));
define('LOGIN_MAX_ATTEMPTS', (int)(getenv('LOGIN_MAX_ATTEMPTS') ?: 5));
define('LOGIN_LOCKOUT_MINUTES', (int)(getenv('LOGIN_LOCKOUT_MINUTES') ?: 15));

define('DAILY_REQUEST_LIMIT', (int)(getenv('DAILY_REQUEST_LIMIT') ?: 50));
