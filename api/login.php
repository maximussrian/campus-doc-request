<?php
require_once __DIR__ . '/session_init.php';
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

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$student_number = sanitizeText($input['student_number'] ?? '', 50);
$password = $input['password'] ?? '';

// Basic validation & normalization
if ($student_number === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student number and password are required']);
    exit;
}
// Enforce student number format and limit password length
if (strlen($password) > MAX_PASSWORD_INPUT) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}
if (!preg_match('/^\d{4}-\d{5}$/', $student_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student number format']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Maintenance mode: block student login
    try {
        $m = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $m->execute(['maintenance_mode']);
        $row = $m->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['setting_value'] === '1') {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'System is under maintenance.', 'maintenance' => true]);
            exit;
        }
    } catch (Throwable $e) { /* system_settings may not exist */ }
    try { $pdo->exec('ALTER TABLE users ADD COLUMN department VARCHAR(255) NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN course VARCHAR(255) NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (PDOException $e) {}
    $stmt = $pdo->prepare('SELECT id, student_number, names, surnames, email, password_hash, department, course, is_active FROM users WHERE student_number = ?');
    $stmt->execute([$student_number]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash']) || (isset($user['is_active']) && (int)$user['is_active'] === 0)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid student number or password']);
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) session_start();
    // Regenerate session ID on successful login to prevent session fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['student_number'] = $user['student_number'];
    $_SESSION['names'] = $user['names'];
    $_SESSION['department'] = $user['department'] ?? null;
    $_SESSION['course'] = $user['course'] ?? null;

    $userResp = ['id' => (int)$user['id'], 'names' => $user['names']];
    if (!empty($user['department'])) $userResp['department'] = $user['department'];
    if (!empty($user['course'])) $userResp['course'] = $user['course'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $userResp
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed']);
}