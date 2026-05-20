<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/brevo_helper.php';

echo 'MAIL_FROM=' . (MAIL_FROM ?: '(empty)') . PHP_EOL;
echo 'API_KEY set=' . (BREVO_API_KEY !== '' ? 'yes' : 'no') . PHP_EOL;

$result = sendBrevoEmail('test@evsu.edu.ph', 'Test User', 'Test OTP', '<p>Code: 123456</p>');
echo 'ok=' . ($result['ok'] ? 'yes' : 'no') . PHP_EOL;
echo 'message=' . $result['message'] . PHP_EOL;
