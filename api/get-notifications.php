<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/session_init.php';
session_name('PHPSESSID');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();
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
    } catch (PDOException $e) { /* table may already exist */ }

    $stmt = $pdo->prepare('
        SELECT id, request_id, document_name, status, is_read, created_at
        FROM user_notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadCount = 0;
    foreach ($rows as &$r) {
        $r['is_read'] = (int)$r['is_read'];
        if (!$r['is_read']) $unreadCount++;
    }

    echo json_encode([
        'success' => true,
        'notifications' => $rows,
        'unread_count' => $unreadCount,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load notifications']);
}
