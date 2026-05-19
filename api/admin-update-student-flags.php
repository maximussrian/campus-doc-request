<?php
header('Content-Type: application/json');
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar', 'teller']);
requirePermission('manage_requests');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = (int)($input['user_id'] ?? 0);
$transferAuthorized = isset($input['transfer_authorized']) ? (int)(bool)$input['transfer_authorized'] : null;
$graduated = isset($input['graduated']) ? (int)(bool)$input['graduated'] : null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

if ($transferAuthorized === null && $graduated === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'At least one flag to update required']);
    exit;
}

try {
    $pdo = Database::getConnection();
    try { $pdo->exec('ALTER TABLE users ADD COLUMN transfer_authorized TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN graduated TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}

    $updates = [];
    $params = [];
    if ($transferAuthorized !== null) { $updates[] = 'transfer_authorized = ?'; $params[] = $transferAuthorized; }
    if ($graduated !== null) { $updates[] = 'graduated = ?'; $params[] = $graduated; }
    $params[] = $userId;

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Student flags updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
