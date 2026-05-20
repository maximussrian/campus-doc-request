<?php
/**
 * Public endpoint: returns whether maintenance mode is enabled.
 * No auth required. Used by frontend to show maintenance page.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$enabled = false;
try {
    require_once __DIR__ . '/../config/config.php';
    $port = defined('DB_PORT') ? DB_PORT : '3306';
    $dsn  = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo  = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute(['maintenance_mode']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $enabled = ($row && $row['setting_value'] === '1');
} catch (Throwable $e) {
    // DB not ready or table missing — treat as not in maintenance
    $enabled = false;
}
echo json_encode(['maintenance' => $enabled]);
