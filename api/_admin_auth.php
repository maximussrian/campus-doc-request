<?php
/**
 * Shared RBAC helper for admin API endpoints.
 * Include this file, then call requireAdminRole([...allowed roles...]).
 */
require_once __DIR__ . '/session_init.php';
if (!defined('SESSION_TIMEOUT_MINUTES')) {
    @include_once __DIR__ . '/../config/config.php';
}

function requireAdminRole(array $allowed = ['developer', 'registrar', 'teller']): void {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Always resolve role from DB to avoid stale session when switching developer/registrar dashboards
    $role = 'teller';
    require_once __DIR__ . '/../config/database.php';
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT role, permissions FROM admins WHERE id = ? AND is_active = 1');
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $roleDefaults = [
            'teller'    => ['handle_requests' => true],
            'registrar' => ['manage_requests' => true, 'manage_staff' => true, 'view_students' => true, 'export_reports' => true, 'assign_departments' => true],
            'developer' => [],
        ];
        if (!$row) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Account has been deactivated', 'account_deactivated' => true]);
            exit;
        }
        {
            $role = strtolower(trim($row['role'] ?? 'teller'));
            $_SESSION['admin_role'] = $role;
            $stored = !empty($row['permissions']) ? json_decode($row['permissions'], true) : null;
            $perms = is_array($stored) ? $stored : ($roleDefaults[$role] ?? []);
            // Merge with role defaults so missing keys (e.g. manage_staff) default correctly
            $defaults = $roleDefaults[$role] ?? [];
            foreach ($defaults as $k => $v) {
                if (!array_key_exists($k, $perms)) $perms[$k] = $v;
            }
            $_SESSION['admin_permissions'] = $perms;
            if (empty($_SESSION['admin_name'])) {
                $n = $pdo->prepare('SELECT name FROM admins WHERE id = ?');
                $n->execute([(int)$_SESSION['admin_id']]);
                $_SESSION['admin_name'] = $n->fetchColumn() ?: 'Admin';
            }
        }
    } catch (Throwable $e) {
        $role = strtolower(trim($_SESSION['admin_role'] ?? 'teller'));
    }
    $_SESSION['_last_activity'] = time();

    if (!in_array($role, $allowed)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: insufficient permissions']);
        exit;
    }
}

/**
 * Require a specific permission key in the current session.
 * Developer role always passes. All others must have the key set to true.
 */
function requirePermission(string $perm): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (($_SESSION['admin_role'] ?? '') === 'developer') return;
    if (empty($_SESSION['admin_permissions'][$perm])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied: you do not have the "' . $perm . '" permission.']);
        exit;
    }
}
