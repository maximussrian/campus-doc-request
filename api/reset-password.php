<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$token = preg_replace('/[^a-fA-F0-9]/', '', substr($input['token'] ?? '', 0, 64));
$password = $input['password'] ?? '';

if ($token === '' || strlen($password) < 6 || strlen($password) > MAX_PASSWORD_INPUT) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid token or password (min 6 characters)']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT prt.user_id FROM password_reset_tokens prt WHERE prt.token = ? AND prt.expires_at > NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset link']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $row['user_id']]);
    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$row['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Your password has been reset. You can now log in.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Reset failed. Please try again.']);
}
