<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, max-age=0');
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['registrar', 'teller']);
require_once __DIR__ . '/../config/database.php';

session_start();
$myId = (int)($_SESSION['admin_id'] ?? 0);
if (!$myId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'recipients' => []]);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT id, name, email
        FROM admins
        WHERE role IN ('registrar', 'teller') AND is_active = 1 AND id != ?
        ORDER BY role ASC, name ASC
    ");
    $stmt->execute([$myId]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'recipients' => $recipients]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'recipients' => [], 'message' => $e->getMessage()]);
}
