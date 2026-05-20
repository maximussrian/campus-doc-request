<?php
/**
 * Diagnostic: simulates send-register-otp email step.
 * Run: php scripts/diagnose-otp-email.php your@evsu.edu.ph
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/brevo_helper.php';

$toEmail = $argv[1] ?? MAIL_FROM;

echo "=== OTP Email Diagnostic ===\n";
echo "BREVO_API_KEY ending: ..." . substr(BREVO_API_KEY, -8) . "\n";
echo "BREVO_SMTP_KEY set: " . (BREVO_SMTP_KEY !== '' ? 'yes' : 'no') . "\n";
echo "BREVO_SMTP_USER: " . (defined('BREVO_SMTP_USER') ? BREVO_SMTP_USER : '(not set)') . "\n";
echo "MAIL_FROM: " . MAIL_FROM . "\n";
echo "Send TO: {$toEmail}\n\n";

$result = sendBrevoEmail(
    $toEmail,
    'Test Student',
    'Your EVSU Registration Verification Code',
    '<p>Test OTP: <strong>123456</strong></p>',
    'Test OTP: 123456'
);

echo "sendBrevoEmail ok: " . ($result['ok'] ? 'YES' : 'NO') . "\n";
echo "message: " . $result['message'] . "\n";

if ($result['ok']) {
    echo "\n=> Brevo accepted the send. Check Brevo → Transactional → Logs.\n";
} else {
    echo "\n=> Email was NOT sent. Fix the issue above before testing registration.\n";
}
