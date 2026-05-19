<?php
/**
 * Developer-only: fetch audit log entries.
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer']);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Bounded integer — safe from injection (never concatenate unvalidated input)
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
try {
    $pdo = Database::getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        admin_name VARCHAR(150) NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->query("SELECT id, admin_id, admin_name, action, details, ip_address, created_at FROM audit_log ORDER BY created_at DESC LIMIT $limit");
    $logs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $logs = [];
}
echo json_encode(['success' => true, 'logs' => $logs]);
