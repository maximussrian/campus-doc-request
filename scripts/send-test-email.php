<?php
/** Run: php scripts/send-test-email.php your@email.com */
$to = $argv[1] ?? '';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/brevo_helper.php';

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send-test-email.php your@email.com\n");
    exit(1);
}

echo "MAIL_FROM=" . MAIL_FROM . "\n";
echo "KEY_LEN=" . strlen(BREVO_API_KEY) . "\n";

$r = sendBrevoEmail($to, 'Test', 'EVSU OTP test', '<p>Test code: <strong>123456</strong></p>', 'Test code: 123456');
echo "ok=" . ($r['ok'] ? 'yes' : 'no') . "\n";
echo "message=" . $r['message'] . "\n";
