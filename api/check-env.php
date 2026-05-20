<?php
/**
 * One-time Hostinger diagnostic — DELETE this file after testing.
 * Open: https://your-site.com/api/check-env.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

$envPath = realpath(__DIR__ . '/../.env');
$key     = BREVO_API_KEY;
$masked  = $key === '' ? '(empty)' : (substr($key, 0, 8) . '...' . substr($key, -4));

echo json_encode([
    'env_file_exists'  => $envPath && file_exists($envPath),
    'env_file_path'    => $envPath ?: 'not found',
    'brevo_key_set'    => $key !== '',
    'brevo_key_masked' => $masked,
    'mail_from'        => MAIL_FROM ?: '(empty)',
    'mail_from_valid'  => (bool) filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL),
    'base_url'         => BASE_URL ?: '(empty)',
], JSON_PRETTY_PRINT);
