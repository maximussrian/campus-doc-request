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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));
$otp   = preg_replace('/\D/', '', substr(trim($input['otp'] ?? ''), 0, 6));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($otp) !== 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email and 6-digit OTP are required']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Ensure otp_attempts column exists (self-migration)
    try {
        $pdo->exec('ALTER TABLE password_reset_tokens ADD COLUMN otp_attempts INT NOT NULL DEFAULT 0');
    } catch (PDOException $e) { /* column may already exist */ }

    // Find pending OTP record by email (to check attempt limit and verify)
    $stmt = $pdo->prepare('SELECT u.id, prt.id AS prt_id, prt.otp_code, prt.otp_attempts
        FROM users u
        JOIN password_reset_tokens prt ON prt.user_id = u.id
        WHERE LOWER(u.email) = ? AND prt.otp_code IS NOT NULL AND prt.expires_at > NOW()');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please request a new code from the forgot password page.']);
        exit;
    }

    $attempts = (int) ($row['otp_attempts'] ?? 0);
    if ($attempts > 3) {
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE id = ?')->execute([$row['prt_id']]);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new verification code from the forgot password page.']);
        exit;
    }

    if ($row['otp_code'] !== $otp) {
        $pdo->prepare('UPDATE password_reset_tokens SET otp_attempts = otp_attempts + 1 WHERE id = ?')->execute([$row['prt_id']]);
        $newAttempts = $attempts + 1;
        if ($newAttempts > 3) {
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE id = ?')->execute([$row['prt_id']]);
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new verification code from the forgot password page.']);
        } else {
            $left = 4 - $newAttempts;
            $msg = $left === 1 ? '1 attempt remaining.' : $left . ' attempts remaining.';
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. ' . $msg]);
        }
        http_response_code(400);
        exit;
    }

    $token = bin2hex(random_bytes(32));

    // Use MySQL NOW() for expiry so reset-password check matches (no timezone mismatch)
    $pdo->prepare('UPDATE password_reset_tokens SET otp_code = NULL, token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')
        ->execute([$token, $row['prt_id']]);

    $resetLink = site_url('reset-password.php?token=' . $token);

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified. Proceed to reset your password.',
        'reset_link' => $resetLink
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
