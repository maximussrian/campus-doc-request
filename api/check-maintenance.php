<?php
/**
 * Public endpoint: returns whether maintenance mode is enabled.
 * No auth required. Used by frontend to show maintenance page.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$enabled = false;
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute(['maintenance_mode']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $enabled = ($row && $row['setting_value'] === '1');
} catch (Throwable $e) {
    // Table may not exist yet
}
echo json_encode(['maintenance' => $enabled]);
