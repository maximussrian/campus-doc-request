<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Ensure OTP-enabled schema exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        otp_code VARCHAR(6) NULL,
        token VARCHAR(64) NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_email (user_id),
        INDEX idx_token (token),
        INDEX idx_otp (user_id, otp_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN otp_code VARCHAR(6) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE password_reset_tokens MODIFY token VARCHAR(64) NULL"); } catch (PDOException $e) {}

    $stmt = $pdo->prepare('SELECT id, names FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$user['id']]);
        // Use MySQL NOW() so expiry matches verify-otp check (avoids timezone mismatch)
        $pdo->prepare('INSERT INTO password_reset_tokens (user_id, otp_code, token, expires_at) VALUES (?, ?, NULL, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')
            ->execute([$user['id'], $otp]);

        // Send OTP via Brevo
        if (!empty(BREVO_API_KEY)) {
            $payload = [
                'sender' => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
                'to' => [['email' => $email, 'name' => $user['names']]],
                'subject' => 'Your password reset OTP',
                'htmlContent' => '<p>Hi ' . htmlspecialchars($user['names']) . ',</p>'
                    . '<p>Your verification code is: <strong style="font-size:24px;letter-spacing:4px;">' . $otp . '</strong></p>'
                    . '<p>Enter this code on the reset page. It expires in 10 minutes.</p>'
                    . '<p>If you did not request this, you can ignore this email.</p>',
                'textContent' => "Hi {$user['names']},\n\nYour verification code is: {$otp}\n\nEnter this code on the reset page. It expires in 10 minutes.\n\nIf you did not request this, you can ignore this email."
            ];

            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'accept: application/json',
                    'api-key: ' . BREVO_API_KEY,
                    'content-type: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                throw new \Exception('Brevo request failed: ' . $err);
            }
            $result = json_decode($response, true);
            if ($httpCode >= 400) {
                $msg = $result['message'] ?? 'Brevo API error';
                throw new \Exception('Brevo: ' . $msg);
            }
        } else {
            // Fallback: return OTP for testing (no Brevo)
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent (test mode): ' . $otp,
                'verify_url' => 'verify-otp.php?email=' . urlencode($email)
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Check your email for the verification code.',
            'verify_url' => 'verify-otp.php?email=' . urlencode($email)
        ]);
    } else {
        echo json_encode(['success' => true, 'message' => 'If that email is registered, you will receive a verification code shortly.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
