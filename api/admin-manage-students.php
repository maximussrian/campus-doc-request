<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/audit_helper.php';

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = strtolower(trim($input['action'] ?? ''));
$userId = (int)($input['user_id'] ?? 0);

if (!$userId || !in_array($action, ['deactivate', 'reactivate', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo = Database::getConnection();
    // Ensure column exists (safe migration)
    try { $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (PDOException $e) {}

    // Fetch student info for auditing / response
    $stmt = $pdo->prepare('SELECT id, student_number, names, surnames, email, is_active FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    if ($action === 'delete') {
        // DELETE cascades to document_requests via FK; this is permanent
        $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$userId]);
        auditLog($pdo, (int)($_SESSION['admin_id'] ?? 0), $_SESSION['admin_name'] ?? '', 'student_delete', 'user_id=' . $userId . ' student=' . ($u['student_number'] ?? ''));
        echo json_encode(['success' => true, 'message' => 'Student record deleted']);
        exit;
    }

    $newActive = ($action === 'reactivate') ? 1 : 0;
    $upd = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
    $upd->execute([$newActive, $userId]);
    auditLog(
        $pdo,
        (int)($_SESSION['admin_id'] ?? 0),
        $_SESSION['admin_name'] ?? '',
        $newActive ? 'student_reactivate' : 'student_deactivate',
        'user_id=' . $userId . ' student=' . ($u['student_number'] ?? '')
    );
    echo json_encode(['success' => true, 'message' => $newActive ? 'Student reactivated' : 'Student deactivated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

