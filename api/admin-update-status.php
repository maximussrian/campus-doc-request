<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['registrar', 'teller']);
// Registrar must have manage_requests; tellers always have handle_requests (always ON)
if (($_SESSION['admin_role'] ?? '') === 'registrar') requirePermission('manage_requests');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/audit_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = (int)($input['id'] ?? 0);
$status = trim($input['status'] ?? '');

$valid = ['pending', 'processing', 'ready', 'claimed'];
if (!$id || !in_array($status, $valid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Guard: fetch current status before updating
try {
    $pdo = Database::getConnection();
    $cur = $pdo->prepare('SELECT status FROM document_requests WHERE id = ?');
    $cur->execute([$id]);
    $row = $cur->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['status'] === 'claimed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This request has already been claimed and cannot be changed.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

function sendBrevoEmail(string $toEmail, string $toName, string $subject, string $htmlContent): array {
    $payload = json_encode([
        'sender'     => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'         => [['email' => $toEmail, 'name' => $toName]],
        'subject'    => $subject,
        'htmlContent'=> $htmlContent,
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
    return ['code' => $httpCode, 'body' => $response];
}

function buildClaimedEmail(string $studentName, string $documentName): string {
    $year = date('Y');
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
        <tr><td align="center">
          <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">
            <!-- Header -->
            <tr>
              <td style="background:#DD0426;padding:28px 32px;text-align:center;">
                <h1 style="color:#ffffff;margin:0;font-size:22px;letter-spacing:.5px;">Eastern Visayas State University</h1>
                <p style="color:rgba(255,255,255,.85);margin:6px 0 0;font-size:13px;">Office of the University Registrar</p>
              </td>
            </tr>
            <!-- Checkmark banner -->
            <tr>
              <td style="background:#d4edda;padding:20px 32px;text-align:center;">
                <p style="margin:0;font-size:36px;">✅</p>
                <p style="margin:6px 0 0;font-size:17px;font-weight:bold;color:#155724;">Document Successfully Claimed!</p>
              </td>
            </tr>
            <!-- Body -->
            <tr>
              <td style="padding:36px 32px;">
                <p style="font-size:16px;color:#333;margin:0 0 12px;">Dear <strong>{$studentName}</strong>,</p>
                <p style="font-size:15px;color:#555;line-height:1.7;margin:0 0 24px;">
                  This email confirms that you have <strong style="color:#155724;">successfully claimed</strong> your requested document from the Registrar's Office. This transaction is now complete.
                </p>

                <!-- Document card -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-radius:8px;border-left:4px solid #198754;margin-bottom:28px;">
                  <tr>
                    <td style="padding:18px 20px;">
                      <p style="margin:0 0 4px;font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.5px;">Document Claimed</p>
                      <p style="margin:0;font-size:16px;font-weight:bold;color:#222;">{$documentName}</p>
                      <p style="margin:6px 0 0;font-size:12px;color:#888;">Status: <span style="color:#155724;font-weight:bold;">Claimed</span></p>
                    </td>
                  </tr>
                </table>

                <p style="font-size:14px;color:#555;line-height:1.7;margin:0 0 8px;">
                  If you have any concerns or did not personally claim this document, please contact the Registrar's Office immediately.
                </p>

                <p style="font-size:14px;color:#333;margin:24px 0 0;">Best regards,<br>
                  <strong>Office of the University Registrar</strong><br>
                  Eastern Visayas State University
                </p>
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td style="background:#f8f9fa;padding:16px 32px;text-align:center;border-top:1px solid #eee;">
                <p style="margin:0;font-size:12px;color:#aaa;">
                  This is an automated notification. Please do not reply to this email.<br>
                  &copy; {$year} Eastern Visayas State University – Document Request System
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

function buildReadyEmail(string $studentName, string $documentName): string {
    $year = date('Y');
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
        <tr><td align="center">
          <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">
            <!-- Header -->
            <tr>
              <td style="background:#DD0426;padding:28px 32px;text-align:center;">
                <h1 style="color:#ffffff;margin:0;font-size:22px;letter-spacing:.5px;">Eastern Visayas State University</h1>
                <p style="color:rgba(255,255,255,.85);margin:6px 0 0;font-size:13px;">Office of the University Registrar</p>
              </td>
            </tr>
            <!-- Body -->
            <tr>
              <td style="padding:36px 32px;">
                <p style="font-size:16px;color:#333;margin:0 0 12px;">Dear <strong>{$studentName}</strong>,</p>
                <p style="font-size:15px;color:#555;line-height:1.7;margin:0 0 24px;">
                  Great news! Your requested document is now <strong style="color:#198754;">ready for claiming</strong> at the Registrar's Office.
                </p>

                <!-- Document card -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-radius:8px;border-left:4px solid #DD0426;margin-bottom:28px;">
                  <tr>
                    <td style="padding:18px 20px;">
                      <p style="margin:0 0 4px;font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.5px;">Document Requested</p>
                      <p style="margin:0;font-size:16px;font-weight:bold;color:#222;">{$documentName}</p>
                    </td>
                  </tr>
                </table>

                <p style="font-size:14px;color:#555;line-height:1.7;margin:0 0 8px;">
                  Please proceed to the <strong>Registrar's Office</strong> during office hours to claim your document. 
                  Bring a valid school ID or any government-issued ID.
                </p>
                <p style="font-size:13px;color:#888;margin:0 0 28px;">
                  Office hours: Monday – Friday, 8:00 AM – 5:00 PM
                </p>

                <p style="font-size:14px;color:#333;margin:0;">Best regards,<br>
                  <strong>Office of the University Registrar</strong><br>
                  Eastern Visayas State University
                </p>
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td style="background:#f8f9fa;padding:16px 32px;text-align:center;border-top:1px solid #eee;">
                <p style="margin:0;font-size:12px;color:#aaa;">
                  This is an automated notification. Please do not reply to this email.<br>
                  &copy; {$year} Eastern Visayas State University – Document Request System
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

try {
    $pdo = Database::getConnection();

    // Fetch request + student info before updating
    $stmt = $pdo->prepare('
        SELECT dr.id, dr.user_id, dr.status AS old_status, dr.department, dt.name AS document_name,
               u.names, u.surnames, u.email
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        JOIN users u ON u.id = dr.user_id
        WHERE dr.id = ?
    ');
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Department-based RBAC: tellers can only update requests for their assigned departments
    $role = $_SESSION['admin_role'] ?? '';
    if ($role === 'teller') {
        $permRow = $pdo->prepare('SELECT permissions FROM admins WHERE id = ? AND is_active = 1');
        $permRow->execute([(int)($_SESSION['admin_id'] ?? 0)]);
        $permJson = $permRow->fetchColumn();
        $perm = $permJson ? json_decode($permJson, true) : null;
        $depts = (is_array($perm) && isset($perm['assigned_departments']) && is_array($perm['assigned_departments']))
            ? array_values(array_filter(array_map('trim', $perm['assigned_departments']))) : [];
        if (!empty($depts)) {
            $reqDept = trim($request['department'] ?? '');
            if (!in_array($reqDept, $depts)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to update requests for this department.']);
                exit;
            }
        }
    }

    try { $pdo->exec('ALTER TABLE document_requests ADD COLUMN processed_by_admin_id INT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_requests ADD COLUMN processed_by_name VARCHAR(150) NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_requests ADD COLUMN processed_at DATETIME NULL'); } catch (PDOException $e) {}

    $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    $adminName = $_SESSION['admin_name'] ?? null;
    $stmt = $pdo->prepare('UPDATE document_requests SET status = ?, processed_by_admin_id = ?, processed_by_name = ?, processed_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $adminId, $adminName, $id]);

    $emailSent = false;
    $emailError = null;

    // Send email and create in-app notification when status changes TO "ready" or "claimed"
    if (in_array($status, ['ready', 'claimed']) && $request['old_status'] !== $status) {
        $studentName = trim($request['names'] . ' ' . $request['surnames']);
        $userId = (int)($request['user_id'] ?? 0);
        if ($userId) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    request_id INT NOT NULL,
                    document_name VARCHAR(255) NOT NULL,
                    status ENUM('ready','claimed') NOT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_user_unread (user_id, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $ins = $pdo->prepare('INSERT INTO user_notifications (user_id, request_id, document_name, status) VALUES (?, ?, ?, ?)');
                $ins->execute([$userId, $id, $request['document_name'], $status]);
            } catch (PDOException $e) { /* ignore */ }
        }

        if ($status === 'ready') {
            $subject     = 'Your Document is Ready to Claim – EVSU Registrar';
            $htmlContent = buildReadyEmail($studentName, $request['document_name']);
        } else {
            $subject     = 'Document Successfully Claimed – EVSU Registrar';
            $htmlContent = buildClaimedEmail($studentName, $request['document_name']);
        }

        $result     = sendBrevoEmail($request['email'], $studentName, $subject, $htmlContent);
        $emailSent  = ($result['code'] >= 200 && $result['code'] < 300);
        $emailError = $emailSent ? null : 'Email delivery failed (HTTP ' . $result['code'] . ')';
    }

    $details   = sprintf('request_id=%d %s→%s doc=%s', $id, $request['old_status'], $status, $request['document_name'] ?? '');
    auditLog($pdo, $adminId, $adminName, 'status_update', $details);

    echo json_encode([
        'success'     => true,
        'message'     => 'Status updated' . ($emailSent ? ' and notification sent to student.' : '.'),
        'email_sent'  => $emailSent,
        'email_error' => $emailError,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
