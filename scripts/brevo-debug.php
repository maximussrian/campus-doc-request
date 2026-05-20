<?php
require_once __DIR__ . '/../config/config.php';
$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'sender'      => ['name' => 'Test', 'email' => MAIL_FROM],
        'to'          => [['email' => MAIL_FROM]],
        'subject'     => 'Test',
        'htmlContent' => '<p>Test</p>',
        'textContent' => 'Test',
    ]),
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . BREVO_API_KEY,
        'content-type: application/json',
    ],
]);
$response = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $code\n$response\n";
