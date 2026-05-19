<?php
/**
 * Auth guard for teller dashboard — redirect to login if not authenticated or wrong role.
 */
while (ob_get_level()) ob_end_clean();
ob_start();
require_once __DIR__ . '/session_init.php';
session_name('TELLER_SESS');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    ob_end_clean();
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://'
         . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/teller-login.php';
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
        header('Location: teller-login.php');
        exit;
    }
    $role = strtolower(trim($row['role'] ?? ''));
    if ($role !== 'teller') {
        header('Location: teller-login.php');
        exit;
    }
} catch (Throwable $e) {
    header('Location: teller-login.php');
    exit;
}
