<?php
/**
 * One-time: test Brevo from Hostinger. DELETE after debugging.
 * Open: /api/brevo-ping.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/brevo_helper.php';

$result = sendBrevoEmail(
    MAIL_FROM,
    'Test',
    'Brevo ping from server',
    '<p>If you received this, Brevo works from your hosting.</p>',
    'Brevo ping from server.'
);

echo json_encode([
    'mail_from' => MAIL_FROM,
    'ok'        => $result['ok'],
    'message'   => $result['message'],
    'host'      => $_SERVER['HTTP_HOST'] ?? '',
], JSON_PRETTY_PRINT);
