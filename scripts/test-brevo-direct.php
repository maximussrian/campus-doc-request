<?php
/** Test Brevo using ONLY .env file (no Windows getenv pollution). */
$envFile = __DIR__ . '/../.env';
$vars = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $vars[trim($k)] = trim($v, " \t\"'");
}
$key  = $vars['BREVO_API_KEY'] ?? '';
$from = $vars['MAIL_FROM'] ?? '';
echo 'MAIL_FROM=' . $from . PHP_EOL;
echo 'KEY_LEN=' . strlen($key) . PHP_EOL;
echo 'KEY_START=' . substr($key, 0, 10) . PHP_EOL;
echo 'KEY_END=' . substr($key, -6) . PHP_EOL;

$payload = json_encode([
    'sender' => ['name' => 'Document Request', 'email' => $from],
    'to' => [['email' => 'test@evsu.edu.ph', 'name' => 'Test']],
    'subject' => 'API test',
    'htmlContent' => '<p>Test</p>',
]);

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'accept: application/json',
        'api-key: ' . $key,
        'content-type: application/json',
    ],
]);
$response = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo 'HTTP_CODE=' . $code . PHP_EOL;
$body = json_decode($response, true);
echo 'BREVO_MSG=' . ($body['message'] ?? $response) . PHP_EOL;
