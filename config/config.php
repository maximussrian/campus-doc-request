<?php

// Load .env (secrets) — never commit .env; use .env.example as template
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $raw = file_get_contents($envFile);
    if ($raw !== false && str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3); // UTF-8 BOM (common when editing in Notepad)
    }
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\0\x0B\"'");
        if (($p = strpos($val, '#')) !== false) {
            $val = trim(substr($val, 0, $p));
        }
        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}

/** Prefer string values from .env ($_ENV); ignore polluted Windows getenv(). */
function env_str(string $key, string $default = ''): string
{
    if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
        return trim($_ENV[$key]);
    }
    $g = getenv($key);
    if (is_string($g) && $g !== '') {
        return trim($g);
    }
    return $default;
}

define('DB_HOST', env_str('DB_HOST', 'localhost'));
require_once __DIR__ . '/../api/session_init.php';
define('DB_NAME', env_str('DB_NAME', 'docu_request'));
define('DB_USER', env_str('DB_USER', 'root'));
define('DB_PASS', env_str('DB_PASS', ''));

define('BASE_URL', env_str('BASE_URL'));
/** Subfolder when hosted under /docu_request (XAMPP). Leave empty on Hostinger root. */
define('APP_BASE', rtrim(env_str('APP_BASE'), '/'));
define('DB_PORT', env_str('DB_PORT', '3306'));

require_once __DIR__ . '/url_helper.php';

define('BREVO_API_KEY', env_str('BREVO_API_KEY'));
define('BREVO_SMTP_KEY', env_str('BREVO_SMTP_KEY'));
define('BREVO_SMTP_USER', env_str('BREVO_SMTP_USER', env_str('MAIL_FROM')));
define('MAIL_FROM', env_str('MAIL_FROM', env_str('DEV_SUPPORT_EMAIL')));
define('MAIL_FROM_NAME', env_str('MAIL_FROM_NAME', 'Document Request'));

define('DEV_SUPPORT_EMAIL', env_str('DEV_SUPPORT_EMAIL'));
define('DEV_SUPPORT_PHONE', env_str('DEV_SUPPORT_PHONE'));

define('SESSION_TIMEOUT_MINUTES', (int) env_str('SESSION_TIMEOUT_MINUTES', '0'));
define('LOGIN_MAX_ATTEMPTS', (int) env_str('LOGIN_MAX_ATTEMPTS', '5'));
define('LOGIN_LOCKOUT_MINUTES', (int) env_str('LOGIN_LOCKOUT_MINUTES', '15'));

define('DAILY_REQUEST_LIMIT', (int) env_str('DAILY_REQUEST_LIMIT', '50'));
