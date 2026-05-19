<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/validation_helper.php';

$input    = json_decode(file_get_contents('php://input'), true);
$input    = is_array($input) ? $input : [];
$username = validateAdminUsername($input['username'] ?? '');
$password = $input['password'] ?? '';

// Basic validation & normalization
if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}
if (strlen($password) > MAX_PASSWORD_INPUT) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (strlen($ip) > 45) $ip = substr($ip, 0, 45);

try {
    $pdo = Database::getConnection();

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(100) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_ip_user (ip_address, username)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        admin_name VARCHAR(150) NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin (admin_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    )");

    $maxAttempts = defined('LOGIN_MAX_ATTEMPTS') ? (int)LOGIN_MAX_ATTEMPTS : 5;
    $lockoutMins = defined('LOGIN_LOCKOUT_MINUTES') ? (int)LOGIN_LOCKOUT_MINUTES : 15;

    $ra = $pdo->prepare("SELECT attempts, locked_until FROM login_attempts WHERE ip_address = ? AND username = ?");
    $ra->execute([$ip, $username]);
    $row = $ra->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['locked_until']) {
        $lockedUntil = strtotime($row['locked_until']);
        if ($lockedUntil > time()) {
            $minsLeft = (int)ceil(($lockedUntil - time()) / 60);
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => "Too many failed attempts. Try again in {$minsLeft} minute(s).", 'locked' => true]);
            exit;
        }
        $pdo->prepare("UPDATE login_attempts SET attempts = 0, locked_until = NULL WHERE ip_address = ? AND username = ?")->execute([$ip, $username]);
    }

    // Maintenance mode: block registrar & teller, allow developer only
    try {
        $m = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $m->execute(['maintenance_mode']);
        $row = $m->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['setting_value'] === '1') {
            $preview = $pdo->prepare('SELECT role FROM admins WHERE username = ? AND is_active = 1');
            $preview->execute([$username]);
            $adm = $preview->fetch();
            if ($adm && $adm['role'] !== 'developer') {
                http_response_code(503);
                echo json_encode(['success' => false, 'message' => 'System is under maintenance.', 'maintenance' => true]);
                exit;
            }
        }
    } catch (Throwable $e) { /* system_settings may not exist */ }

    // Create admins table with full RBAC columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(150) NOT NULL,
        role ENUM('developer','registrar','teller') NOT NULL DEFAULT 'teller',
        created_by INT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrate existing table: add missing columns if upgrading from old schema
    $columns = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('role', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role ENUM('developer','registrar','teller') NOT NULL DEFAULT 'registrar' AFTER name");
    }
    if (!in_array('created_by', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN created_by INT NULL AFTER role");
    }
    if (!in_array('is_active', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER created_by");
    }
    if (!in_array('email', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(150) NULL AFTER name");
    }
    if (!in_array('activation_token', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN activation_token VARCHAR(100) NULL");
    }
    if (!in_array('must_change_password', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('permissions', $columns)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN permissions JSON NULL");
    }

    // Seed default developer account: superadmin / dev@EVSU2024
    $devHash = password_hash('dev@EVSU2024', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO admins (username, password_hash, name, role) VALUES ('superadmin', '$devHash', 'System Developer', 'developer')");

    // Seed default registrar account: admin / admin123
    $regHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO admins (username, password_hash, name, role) VALUES ('admin', '$regHash', 'Registrar Admin', 'registrar')");

    // Fetch admin
    $stmt = $pdo->prepare('SELECT id, username, name, role, password_hash, permissions FROM admins WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        require_once __DIR__ . '/audit_helper.php';
        $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempts) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()")->execute([$ip, $username]);
        $ra2 = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ? AND username = ?");
        $ra2->execute([$ip, $username]);
        $att = (int)($ra2->fetchColumn() ?: 0);
        if ($att >= $maxAttempts) {
            $pdo->prepare("UPDATE login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE ip_address = ? AND username = ?")
                ->execute([$lockoutMins, $ip, $username]);
            auditLog($pdo, null, $username, 'login_failed', "Locked after {$att} attempts");
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => "Too many failed attempts. Account locked for {$lockoutMins} minutes.", 'locked' => true]);
            exit;
        }
        auditLog($pdo, null, $username, 'login_failed', "Attempt {$att}");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND username = ?")->execute([$ip, $username]);
    require_once __DIR__ . '/audit_helper.php';
    auditLog($pdo, (int)$admin['id'], $admin['name'], 'login_success', 'Role: ' . $admin['role']);

    // Resolve effective permissions: use stored JSON or fall back to role defaults
    $roleDefaults = [
        'teller'    => ['handle_requests' => true],
        'registrar' => ['manage_requests' => true, 'manage_staff' => true, 'view_students' => true, 'export_reports' => true, 'assign_departments' => true],
        'developer' => [],
    ];
    $storedPerms = !empty($admin['permissions']) ? json_decode($admin['permissions'], true) : null;
    $permissions = is_array($storedPerms) ? $storedPerms : ($roleDefaults[$admin['role']] ?? []);

    if (session_status() === PHP_SESSION_NONE) session_start();
    // Regenerate session ID on successful admin login to prevent session fixation
    session_regenerate_id(true);
    $_SESSION['admin_id']          = $admin['id'];
    $_SESSION['admin_name']        = $admin['name'];
    $_SESSION['admin_role']        = $admin['role'];
    $_SESSION['admin_permissions'] = $permissions;
    session_write_close();

    echo json_encode([
        'success'     => true,
        'name'        => $admin['name'],
        'role'        => $admin['role'],
        'permissions' => $permissions,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
}
