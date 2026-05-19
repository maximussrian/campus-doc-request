<?php
/**
 * Auth guard for student dashboard — redirect to login if not authenticated.
 */
while (ob_get_level()) ob_end_clean();
ob_start();
require_once __DIR__ . '/session_init.php';
session_name('PHPSESSID');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    ob_end_clean();
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://'
         . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/index.php';
    header('Location: ' . $url, true, 302);
    exit;
}
