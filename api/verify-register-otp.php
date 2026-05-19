<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));
$otp   = preg_replace('/\D/', '', substr(trim($input['otp'] ?? ''), 0, 6));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($otp) !== 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and 6-digit OTP are required']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Ensure otp_attempts column exists (self-migration)
    try {
        $pdo->exec('ALTER TABLE registration_otps ADD COLUMN otp_attempts INT NOT NULL DEFAULT 0');
    } catch (PDOException $e) { /* column may already exist */ }

    // Ensure registration_blocks table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_blocks (
        email VARCHAR(255) PRIMARY KEY,
        blocked_until DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_blocked_until (blocked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $blockEmail = function () use ($pdo, $email) {
        $pdo->prepare('INSERT INTO registration_blocks (email, blocked_until) VALUES (?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))
            ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL 2 MINUTE)')->execute([$email]);
    };

    // Find pending OTP record by email (to check attempt limit before verifying)
    $stmt = $pdo->prepare('SELECT * FROM registration_otps WHERE email = ? AND expires_at > NOW()');
    $stmt->execute([$email]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code. Please request a new one.']);
        exit;
    }

    $attempts = (int) ($record['otp_attempts'] ?? 0);
    if ($attempts > 3) {
        $pdo->prepare('DELETE FROM registration_otps WHERE email = ?')->execute([$email]);
        $blockEmail();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. You are blocked from registering with this email for 2 minutes.',
            'blocked' => true,
            'blocked_minutes' => 2
        ]);
        exit;
    }

    // Verify OTP matches
    if ($record['otp_code'] !== $otp) {
        $pdo->prepare('UPDATE registration_otps SET otp_attempts = otp_attempts + 1 WHERE email = ?')->execute([$email]);
        $newAttempts = $attempts + 1;
        if ($newAttempts > 3) {
            $pdo->prepare('DELETE FROM registration_otps WHERE email = ?')->execute([$email]);
            $blockEmail();
            echo json_encode([
                'success' => false,
                'message' => 'Too many failed attempts. You are blocked from registering with this email for 2 minutes.',
                'blocked' => true,
                'blocked_minutes' => 2
            ]);
        } else {
            $left = 4 - $newAttempts;
            $msg = $left === 1 ? '1 attempt remaining.' : $left . ' attempts remaining.';
            echo json_encode(['success' => false, 'message' => 'Invalid verification code. ' . $msg]);
        }
        http_response_code(400);
        exit;
    }

    // Double-check email/student_number not taken (race condition guard)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE student_number = ? OR email = ?');
    $stmt->execute([$record['student_number'], $email]);
    if ($stmt->fetch()) {
        $pdo->prepare('DELETE FROM registration_otps WHERE email = ?')->execute([$email]);
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This account already exists. Please log in.']);
        exit;
    }

    // Create the user account (program set on first document request)
    $pdo->prepare('INSERT INTO users (student_number, names, surnames, email, password_hash) VALUES (?, ?, ?, ?, ?)')
        ->execute([$record['student_number'], $record['names'], $record['surnames'], $email, $record['password_hash']]);

    // Clean up OTP record
    $pdo->prepare('DELETE FROM registration_otps WHERE email = ?')->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'Account verified and created successfully! You can now log in.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
