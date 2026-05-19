<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar']);
// Registrars must have manage_staff permission to reach this endpoint
if (($_SESSION['admin_role'] ?? '') === 'registrar') requirePermission('manage_staff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';
require_once __DIR__ . '/audit_helper.php';

$method     = $_SERVER['REQUEST_METHOD'];
$callerRole = $_SESSION['admin_role'];
$callerId   = (int)$_SESSION['admin_id'];

/* ── Brevo email helper ─────────────────────────────────────────── */
function sendBrevoEmail(string $toEmail, string $toName, string $subject, string $html): bool {
    $payload = json_encode([
        'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $html,
    ]);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function buildCredentialsEmail(string $recipientName, string $username, string $tempPassword, string $role, string $activationLink): string {
    $roleLabel = ucfirst($role);
    $year      = date('Y');
    $loginUrl  = site_url('admin-login.php');
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:40px 0">
        <tr><td align="center">
          <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)">
            <tr><td style="background:#DD0426;padding:28px 40px;text-align:center">
              <h1 style="margin:0;color:#fff;font-size:1.4rem;letter-spacing:.5px">EVSU Document Request System</h1>
              <p style="margin:6px 0 0;color:rgba(255,255,255,.85);font-size:.85rem">Account Created — Action Required</p>
            </td></tr>
            <tr><td style="padding:36px 40px">
              <p style="margin:0 0 18px;color:#222;font-size:1rem">Hello, <strong>{$recipientName}</strong>!</p>
              <p style="margin:0 0 20px;color:#555;line-height:1.6">
                A <strong>{$roleLabel}</strong> account has been created for you on the EVSU Document Request System.
                Below are your login credentials:
              </p>

              <table width="100%" cellpadding="12" cellspacing="0" style="background:#fef0f3;border:1.5px solid #f8c0c8;border-radius:8px;margin-bottom:24px">
                <tr>
                  <td style="color:#999;font-size:.8rem;font-weight:600;text-transform:uppercase;border-bottom:1px solid #f8c0c8">Username</td>
                  <td style="color:#DD0426;font-weight:700;font-size:1rem;border-bottom:1px solid #f8c0c8">{$username}</td>
                </tr>
                <tr>
                  <td style="color:#999;font-size:.8rem;font-weight:600;text-transform:uppercase;padding-top:12px">Temporary Password</td>
                  <td style="color:#DD0426;font-weight:700;font-size:1rem;font-family:monospace;padding-top:12px">{$tempPassword}</td>
                </tr>
              </table>

              <p style="margin:0 0 8px;color:#555;font-size:.9rem">
                <strong>⚠ Important:</strong> Your account is not yet active. You must verify your email by clicking the button below before you can log in.
              </p>

              <div style="text-align:center;margin:28px 0">
                <a href="{$activationLink}" style="display:inline-block;background:#DD0426;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:1rem;letter-spacing:.3px">
                  Activate My Account
                </a>
              </div>

              <p style="margin:0 0 6px;color:#999;font-size:.78rem;text-align:center">
                Button not working? Copy and paste this link into your browser:
              </p>
              <p style="margin:0 0 24px;color:#999;font-size:.75rem;text-align:center;word-break:break-all">{$activationLink}</p>

              <p style="margin:0;color:#bbb;font-size:.78rem;text-align:center;border-top:1px solid #f0f0f0;padding-top:20px">
                This link expires in <strong>24 hours</strong>. After activating, please change your password for security. &copy; {$year} EVSU
              </p>
            </td></tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

/* ── Random password & token helpers ───────────────────────────── */
function generateTempPassword(int $length = 10): string {
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789@#!';
    $pwd   = '';
    for ($i = 0; $i < $length; $i++) {
        $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pwd;
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

try {
    $pdo = Database::getConnection();

    // Ensure new columns exist (safe migration — runs once, idempotent)
    $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('email', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(150) NULL AFTER name");
    }
    if (!in_array('activation_token', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN activation_token VARCHAR(100) NULL");
    }
    if (!in_array('must_change_password', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('permissions', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN permissions JSON NULL");
    }

    /* ── GET ──────────────────────────────────────────────────────────
       Developer  → all accounts
       Registrar  → teller accounts only
    ──────────────────────────────────────────────────────────────── */
    if ($method === 'GET') {
        $roleDefaults = [
            'teller'    => ['handle_requests' => true],
            'registrar' => ['manage_requests' => true, 'manage_staff' => true, 'view_students' => true, 'export_reports' => true],
            'developer' => [],
        ];

        if ($callerRole === 'developer') {
            $stmt = $pdo->query("
                SELECT a.id, a.username, a.name, a.email, a.role, a.is_active,
                       a.activation_token, a.must_change_password, a.created_at,
                       a.permissions,
                       c.name AS created_by_name
                FROM admins a
                LEFT JOIN admins c ON c.id = a.created_by
                ORDER BY FIELD(a.role,'developer','registrar','teller'), a.name ASC
            ");
        } else {
            $stmt = $pdo->query("
                SELECT a.id, a.username, a.name, a.email, a.role, a.is_active,
                       a.activation_token, a.must_change_password, a.created_at,
                       a.permissions,
                       c.name AS created_by_name
                FROM admins a
                LEFT JOIN admins c ON c.id = a.created_by
                WHERE a.role = 'teller'
                ORDER BY a.name ASC
            ");
        }
        $users   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            $u['activation_link'] = (!empty($u['activation_token']) && !$u['is_active'])
                ? site_url('admin-activate.php?token=' . $u['activation_token'])
                : null;
            unset($u['activation_token']);
            // Decode stored permissions or fall back to role defaults
            $stored = !empty($u['permissions']) ? json_decode($u['permissions'], true) : null;
            $u['permissions'] = is_array($stored) ? $stored : ($roleDefaults[$u['role']] ?? []);
        }
        unset($u);
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];

    /* ── POST: create account ─────────────────────────────────────────
       Developer  → registrar or teller
       Registrar  → teller only
    ──────────────────────────────────────────────────────────────── */
    if ($method === 'POST') {
        $username = validateAdminUsername($input['username'] ?? '');
        $name     = sanitizeText($input['name'] ?? '', MAX_NAMES_LENGTH);
        $email    = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));
        $newRole  = validateAdminRole($input['role'] ?? '');

        if (empty($username) || empty($name) || empty($email) || empty($newRole)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All fields are required (name, username, email, role)']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }

        if ($callerRole === 'registrar' && $newRole !== 'teller') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Registrar can only create Teller accounts']);
            exit;
        }

        if ($callerRole === 'developer' && !in_array($newRole, ['registrar', 'teller'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Role must be registrar or teller']);
            exit;
        }

        // Check duplicate username or email
        $check = $pdo->prepare('SELECT id FROM admins WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username or email already in use']);
            exit;
        }

        $tempPassword    = generateTempPassword();
        $activationToken = generateToken();
        $hash            = password_hash($tempPassword, PASSWORD_DEFAULT);

        // Resolve permissions for new account
        $roleDefaults = [
            'teller'    => ['handle_requests' => true],
            'registrar' => ['manage_requests' => true, 'manage_staff' => true, 'view_students' => true, 'export_reports' => true, 'assign_departments' => true],
            'developer' => [],
        ];
        $allowedKeys = [
            'teller'    => ['handle_requests', 'view_students', 'export_reports'],
            'registrar' => ['manage_requests', 'manage_staff', 'view_students', 'export_reports', 'assign_departments'],
        ];
        $incomingPerms = isset($input['permissions']) && is_array($input['permissions']) ? $input['permissions'] : [];
        $defaults      = $roleDefaults[$newRole] ?? [];
        // Merge: start from defaults, overlay incoming values, restrict to allowed keys
        $perms = $defaults;
        foreach ($allowedKeys[$newRole] ?? [] as $key) {
            if (array_key_exists($key, $incomingPerms)) {
                $perms[$key] = (bool)$incomingPerms[$key];
            }
        }
        // handle_requests is always true for tellers
        if ($newRole === 'teller') $perms['handle_requests'] = true;
        // assigned_departments: only Head Registrar with assign_departments permission can assign
        if ($newRole === 'teller') {
            $canAssign = ($callerRole === 'registrar' && !empty($_SESSION['admin_permissions']['assign_departments']));
            if ($canAssign && isset($incomingPerms['assigned_departments']) && is_array($incomingPerms['assigned_departments'])) {
                $perms['assigned_departments'] = array_values(array_filter(array_map(function ($d) {
                    return sanitizeText((string)$d, MAX_DEPARTMENT_LENGTH);
                }, $incomingPerms['assigned_departments'])));
            } else {
                $perms['assigned_departments'] = [];
            }
        }
        $permissionsJson = json_encode($perms);

        $stmt = $pdo->prepare('
            INSERT INTO admins (username, password_hash, name, email, role, created_by, is_active, activation_token, must_change_password, permissions)
            VALUES (?,?,?,?,?,?,0,?,1,?)
        ');
        $stmt->execute([$username, $hash, $name, $email, $newRole, $callerId, $activationToken, $permissionsJson]);

        auditLog($pdo, $callerId, $_SESSION['admin_name'] ?? null, 'user_create', "username=$username role=$newRole");

        // Build activation link
        $activationLink = site_url('admin-activate.php?token=' . $activationToken);

        // Send credentials email
        $subject  = 'Your EVSU Document Request System Account';
        $html     = buildCredentialsEmail($name, $username, $tempPassword, $newRole, $activationLink);
        $emailSent = sendBrevoEmail($email, $name, $subject, $html);

        echo json_encode([
            'success'    => true,
            'message'    => ucfirst($newRole) . ' account created. Activation email ' . ($emailSent ? 'sent to ' . $email : 'could not be sent — share credentials manually.'),
            'email_sent' => $emailSent,
        ]);
        exit;
    }

    /* ── PATCH: manually activate a pending account ─────────────────
       Developer  → can activate registrar or teller
       Registrar  → can activate teller only
    ──────────────────────────────────────────────────────────────── */
    if ($method === 'PATCH') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit;
        }

        $target = $pdo->prepare('SELECT role, is_active FROM admins WHERE id = ?');
        $target->execute([$id]);
        $existing = $target->fetch();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        if ($callerRole === 'registrar' && $existing['role'] !== 'teller') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Registrar can only activate Teller accounts']);
            exit;
        }
        if ($existing['is_active'] == 1) {
            echo json_encode(['success' => true, 'message' => 'Account is already active']);
            exit;
        }

        $pdo->prepare('UPDATE admins SET is_active = 1, activation_token = NULL WHERE id = ?')
            ->execute([$id]);
        $targetName = $pdo->prepare('SELECT name FROM admins WHERE id = ?');
        $targetName->execute([$id]);
        $tn = $targetName->fetchColumn() ?: "id=$id";
        auditLog($pdo, $callerId, $_SESSION['admin_name'] ?? null, 'user_activate', "target=$tn");
        echo json_encode(['success' => true, 'message' => 'Account activated successfully']);
        exit;
    }

    /* ── PUT: edit account ─────────────────────────────────────────
       Developer → any non-developer account
       Registrar → teller only (permissions & departments)
    ──────────────────────────────────────────────────────────────── */
    if ($method === 'PUT') {

        $id      = (int)($input['id']   ?? 0);
        $name    = sanitizeText($input['name'] ?? '', MAX_NAMES_LENGTH);
        $newRole = validateAdminRole($input['role'] ?? '');
        $email   = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));

        if (!$id || empty($name) || empty($newRole)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID, name and role are required']);
            exit;
        }

        $target = $pdo->prepare('SELECT role FROM admins WHERE id = ?');
        $target->execute([$id]);
        $existing = $target->fetch();

        if (!$existing) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'User not found']); exit; }
        if ($callerRole === 'registrar') {
            if ($existing['role'] !== 'teller' || $newRole !== 'teller') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Registrar can only edit teller accounts.']);
                exit;
            }
        } elseif ($existing['role'] === 'developer' && $id !== $callerId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Cannot edit another developer account']); exit;
        }
        if (!in_array($newRole, ['registrar', 'teller', 'developer'])) {
            http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid role']); exit;
        }

        // Update permissions if provided
        $allowedKeys = [
            'teller'    => ['handle_requests', 'view_students', 'export_reports'],
            'registrar' => ['manage_requests', 'manage_staff', 'view_students', 'export_reports', 'assign_departments'],
        ];
        $permissionsJson = null;
        if (isset($input['permissions']) && is_array($input['permissions']) && isset($allowedKeys[$newRole])) {
            $perms = [];
            foreach ($allowedKeys[$newRole] as $key) {
                $perms[$key] = array_key_exists($key, $input['permissions']) ? (bool)$input['permissions'][$key] : false;
            }
            if ($newRole === 'teller') {
                $perms['handle_requests'] = true;
                $cur = $pdo->prepare('SELECT permissions FROM admins WHERE id = ?');
                $cur->execute([$id]);
                $curPerms = $cur->fetchColumn();
                $curDecoded = $curPerms ? json_decode($curPerms, true) : null;
                $curDecoded = is_array($curDecoded) ? $curDecoded : [];
                $existingDepts = isset($curDecoded['assigned_departments']) && is_array($curDecoded['assigned_departments']) ? $curDecoded['assigned_departments'] : [];
                $canAssign = ($callerRole === 'registrar' && !empty($_SESSION['admin_permissions']['assign_departments']));
                $perms['assigned_departments'] = ($canAssign && isset($input['permissions']['assigned_departments']) && is_array($input['permissions']['assigned_departments']))
                    ? array_values(array_filter(array_map(function ($d) {
                        return sanitizeText((string)$d, MAX_DEPARTMENT_LENGTH);
                    }, $input['permissions']['assigned_departments'])))
                    : $existingDepts;
            }
            $permissionsJson = json_encode($perms);
        }

        $password = $input['password'] ?? '';
        if (!empty($password)) {
            if (strlen($password) > MAX_PASSWORD_INPUT) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password too long']);
                exit;
            }
            if (strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                exit;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($permissionsJson !== null) {
                $pdo->prepare('UPDATE admins SET name=?, email=?, role=?, password_hash=?, must_change_password=0, permissions=? WHERE id=?')
                    ->execute([$name, $email, $newRole, $hash, $permissionsJson, $id]);
            } else {
                $pdo->prepare('UPDATE admins SET name=?, email=?, role=?, password_hash=?, must_change_password=0 WHERE id=?')
                    ->execute([$name, $email, $newRole, $hash, $id]);
            }
        } else {
            if ($permissionsJson !== null) {
                $pdo->prepare('UPDATE admins SET name=?, email=?, role=?, permissions=? WHERE id=?')
                    ->execute([$name, $email, $newRole, $permissionsJson, $id]);
            } else {
                $pdo->prepare('UPDATE admins SET name=?, email=?, role=? WHERE id=?')
                    ->execute([$name, $email, $newRole, $id]);
            }
        }

        auditLog($pdo, $callerId, $_SESSION['admin_name'] ?? null, 'user_update', "id=$id name=$name role=$newRole");
        echo json_encode(['success' => true, 'message' => 'Account updated successfully']);
        exit;
    }

    /* ── DELETE: deactivate OR permanently cancel account ───────────
       pass  { id, permanent: true }  to hard-delete
       pass  { id }                   to soft-deactivate
       Developer  → registrar or teller
       Registrar  → teller only
    ──────────────────────────────────────────────────────────────── */
    if ($method === 'DELETE') {
        $id        = (int)($input['id']        ?? 0);
        $permanent = (bool)($input['permanent'] ?? false);

        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID is required']); exit; }
        if ($id === $callerId) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'You cannot remove your own account']); exit; }

        $target = $pdo->prepare('SELECT role, name FROM admins WHERE id = ?');
        $target->execute([$id]);
        $existing = $target->fetch();

        if (!$existing) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'User not found']); exit; }
        if ($callerRole === 'registrar' && $existing['role'] !== 'teller') {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Registrar can only remove Teller accounts']); exit;
        }
        if ($existing['role'] === 'developer') {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Cannot remove a Developer account']); exit;
        }

        $targetName = $existing['name'] ?? "id=$id";
        if ($permanent) {
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            auditLog($pdo, $callerId, $_SESSION['admin_name'] ?? null, 'user_delete', "target=$targetName (permanent)");
            echo json_encode(['success' => true, 'message' => 'Account permanently cancelled and removed.']);
        } else {
            $pdo->prepare('UPDATE admins SET is_active = 0 WHERE id = ?')->execute([$id]);
            auditLog($pdo, $callerId, $_SESSION['admin_name'] ?? null, 'user_deactivate', "target=$targetName");
            echo json_encode(['success' => true, 'message' => 'Account deactivated successfully.']);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
