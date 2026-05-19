<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['registrar', 'teller']);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

session_start();
$myId = (int)($_SESSION['admin_id'] ?? 0);
$myName = $_SESSION['admin_name'] ?? 'Staff';
if (!$myId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$recipientId = (int)($input['recipient_id'] ?? 0);
$message = sanitizeText($input['message'] ?? '', MAX_CHAT_MESSAGE_LENGTH);
if (!$recipientId || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Recipient and message required']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT id, name, email FROM admins WHERE id = ? AND role IN ('registrar','teller') AND is_active = 1");
    $stmt->execute([$recipientId]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipient) {
        echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
        exit;
    }
    $toEmail = $recipient['email'] ?? '';
    if (!$toEmail) {
        echo json_encode(['success' => false, 'message' => 'Recipient has no email configured']);
        exit;
    }
    $toName = $recipient['name'];
    $subject = 'Message from ' . $myName . ' (Document Request System)';
    $html = '<p>' . nl2br(htmlspecialchars($message)) . '</p><p style="color:#888;font-size:.85rem">— ' . htmlspecialchars($myName) . '</p>';
    
    $payload = json_encode([
        'sender' => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to' => [['email' => $toEmail, 'name' => $toName]],
        'subject' => $subject,
        'htmlContent' => $html,
    ]);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        echo json_encode(['success' => true, 'message' => 'Message sent to ' . $toName]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
