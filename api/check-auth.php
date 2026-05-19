<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$_SESSION['_last_activity'] = time();

$user = ['id' => (int)$_SESSION['user_id'], 'student_number' => $_SESSION['student_number'] ?? '', 'names' => $_SESSION['names'] ?? ''];
try {
    $pdo = Database::getConnection();
    try { $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (PDOException $e) {}
    $stmt = $pdo->prepare('SELECT department, course, is_active FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (isset($row['is_active']) && (int)$row['is_active'] === 0) {
            // Student account deactivated: clear session and force login
            session_destroy();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Account deactivated']);
            exit;
        }
        if (!empty($row['department'])) $user['department'] = $row['department'];
        if (!empty($row['course'])) $user['course'] = $row['course'];
    }
} catch (Throwable $e) { /* ignore */ }

echo json_encode(['success' => true, 'user' => $user]);
