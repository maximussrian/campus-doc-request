<?php
/**
 * Send transactional email via Brevo API, with SMTP relay fallback.
 *
 * @return array{ok: bool, message: string}
 */
function sendBrevoEmail(string $toEmail, string $toName, string $subject, string $htmlContent, ?string $textContent = null): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Email service unavailable (cURL not enabled on server). Contact hosting support.'];
    }

    if (empty(BREVO_API_KEY) && empty(BREVO_SMTP_KEY)) {
        return ['ok' => false, 'message' => 'Email not configured (missing BREVO_API_KEY or BREVO_SMTP_KEY in .env).'];
    }

    if (empty(MAIL_FROM) || !filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Add MAIL_FROM=your-verified@email.com to .env (must match a verified sender in Brevo).'];
    }

    if ($textContent === null || $textContent === '') {
        $textContent = strip_tags(preg_replace('/\s+/', ' ', $htmlContent));
    }

    if (!empty(BREVO_API_KEY)) {
        if (!str_starts_with(BREVO_API_KEY, 'xkeysib-')) {
            return ['ok' => false, 'message' => 'BREVO_API_KEY must start with xkeysib- (API key). Do not use xsmtpsib- SMTP key here.'];
        }

        $apiResult = sendBrevoEmailViaApi($toEmail, $toName, $subject, $htmlContent, $textContent);
        if ($apiResult['ok']) {
            return $apiResult;
        }

        // Try SMTP when API is blocked by IP/auth but relay credentials may still work
        if (!empty(BREVO_SMTP_KEY) && shouldTryBrevoSmtpFallback($apiResult['http_code'] ?? 0, $apiResult['message'])) {
            $smtpResult = sendBrevoEmailViaSmtp($toEmail, $toName, $subject, $htmlContent, $textContent);
            if ($smtpResult['ok']) {
                return $smtpResult;
            }
            return ['ok' => false, 'message' => $apiResult['message'] . ' SMTP fallback also failed: ' . $smtpResult['message']];
        }

        return ['ok' => false, 'message' => $apiResult['message']];
    }

    return sendBrevoEmailViaSmtp($toEmail, $toName, $subject, $htmlContent, $textContent);
}

/** @return array{ok: bool, message: string, http_code?: int} */
function sendBrevoEmailViaApi(string $toEmail, string $toName, string $subject, string $htmlContent, string $textContent): array
{
    $payload = [
        'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $htmlContent,
        'textContent' => $textContent,
        'replyTo'     => ['email' => MAIL_FROM, 'name' => MAIL_FROM_NAME],
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr !== '') {
        return ['ok' => false, 'message' => 'Could not reach email service. Check server network or try again later.', 'http_code' => 0];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'message' => 'Sent', 'http_code' => $httpCode];
    }

    return ['ok' => false, 'message' => brevoApiErrorMessage($httpCode, $response), 'http_code' => $httpCode];
}

/** @return array{ok: bool, message: string} */
function sendBrevoEmailViaSmtp(string $toEmail, string $toName, string $subject, string $htmlContent, string $textContent): array
{
    if (empty(BREVO_SMTP_KEY)) {
        return ['ok' => false, 'message' => 'SMTP not configured (missing BREVO_SMTP_KEY in .env).'];
    }
    if (!str_starts_with(BREVO_SMTP_KEY, 'xsmtpsib-')) {
        return ['ok' => false, 'message' => 'BREVO_SMTP_KEY must start with xsmtpsib-.'];
    }

    $smtpUser = defined('BREVO_SMTP_USER') ? BREVO_SMTP_USER : MAIL_FROM;
    if ($smtpUser === '') {
        return ['ok' => false, 'message' => 'Set BREVO_SMTP_USER in .env (find it in Brevo → SMTP & API → SMTP tab).'];
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $boundary       = 'brevo_' . bin2hex(random_bytes(8));
    $body           = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
        . $textContent . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
        . $htmlContent . "\r\n"
        . "--{$boundary}--";

    $message = implode("\r\n", [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'To: ' . ($toName !== '' ? "{$toName} <{$toEmail}>" : $toEmail),
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        '',
        $body,
    ]);

    $ch = curl_init('smtp://smtp-relay.brevo.com:587');
    curl_setopt_array($ch, [
        CURLOPT_MAIL_FROM     => MAIL_FROM,
        CURLOPT_MAIL_RCPT     => [$toEmail],
        CURLOPT_USERNAME      => $smtpUser,
        CURLOPT_PASSWORD      => BREVO_SMTP_KEY,
        CURLOPT_USE_SSL       => CURLUSESSL_ALL,
        CURLOPT_UPLOAD        => true,
        CURLOPT_READDATA      => fopen('data://text/plain,' . rawurlencode($message), 'r'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT       => 30,
    ]);

    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        return ['ok' => false, 'message' => 'SMTP connection failed. Check BREVO_SMTP_USER and BREVO_SMTP_KEY in .env.'];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'message' => 'Sent via SMTP'];
    }
    if ($httpCode === 535 || $httpCode === 534) {
        return ['ok' => false, 'message' => 'Brevo SMTP login failed. In Brevo → SMTP & API → SMTP, copy the SMTP login into BREVO_SMTP_USER and the SMTP key into BREVO_SMTP_KEY.'];
    }
    if ($httpCode === 502 || $httpCode === 403) {
        return ['ok' => false, 'message' => 'Brevo SMTP relay is not active yet. Email contact@brevo.com to activate transactional sending on your account.'];
    }

    return ['ok' => false, 'message' => 'SMTP send failed (code ' . $httpCode . '). Verify Brevo SMTP settings in .env.'];
}

function shouldTryBrevoSmtpFallback(int $httpCode, string $message): bool
{
    // API key may be wrong/revoked while SMTP credentials are still valid
    if ($httpCode === 401) {
        return true;
    }
    // Some accounts can use SMTP relay after API returns activation errors
    if ($httpCode === 403 && stripos($message, 'not yet activated') !== false) {
        return true;
    }
    return false;
}

function brevoApiErrorMessage(int $httpCode, string $response): string
{
    $body   = json_decode($response, true);
    $apiMsg = is_array($body) ? ($body['message'] ?? '') : '';

    if ($httpCode === 401) {
        if (stripos($apiMsg, 'IP address') !== false || stripos($apiMsg, 'authorised_ips') !== false || stripos($apiMsg, 'unrecognised IP') !== false) {
            return 'Brevo blocked this server IP. Open Brevo → Security → Authorized IPs → turn OFF restriction (or add your hosting IP).';
        }
        if (stripos($apiMsg, 'Key not found') !== false) {
            return 'Brevo API key not found. Generate a new xkeysib- key in Brevo → SMTP & API, then paste it into .env as BREVO_API_KEY.';
        }
        return 'Brevo auth failed (401). Regenerate the API key in Brevo and update BREVO_API_KEY in .env.';
    }
    if ($httpCode === 400 && (stripos($apiMsg, 'sender') !== false || stripos($apiMsg, 'not valid') !== false)) {
        return 'Sender not verified in Brevo. Go to Brevo → Senders → add and verify MAIL_FROM (' . MAIL_FROM . '). Check your inbox for the Brevo verification link.';
    }
    if ($httpCode === 403 && stripos($apiMsg, 'not yet activated') !== false) {
        return 'Brevo cannot send email yet — your account needs activation. In Brevo, finish sender verification, then email contact@brevo.com and ask them to activate transactional SMTP sending.';
    }

    return 'Failed to send verification email.' . ($apiMsg !== '' ? ' (' . $apiMsg . ')' : '');
}
