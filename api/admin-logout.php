<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$adminId   = $_SESSION['admin_id'] ?? null;
$adminName = $_SESSION['admin_name'] ?? null;
if ($adminId || $adminName) {
    try {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/audit_helper.php';
        $pdo = Database::getConnection();
        auditLog($pdo, $adminId ? (int)$adminId : null, $adminName, 'logout', null);
    } catch (Throwable $e) { /* ignore */ }
}

session_destroy();
echo json_encode(['success' => true]);
