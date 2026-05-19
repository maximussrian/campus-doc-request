<?php
/**
 * Developer-only settings API: maintenance mode, backup, restore.
 */
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer']);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';
require_once __DIR__ . '/audit_helper.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$method = $_SERVER['REQUEST_METHOD'];
$action = sanitizeText($_GET['action'] ?? $_POST['action'] ?? '', 50);
$jsonBody = null;
if ($method === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $jsonBody = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!empty($jsonBody['action'])) $action = trim($jsonBody['action']);
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;

    $pdo = Database::getConnection();

    function logBackupActivity(PDO $pdo, int $adminId, string $action, string $status, ?array $tableStatus = null, ?string $filename = null, ?string $restoreMode = null, ?string $message = null): void {
        $stmt = $pdo->prepare("INSERT INTO backup_activity_log (action, admin_id, status, filename, restore_mode, table_status, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$action, $adminId ?: null, $status, $filename, $restoreMode, $tableStatus ? json_encode($tableStatus) : null, $message]);
    }

    // Ensure system_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS backup_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action ENUM('backup','restore','reset') NOT NULL,
        admin_id INT NULL,
        status ENUM('success','failed') NOT NULL DEFAULT 'success',
        filename VARCHAR(255) NULL,
        restore_mode VARCHAR(50) NULL,
        table_status TEXT NULL,
        message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($action === 'backup_logs' && $method === 'GET') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $stmt = $pdo->query("SELECT id, action, admin_id, status, filename, restore_mode, table_status, message, created_at FROM backup_activity_log ORDER BY created_at DESC LIMIT $limit");
        $logs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($logs as &$row) {
            $row['table_status'] = $row['table_status'] ? json_decode($row['table_status'], true) : [];
        }
        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }

    if ($action === 'audit_logs' && $method === 'GET') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        try {
            $stmt = $pdo->query("SELECT id, admin_id, admin_name, action, details, ip_address, created_at FROM audit_log ORDER BY created_at DESC LIMIT $limit");
            $logs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $logs = [];
        }
        echo json_encode(['success' => true, 'logs' => $logs]);
        exit;
    }

    if (($action === 'maintenance' || $action === '') && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute(['maintenance_mode']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $value = ($row && $row['setting_value'] === '1');
        echo json_encode(['success' => true, 'maintenance_mode' => $value]);
        exit;
    }
    if ($action === 'maintenance' && $method === 'POST') {
        $body = $jsonBody ?? json_decode(file_get_contents('php://input'), true) ?: [];
        $on = !empty($body['enabled']);
        $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
        $v = $on ? '1' : '0';
        $stmt->execute(['maintenance_mode', $v, $v]);
        auditLog($pdo, $adminId, $_SESSION['admin_name'] ?? null, 'maintenance_' . ($on ? 'on' : 'off'), null);
        echo json_encode(['success' => true, 'maintenance_mode' => $on]);
        exit;
    }

    $maintenanceOn = false;
    try {
        $m = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $row = $m ? $m->fetch(PDO::FETCH_ASSOC) : null;
        $maintenanceOn = $row && ($row['setting_value'] ?? '') === '1';
    } catch (Exception $e) { }

    if ($action === 'backup') {
        if (!$maintenanceOn) {
            echo json_encode(['success' => false, 'message' => 'Enable maintenance mode first']);
            exit;
        }
        if (!class_exists('ZipArchive')) {
            echo json_encode(['success' => false, 'message' => 'ZipArchive extension not available']);
            exit;
        }
        $stmt = $pdo->query('SHOW TABLES');
        $allTables = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_NUM), 0) : [];
        $depOrder = ['users', 'document_types', 'admins', 'system_settings', 'registration_otps', 'password_reset_tokens', 'document_requests', 'chats', 'chat_messages', 'login_attempts', 'audit_log', 'backup_activity_log'];
        $tables = array_values(array_unique(array_merge($depOrder, $allTables)));
        $manifest = ['created' => date('c'), 'tables' => [], 'version' => '1.0'];
        $tableStatus = [];

        $tmpDir = sys_get_temp_dir() . '/docu_backup_' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Could not create temp directory']);
            exit;
        }

        foreach ($tables as $t) {
            if (!in_array($t, $allTables)) continue;
            try {
                $stmt = $pdo->query("SELECT * FROM `$t`");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $csvPath = $tmpDir . '/' . $t . '.csv';
                $fp = fopen($csvPath, 'w');
                if (!$fp) {
                    $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'failed', 'message' => 'Could not create file'];
                    continue;
                }
                if (count($rows) > 0) {
                    $headers = array_keys($rows[0]);
                    fputcsv($fp, $headers);
                    foreach ($rows as $r) {
                        $vals = [];
                        foreach ($headers as $h) {
                            $vals[] = array_key_exists($h, $r) ? $r[$h] : '';
                        }
                        fputcsv($fp, $vals);
                    }
                } else {
                    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t) . " ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
                    if ($cols) fputcsv($fp, $cols);
                }
                fclose($fp);
                $rowCount = count($rows);
                $manifest['tables'][$t] = $rowCount;
                $tableStatus[] = ['table' => $t, 'rows' => $rowCount, 'status' => 'success'];
            } catch (Exception $e) {
                $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }

        file_put_contents($tmpDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zipPath = sys_get_temp_dir() . '/docu_backup_' . date('Ymd_His') . '_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            @array_map('unlink', glob($tmpDir . '/*'));
            @rmdir($tmpDir);
            logBackupActivity($pdo, $adminId, 'backup', 'failed', $tableStatus, null, null, 'Could not create ZIP');
            echo json_encode(['success' => false, 'message' => 'Could not create ZIP']);
            exit;
        }
        foreach (glob($tmpDir . '/*') as $f) {
            $zip->addFile($f, basename($f));
        }
        $zip->close();
        @array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);

        $filename = 'docu_request_backup_' . date('Ymd_His') . '.zip';
        logBackupActivity($pdo, $adminId, 'backup', 'success', $tableStatus, $filename);
        auditLog($pdo, $adminId, $_SESSION['admin_name'] ?? null, 'backup', $filename);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    if ($action === 'restore' && $method === 'POST') {
        if (!$maintenanceOn) {
            echo json_encode(['success' => false, 'message' => 'Enable maintenance mode first']);
            exit;
        }
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit;
        }
        $path = $_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        $restoreModeRaw = trim($_POST['restore_mode'] ?? 'full');
        $restoreMode = in_array($restoreModeRaw, ['full', 'merge'], true) ? $restoreModeRaw : 'full';
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        if ($ext === 'zip') {
            if (!class_exists('ZipArchive')) {
                echo json_encode(['success' => false, 'message' => 'ZipArchive extension not available']);
                exit;
            }
            $zip = new ZipArchive();
            if (!$zip->open($path, ZipArchive::RDONLY)) {
                echo json_encode(['success' => false, 'message' => 'Invalid ZIP file']);
                exit;
            }
            $tmpDir = sys_get_temp_dir() . '/docu_restore_' . uniqid();
            mkdir($tmpDir, 0755, true);
            $zip->extractTo($tmpDir);
            $zip->close();

            $schemaPath = __DIR__ . '/../database/schema.sql';
            if (is_readable($schemaPath)) {
                $schema = file_get_contents($schemaPath);
                $schema = preg_replace('/^\s*--[^\n]*\n/m', "\n", $schema);
                $schema = str_replace(["\r\n", "\r"], "\n", $schema);
                $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $schema)));
                foreach ($statements as $q) {
                    $q = trim($q);
                    if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $q)) continue;
                    if (strlen($q) > 5 && preg_match('/^CREATE\s+TABLE/i', $q)) {
                        try { $pdo->exec($q); } catch (Exception $e) { }
                    }
                }
            }

            $manifestPath = $tmpDir . '/manifest.json';
            $manifest = [];
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
            }
            $depOrder = ['users', 'document_types', 'admins', 'system_settings', 'registration_otps', 'password_reset_tokens', 'document_requests', 'chats', 'chat_messages', 'login_attempts', 'audit_log', 'backup_activity_log'];
            $canonicalNames = [];
            foreach ($depOrder as $dn) { $canonicalNames[strtolower($dn)] = $dn; }
            $csvFiles = glob($tmpDir . '/*.csv') ?: [];
            $tableFiles = [];
            foreach ($csvFiles as $f) {
                $name = basename($f, '.csv');
                $key = strtolower($name);
                $tableFiles[$key] = ['path' => $f, 'name' => $canonicalNames[$key] ?? $name];
            }
            $ordered = [];
            $seen = [];
            foreach (array_merge($depOrder, array_keys($tableFiles)) as $t) {
                $tKey = is_string($t) ? strtolower($t) : '';
                if ($tKey && !isset($seen[$tKey]) && isset($tableFiles[$tKey])) {
                    $seen[$tKey] = true;
                    $ordered[] = $tKey;
                }
            }
            $tableStatus = [];
            $runSchemaForMissingTable = function () use ($pdo, $schemaPath) {
                if (!is_readable($schemaPath)) return;
                $schema = file_get_contents($schemaPath);
                $schema = preg_replace('/^\s*--[^\n]*\n/m', "\n", $schema);
                $schema = str_replace(["\r\n", "\r"], "\n", $schema);
                $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $schema)));
                foreach ($statements as $q) {
                    $q = trim($q);
                    if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $q)) continue;
                    if (strlen($q) > 5 && preg_match('/^CREATE\s+TABLE/i', $q)) {
                        try { $pdo->exec($q); } catch (Exception $e) { }
                    }
                }
            };

            foreach ($ordered as $tKey) {
                $t = $tableFiles[$tKey]['name'];
                $csvPath = $tableFiles[$tKey]['path'];
                if (!file_exists($csvPath)) continue;
                $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->rowCount() > 0;
                if (!$exists) {
                    $runSchemaForMissingTable();
                    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->rowCount() > 0;
                }
                if (!$exists) {
                    $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'skipped', 'message' => 'Table does not exist (create from schema failed)'];
                    continue;
                }

                if ($restoreMode === 'full') {
                    try { $pdo->exec("TRUNCATE TABLE `$t`"); } catch (Exception $e) { }
                }

                $tableCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t) . " ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($tableCols)) {
                    $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'failed', 'message' => 'Could not read table columns'];
                    continue;
                }
                $tableColsByLower = [];
                foreach ($tableCols as $c) {
                    $tableColsByLower[strtolower($c)] = $c;
                }

                $fp = fopen($csvPath, 'r');
                if (!$fp) {
                    $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'failed', 'message' => 'Could not open CSV'];
                    continue;
                }
                $headersRaw = fgetcsv($fp);
                if (!$headersRaw) { fclose($fp); $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'failed', 'message' => 'Empty or invalid CSV']; continue; }
                $headers = array_map(function ($h) { return trim(str_replace("\xEF\xBB\xBF", '', $h)); }, $headersRaw);
                $mappedCols = [];
                $colIndexes = [];
                foreach ($headers as $i => $h) {
                    if ($h === '') continue;
                    $hLower = strtolower($h);
                    if (isset($tableColsByLower[$hLower])) {
                        $mappedCols[] = $tableColsByLower[$hLower];
                        $colIndexes[] = $i;
                    }
                }
                if (empty($mappedCols)) {
                    fclose($fp);
                    $tableStatus[] = ['table' => $t, 'rows' => 0, 'status' => 'failed', 'message' => 'No matching columns between CSV and table'];
                    continue;
                }
                $cols = array_map(fn($c) => "`$c`", $mappedCols);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $insertSql = ($restoreMode === 'append' ? 'INSERT IGNORE INTO ' : 'INSERT INTO ') . "`$t` (" . implode(',', $cols) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($insertSql);
                $count = 0;
                $errMsg = null;
                while (($row = fgetcsv($fp)) !== false) {
                    $vals = [];
                    foreach ($colIndexes as $idx) {
                        $v = $row[$idx] ?? '';
                        $vals[] = ($v === '' || $v === 'NULL') ? null : $v;
                    }
                    try {
                        $stmt->execute($vals);
                        $count++;
                    } catch (Exception $e) { $errMsg = $e->getMessage(); }
                }
                fclose($fp);
                $tableStatus[] = ['table' => $t, 'rows' => $count, 'status' => $errMsg ? 'partial' : 'success', 'message' => $errMsg];
            }

            @array_map('unlink', glob($tmpDir . '/*'));
            @rmdir($tmpDir);

            $totalRows = array_sum(array_column($tableStatus, 'rows'));
            $summary = [];
            foreach ($tableStatus as $ts) {
                if (($ts['rows'] ?? 0) > 0) $summary[] = $ts['table'] . ' (' . $ts['rows'] . ')';
            }
            $msg = 'Database restored successfully (' . $restoreMode . ')';
            if (!empty($summary)) $msg .= '. Rows: ' . implode(', ', $summary);
            elseif ($totalRows === 0) $msg .= '. No rows restored—backup may be empty or column mismatch.';

            logBackupActivity($pdo, $adminId, 'restore', 'success', $tableStatus, $_FILES['file']['name'], $restoreMode);
            auditLog($pdo, $adminId, $_SESSION['admin_name'] ?? null, 'restore', "file={$_FILES['file']['name']} mode=$restoreMode");

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        echo json_encode(['success' => true, 'message' => $msg, 'table_status' => $tableStatus ?? []]);
        exit;
        } else {
            logBackupActivity($pdo, $adminId, 'restore', 'failed', null, $_FILES['file']['name'] ?? null, null, 'Invalid file type');
            echo json_encode(['success' => false, 'message' => 'Please upload a .zip backup file (CSV format). Legacy .sql backups are no longer supported.']);
            exit;
        }
    }

    if ($action === 'reset' && $method === 'POST') {
        if (!$maintenanceOn) {
            echo json_encode(['success' => false, 'message' => 'Enable maintenance mode first']);
            exit;
        }
        $body = $jsonBody ?? json_decode(file_get_contents('php://input'), true) ?: [];
        $resetMode = trim($body['mode'] ?? 'full');
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (!is_readable($schemaPath)) {
            echo json_encode(['success' => false, 'message' => 'Schema file not found']);
            exit;
        }
        $schema = file_get_contents($schemaPath);
        $schema = preg_replace('/^\s*--[^\n]*\n/m', "\n", $schema);
        $schema = str_replace(["\r\n", "\r"], "\n", $schema);

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $preserveUsers = ($resetMode === 'preserve_users');
        $usersData = $adminsData = [];

        if ($preserveUsers) {
            foreach (['users', 'admins'] as $tbl) {
                try {
                    $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
                    ${$tbl . 'Data'} = $rows;
                } catch (Exception $e) { /* table may not exist */ }
            }
        }

        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_NUM), 0) : [];
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $schema)));
        foreach ($statements as $q) {
            $q = trim($q);
            if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $q)) continue;
            if (strlen($q) > 5 && preg_match('/^(CREATE|INSERT|UPDATE)/i', $q)) {
                try { $pdo->exec($q); } catch (Exception $e) { /* ignore */ }
            }
        }

        if ($preserveUsers) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $usersCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($usersData as $r) {
                $cols = []; $vals = [];
                foreach ($usersCols as $c) {
                    if (array_key_exists($c, $r)) { $cols[] = "`$c`"; $vals[] = $r[$c] === null ? 'NULL' : $pdo->quote($r[$c]); }
                }
                if (!empty($cols)) try { $pdo->exec('INSERT IGNORE INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'); } catch (Exception $e) { }
            }
            $adminsCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($adminsData as $r) {
                $cols = []; $vals = [];
                foreach ($adminsCols as $c) {
                    if (array_key_exists($c, $r)) { $cols[] = "`$c`"; $vals[] = $r[$c] === null ? 'NULL' : $pdo->quote($r[$c]); }
                }
                if (!empty($cols)) try { $pdo->exec('INSERT IGNORE INTO admins (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'); } catch (Exception $e) { }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $resetTables = array_map(fn($t) => ['table' => $t, 'rows' => 0, 'status' => 'success'], $tables);
        $pdo->exec("CREATE TABLE IF NOT EXISTS backup_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action ENUM('backup','restore','reset') NOT NULL,
            admin_id INT NULL,
            status ENUM('success','failed') NOT NULL DEFAULT 'success',
            filename VARCHAR(255) NULL,
            restore_mode VARCHAR(50) NULL,
            table_status TEXT NULL,
            message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            admin_name VARCHAR(150) NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
        logBackupActivity($pdo, $adminId, 'reset', 'success', $resetTables, null, $resetMode, $preserveUsers ? 'Users and admins preserved' : 'Full reset');
        auditLog($pdo, $adminId, $_SESSION['admin_name'] ?? null, 'reset', "mode=$resetMode" . ($preserveUsers ? ' preserve_users' : ''));

        echo json_encode(['success' => true, 'message' => 'Database reset successfully. Maintenance mode remains on.' . ($preserveUsers ? ' (users preserved)' : '')]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
