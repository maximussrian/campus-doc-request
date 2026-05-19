<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$token = sanitizeText($_GET['token'] ?? '', MAX_ACTIVATION_TOKEN_LENGTH);

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Activation token is missing']);
    exit;
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('SELECT id, name, role, is_active FROM admins WHERE activation_token = ?');
    $stmt->execute([$token]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired activation link. Please contact your administrator.']);
        exit;
    }

    if ($admin['is_active'] == 1) {
        echo json_encode(['success' => true, 'already_active' => true, 'name' => $admin['name'], 'role' => $admin['role'], 'message' => 'Your account is already activated. Please log in.']);
        exit;
    }

    // Activate the account
    $pdo->prepare('UPDATE admins SET is_active = 1, activation_token = NULL WHERE id = ?')
        ->execute([$admin['id']]);

    echo json_encode([
        'success' => true,
        'name'    => $admin['name'],
        'role'    => $admin['role'],
        'message' => 'Account activated successfully! You can now log in with your credentials.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Activation failed: ' . $e->getMessage()]);
}
