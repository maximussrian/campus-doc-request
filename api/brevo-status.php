<?php
/**
 * Full Brevo diagnostic. Open in browser: /api/brevo-status.php
 * DELETE after fixing email.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$result = [
    'mail_from'       => MAIL_FROM,
    'api_key_set'     => BREVO_API_KEY !== '',
    'api_key_suffix'  => BREVO_API_KEY !== '' ? ('...' . substr(BREVO_API_KEY, -8)) : '',
    'smtp_key_set'    => BREVO_SMTP_KEY !== '',
    'smtp_user'       => defined('BREVO_SMTP_USER') ? BREVO_SMTP_USER : '',
    'account_check'   => null,
    'send_check'      => null,
    'fix_steps'       => [],
];

if (BREVO_API_KEY === '') {
    $result['fix_steps'][] = 'Add BREVO_API_KEY to .env (xkeysib- key from Brevo → SMTP & API).';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// Step 1: Can we authenticate at all?
$ch = curl_init('https://api.brevo.com/v3/account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . BREVO_API_KEY],
]);
$accountResp = curl_exec($ch);
$accountCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$accountBody = json_decode($accountResp, true);

$result['account_check'] = [
    'http_code' => $accountCode,
    'ok'        => $accountCode === 200,
    'message'   => is_array($accountBody) ? ($accountBody['message'] ?? 'OK') : $accountResp,
];

if ($accountCode === 401) {
    $result['fix_steps'][] = 'Your BREVO_API_KEY is invalid or revoked. In Brevo → SMTP & API → Generate a NEW API key, paste into .env, save, upload to Hostinger.';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

if ($accountCode === 200 && is_array($accountBody)) {
    $result['brevo_email']    = $accountBody['email'] ?? '';
    $result['relay_enabled']  = $accountBody['relay']['enabled'] ?? false;
    $result['smtp_login']     = $accountBody['relay']['data']['userName'] ?? '';
    if ($result['smtp_login'] && (!defined('BREVO_SMTP_USER') || BREVO_SMTP_USER !== $result['smtp_login'])) {
        $result['fix_steps'][] = 'Set BREVO_SMTP_USER=' . $result['smtp_login'] . ' in .env';
    }
}

// Step 2: Is MAIL_FROM a verified sender?
$ch = curl_init('https://api.brevo.com/v3/senders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . BREVO_API_KEY],
]);
$sendersResp = curl_exec($ch);
curl_close($ch);
$sendersBody = json_decode($sendersResp, true);
$verifiedSenders = [];
$mailFromActive    = false;
foreach ($sendersBody['senders'] ?? [] as $sender) {
    $email = strtolower($sender['email'] ?? '');
    $verifiedSenders[] = ['email' => $email, 'active' => (bool) ($sender['active'] ?? false)];
    if ($email === strtolower(MAIL_FROM) && !empty($sender['active'])) {
        $mailFromActive = true;
    }
}
$result['sender_check'] = [
    'ok'               => $mailFromActive,
    'mail_from'        => MAIL_FROM,
    'verified_senders' => $verifiedSenders,
];
if (!$mailFromActive) {
    $result['fix_steps'][] = 'Verify MAIL_FROM (' . MAIL_FROM . ') in Brevo → Senders → Add sender → confirm the verification email. Until then, Brevo logs show Sent then Error.';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// Step 3: Can we send a test email?
require_once __DIR__ . '/brevo_helper.php';
$testTo = MAIL_FROM;
$send = sendBrevoEmail($testTo, 'Test', 'Brevo OTP test', '<p>If you received this, OTP email works.</p>', 'OTP test');
$result['send_check'] = [
    'ok'      => $send['ok'],
    'message' => $send['message'],
    'test_to' => $testTo,
];

if ($send['ok']) {
    $result['fix_steps'][] = 'Brevo send works! OTP emails go to the @evsu.edu.ph address you enter when registering — check that inbox and spam.';
} elseif (stripos($send['message'], 'not yet activated') !== false || stripos($send['message'], 'not active') !== false) {
    $result['fix_steps'][] = 'Email contact@brevo.com — ask them to activate transactional SMTP sending on your account.';
    $result['fix_steps'][] = 'Until then, on localhost the app shows the OTP code on screen (No Email Sent popup).';
} elseif (stripos($send['message'], 'Key not found') !== false) {
    $result['fix_steps'][] = 'Regenerate API key in Brevo and update .env.';
} else {
    $result['fix_steps'][] = $send['message'];
}

echo json_encode($result, JSON_PRETTY_PRINT);
