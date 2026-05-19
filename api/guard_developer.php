<?php
/**
 * Auth guard for developer dashboard — redirect to login if not authenticated or wrong role.
 */
while (ob_get_level()) ob_end_clean();
ob_start();
require_once __DIR__ . '/session_init.php';
session_name('DEV_SESS');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    ob_end_clean();
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://'
         . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/developer-login.php';
    header('Location: ' . $url, true, 302);
    exit;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT role, is_active FROM admins WHERE id = ?');
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (isset($row['is_active']) && (int)$row['is_active'] === 0)) {
        header('Location: developer-login.php');
        exit;
    }
    $role = strtolower(trim($row['role'] ?? ''));
    if ($role !== 'developer') {
        header('Location: developer-login.php');
        exit;
    }
} catch (Throwable $e) {
    header('Location: developer-login.php');
    exit;
}
