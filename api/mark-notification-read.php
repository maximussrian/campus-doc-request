<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/session_init.php';
session_start();
session_name('PHPSESSID');
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$ids = isset($input['ids']) ? (array)$input['ids'] : [];
$all = !empty($input['mark_all']);

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();
    $userId = (int)$_SESSION['user_id'];
    if ($all) {
        $stmt = $pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    } else {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            echo json_encode(['success' => true]);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $ids));
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}
