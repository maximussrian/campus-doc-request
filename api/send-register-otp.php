<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/validation_helper.php';

define('ALLOWED_EMAIL_DOMAIN', 'evsu.edu.ph');
define('STUDENT_NUMBER_PATTERN', '/^\d{4}-\d{5}$/');

$input          = json_decode(file_get_contents('php://input'), true) ?? [];
$student_number = sanitizeText($input['student_number'] ?? '', 50);
$names          = sanitizeText($input['names'] ?? '', MAX_NAMES_LENGTH);
$surnames       = sanitizeText($input['surnames'] ?? '', MAX_NAMES_LENGTH);
$email          = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));
$password       = $input['password'] ?? '';

// Validate
$errors = [];
if (empty($student_number))
    $errors[] = 'Student number is required';
elseif (!preg_match(STUDENT_NUMBER_PATTERN, $student_number))
    $errors[] = 'Student number must follow the format YYYY-NNNNN (e.g. 2022-32222)';
if (empty($names))    $errors[] = 'First name is required';
if (empty($surnames)) $errors[] = 'Last name is required';
if (empty($email))
    $errors[] = 'Email is required';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'Invalid email format';
elseif (!str_ends_with($email, '@' . ALLOWED_EMAIL_DOMAIN))
    $errors[] = 'Only university email addresses are allowed (@' . ALLOWED_EMAIL_DOMAIN . ')';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Check if email is temporarily blocked (3 failed OTP attempts)
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_blocks (
        email VARCHAR(255) PRIMARY KEY,
        blocked_until DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_blocked_until (blocked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec('DELETE FROM registration_blocks WHERE blocked_until < NOW()');
    $stmt = $pdo->prepare('SELECT blocked_until FROM registration_blocks WHERE email = ? AND blocked_until > NOW()');
    $stmt->execute([$email]);
    $block = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($block) {
        $stmt2 = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) AS wait_secs FROM registration_blocks WHERE email = ? AND blocked_until > NOW()');
        $stmt2->execute([$email]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        $waitSecs = $row ? max(1, (int) $row['wait_secs']) : 120;
        $mins = (int) ceil($waitSecs / 60);
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Try again in ' . $mins . ' minute' . ($mins !== 1 ? 's' : '') . '.']);
        exit;
    }

    // Ensure registration_otps table exists and has department/course
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_number VARCHAR(50) NOT NULL,
        names VARCHAR(100) NOT NULL,
        surnames VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        otp_code VARCHAR(6) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        otp_attempts INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_otp (email, otp_code),
        INDEX idx_email_created (email, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE registration_otps ADD COLUMN department VARCHAR(255) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE registration_otps ADD COLUMN course VARCHAR(255) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE registration_otps ADD COLUMN otp_attempts INT NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE registration_otps ADD COLUMN sent_at DATETIME NULL"); } catch (PDOException $e) {}

    // Remove orphan rows (e.g. from older versions that saved before email was sent)
    $pdo->prepare('DELETE FROM registration_otps WHERE email = ? AND sent_at IS NULL')->execute([$email]);

    // Check if already registered
    $stmt = $pdo->prepare('SELECT id FROM users WHERE student_number = ? OR email = ?');
    $stmt->execute([$student_number, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Student number or email is already registered']);
        exit;
    }

    // Cooldown: only after a code was actually emailed (max 1 per 2 min per email)
    $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(sent_at, INTERVAL 2 MINUTE)) AS wait_secs
        FROM registration_otps WHERE email = ? AND sent_at IS NOT NULL AND sent_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY sent_at DESC LIMIT 1');
    $stmt->execute([$email]);
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($recent && (int) $recent['wait_secs'] > 0) {
        $waitSecs = (int) $recent['wait_secs'];
        $mins = max(1, (int) ceil($waitSecs / 60));
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'A code was already sent to your email. Check your inbox or wait ' . $mins . ' minute' . ($mins !== 1 ? 's' : '') . ' before requesting another.']);
        exit;
    }

    // Generate OTP
    $otp          = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Send via Brevo
    $fullName    = $names . ' ' . $surnames;
    $year        = date('Y');
    $htmlContent = <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
        <tr><td align="center">
          <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">
            <tr>
              <td style="background:#DD0426;padding:26px 32px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:20px;">Eastern Visayas State University</h1>
                <p style="color:rgba(255,255,255,.85);margin:5px 0 0;font-size:13px;">Document Request System – Email Verification</p>
              </td>
            </tr>
            <tr>
              <td style="padding:32px;">
                <p style="font-size:15px;color:#333;margin:0 0 10px;">Hi <strong>{$fullName}</strong>,</p>
                <p style="font-size:14px;color:#555;line-height:1.7;margin:0 0 24px;">
                  To complete your registration, please enter the verification code below. This code expires in <strong>10 minutes</strong>.
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                  <tr>
                    <td align="center">
                      <div style="display:inline-block;background:#f8f9fa;border:2px dashed #DD0426;border-radius:12px;padding:18px 40px;">
                        <span style="font-size:36px;font-weight:bold;letter-spacing:10px;color:#DD0426;">{$otp}</span>
                      </div>
                    </td>
                  </tr>
                </table>
                <p style="font-size:13px;color:#888;margin:0;">If you did not request this, please ignore this email.</p>
              </td>
            </tr>
            <tr>
              <td style="background:#f8f9fa;padding:14px 32px;text-align:center;border-top:1px solid #eee;">
                <p style="margin:0;font-size:11px;color:#aaa;">&copy; {$year} EVSU – Document Request System. Do not reply to this email.</p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;

    $payload = json_encode([
        'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'          => [['email' => $email, 'name' => $fullName]],
        'subject'     => 'Your EVSU Registration Verification Code',
        'htmlContent' => $htmlContent,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
        exit;
    }

    // Store OTP only after email succeeds (avoids cooldown lock on failed sends)
    $pdo->prepare('DELETE FROM registration_otps WHERE email = ?')->execute([$email]);
    $pdo->prepare('INSERT INTO registration_otps (student_number, names, surnames, email, password_hash, otp_code, expires_at, sent_at)
                   VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())')
        ->execute([$student_number, $names, $surnames, $email, $passwordHash, $otp]);

    echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
