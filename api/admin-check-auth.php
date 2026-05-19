<?php
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/config.php';
session_start();
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'role' => null]);
    exit;
}
$role = null;
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getConnection();
    $r = $pdo->prepare('SELECT role, permissions, is_active FROM admins WHERE id = ?');
    $r->execute([(int)$_SESSION['admin_id']]);
    $row = $r->fetch(PDO::FETCH_ASSOC);
    if (!$row || (isset($row['is_active']) && (int)$row['is_active'] === 0)) {
        echo json_encode(['success' => false, 'role' => null, 'account_deactivated' => true]);
        exit;
    }
    $role = $row['role'];
    $_SESSION['admin_role'] = $role;
    $roleDefaults = [
        'teller'    => ['handle_requests' => true],
        'registrar' => ['manage_requests' => true, 'manage_staff' => true, 'view_students' => true, 'export_reports' => true, 'assign_departments' => true],
        'developer' => [],
    ];
    $stored = !empty($row['permissions']) ? json_decode($row['permissions'], true) : null;
    $perms = is_array($stored) ? $stored : ($roleDefaults[$role] ?? []);
    foreach ($roleDefaults[$role] ?? [] as $k => $v) {
        if (!array_key_exists($k, $perms)) $perms[$k] = $v;
    }
    $_SESSION['admin_permissions'] = $perms;
} catch (Throwable $e) {
    $role = $_SESSION['admin_role'] ?? 'registrar';
}
$_SESSION['_last_activity'] = time();
echo json_encode([
    'success'     => true,
    'name'        => $_SESSION['admin_name'] ?? '',
    'role'        => $role,
    'permissions' => $_SESSION['admin_permissions'] ?? [],
]);
