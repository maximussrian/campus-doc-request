<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/session_init.php';
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/validation_helper.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ── Migrations ──────────────────────────────────────────────────────────────
    try { $pdo->exec("ALTER TABLE chats ADD COLUMN channel ENUM('developer','registrar') NOT NULL DEFAULT 'registrar'"); } catch (PDOException $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS chats (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        student_id  INT NOT NULL,
        channel     ENUM('developer','registrar') NOT NULL DEFAULT 'registrar',
        status      ENUM('open','resolved') DEFAULT 'open',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_channel (channel),
        INDEX idx_status  (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        chat_id     INT NOT NULL,
        sender_type ENUM('student','admin') NOT NULL,
        sender_name VARCHAR(255) NOT NULL,
        message     TEXT NOT NULL,
        is_read     TINYINT(1) DEFAULT 0,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chat   (chat_id),
        INDEX idx_unread (chat_id, sender_type, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registrar_to_developer (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_admin_id INT NOT NULL,
        from_admin_name VARCHAR(255) NOT NULL,
        chat_id INT NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        student_number VARCHAR(64) NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Auth ────────────────────────────────────────────────────────────────────
    $isStudent = !empty($_SESSION['user_id']);
    $isAdmin   = !empty($_SESSION['admin_id']);

    if (!$isStudent && !$isAdmin) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Always resolve admin role from DB for chat — avoids stale session when switching developer/registrar
    $adminRole = '';
    if ($isAdmin) {
        try {
            $ar = $pdo->prepare('SELECT role, name FROM admins WHERE id = ? AND is_active = 1');
            $ar->execute([(int)$_SESSION['admin_id']]);
            $row = $ar->fetch();
            if ($row) {
                $adminRole = strtolower(trim($row['role'] ?? ''));
                $_SESSION['admin_role'] = $adminRole;
                $_SESSION['admin_name'] = $row['name'] ?? $_SESSION['admin_name'] ?? 'Support';
            } else {
                $adminRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
            }
        } catch (PDOException $e) {
            $adminRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
        }
    }
    $adminChannel = ($adminRole === 'developer') ? 'developer' : (in_array($adminRole, ['registrar', 'teller']) ? 'registrar' : null);

    function adminCanAccessChat(PDO $pdo, $chatId, $adminChannel) {
        if (!$adminChannel) return false;
        $stmt = $pdo->prepare("SELECT channel FROM chats WHERE id = ?");
        $stmt->execute([$chatId]);
        $row = $stmt->fetch();
        $ch = isset($row['channel']) ? strtolower(trim($row['channel'])) : '';
        return $ch === $adminChannel;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $input  = [];
    if ($method === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? '';
    } else {
        $action = $_GET['action'] ?? '';
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  STUDENT — Init (get or create open chat)
    // channel: developer = Developer Support, registrar = Document Support
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'init' && $isStudent) {
        $uid = $_SESSION['user_id'];
        $channel = in_array($input['channel'] ?? '', ['developer', 'registrar']) ? $input['channel'] : 'registrar';
        $stmt = $pdo->prepare("SELECT id FROM chats WHERE student_id = ? AND channel = ? AND status = 'open' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$uid, $channel]);
        $chat = $stmt->fetch();
        if (!$chat) {
            $pdo->prepare("INSERT INTO chats (student_id, channel) VALUES (?, ?)")->execute([$uid, $channel]);
            $chatId = (int)$pdo->lastInsertId();
        } else {
            $chatId = (int)$chat['id'];
        }
        $unread = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE chat_id = ? AND sender_type = 'admin' AND is_read = 0");
        $unread->execute([$chatId]);
        echo json_encode(['success' => true, 'chat_id' => $chatId, 'unread' => (int)$unread->fetchColumn()]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  GET MESSAGES
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'messages') {
        $chatId = (int)($_GET['chat_id'] ?? $input['chat_id'] ?? 0);
        $reqChannel = $_GET['channel'] ?? $input['channel'] ?? null;
        if (!$chatId) { echo json_encode(['success' => false, 'message' => 'No chat_id']); exit; }

        // Admin must request their channel explicitly; use req channel if valid, else adminChannel
        $effectiveChannel = $adminChannel;
        if ($isAdmin && in_array($reqChannel, ['developer', 'registrar']) && $reqChannel === $adminChannel) {
            $effectiveChannel = $reqChannel;
        }

        // Grant access: admin first (Support page), then student (student dashboard)
        $viewerIsAdmin = false;
        $access = false;
        if ($isAdmin && adminCanAccessChat($pdo, $chatId, $effectiveChannel)) {
            $access = true;
            $viewerIsAdmin = true;
            $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE chat_id = ? AND sender_type = 'student' AND is_read = 0")->execute([$chatId]);
        }
        if (!$access && $isStudent) {
            $check = $pdo->prepare("SELECT id FROM chats WHERE id = ? AND student_id = ?");
            $check->execute([$chatId, $_SESSION['user_id']]);
            if ($check->fetch()) {
                $access = true;
                $viewerIsAdmin = false;
                $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE chat_id = ? AND sender_type = 'admin' AND is_read = 0")->execute([$chatId]);
            }
        }
        if (!$access) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, sender_type, sender_name, message, is_read, created_at FROM chat_messages WHERE chat_id = ? ORDER BY created_at ASC");
        $stmt->execute([$chatId]);
        $rows = $stmt->fetchAll();
        // Separate recipient logic: server assigns 'side' per viewer (admin=right/out, student=left/in for own messages)
        $messages = array_map(function ($m) use ($viewerIsAdmin) {
            $m['side'] = ($m['sender_type'] === 'admin') === $viewerIsAdmin ? 'out' : 'in';
            return $m;
        }, $rows);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  SEND MESSAGE
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'send' && $method === 'POST') {
        $chatId  = (int)($input['chat_id'] ?? 0);
        $message = sanitizeText($input['message'] ?? '', MAX_CHAT_MESSAGE_LENGTH);
        $reqChannel = $input['channel'] ?? null;
        if (!$chatId || $message === '') { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

        $senderType = null;
        $senderName = null;
        $fromAdminUI = !empty($input['as_admin']);
        // Admin send: use requested channel if valid and matches admin's channel
        $effectiveChannel = $adminChannel;
        if ($fromAdminUI && $isAdmin && in_array($reqChannel, ['developer', 'registrar']) && $reqChannel === $adminChannel) {
            $effectiveChannel = $reqChannel;
        }
        // NO as_admin = student dashboard request — ONLY student, NEVER admin (even if session has admin_id)
        if (!$fromAdminUI) {
            if ($isStudent) {
                $check = $pdo->prepare("SELECT id FROM chats WHERE id = ? AND student_id = ?");
                $check->execute([$chatId, $_SESSION['user_id']]);
                if ($check->fetch()) {
                    $senderType = 'student';
                    $ns = $pdo->prepare("SELECT CONCAT(names,' ',surnames) as n FROM users WHERE id = ?");
                    $ns->execute([$_SESSION['user_id']]);
                    $senderName = $ns->fetchColumn() ?: 'Student';
                }
            }
        } else {
            // as_admin=true = Developer/Registrar dashboard — only then use admin
            if ($isAdmin && adminCanAccessChat($pdo, $chatId, $effectiveChannel)) {
                $senderType = 'admin';
                $senderName = $_SESSION['admin_name'] ?? 'Support';
            }
        }
        if (!$senderType) {
            http_response_code(403);
            $errMsg = !$isAdmin ? 'Not authenticated. Please log in again.'
                : (!$adminChannel ? 'Your session role could not be verified. Please log out and log in again.' : 'Access denied to this chat.');
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit;
        }

        $pdo->prepare("INSERT INTO chat_messages (chat_id, sender_type, sender_name, message) VALUES (?,?,?,?)")
            ->execute([$chatId, $senderType, $senderName, $message]);
        $msgId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE chats SET updated_at = NOW(), status = 'open' WHERE id = ?")->execute([$chatId]);

        $ms = $pdo->prepare("SELECT id, sender_type, sender_name, message, is_read, created_at FROM chat_messages WHERE id = ?");
        $ms->execute([$msgId]);
        echo json_encode(['success' => true, 'message' => $ms->fetch()]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  ADMIN — List all chats (filtered by channel: developer=developer, registrar/teller=registrar)
    //  Accept channel param to enforce separation; must match admin's allowed channel
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'list' && $isAdmin) {
        if (!$adminChannel) {
            echo json_encode(['success' => true, 'chats' => []]);
            exit;
        }
        $reqChannel = $_GET['channel'] ?? $input['channel'] ?? null;
        $listChannel = $adminChannel;
        if (in_array($reqChannel, ['developer', 'registrar']) && $reqChannel === $adminChannel) {
            $listChannel = $reqChannel;
        }
        $stmt = $pdo->prepare("
            SELECT c.id, c.status, c.updated_at,
                   u.names, u.surnames, u.student_number, u.email,
                   (SELECT COUNT(*) FROM chat_messages m
                    WHERE m.chat_id = c.id AND m.sender_type = 'student' AND m.is_read = 0) AS unread,
                   (SELECT message   FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                   (SELECT created_at FROM chat_messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_at
            FROM chats c
            JOIN users u ON u.id = c.student_id
            WHERE c.channel = ?
            ORDER BY (CASE WHEN c.status='open' THEN 0 ELSE 1 END), c.updated_at DESC
        ");
        $stmt->execute([$listChannel]);
        echo json_encode(['success' => true, 'chats' => $stmt->fetchAll()]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  ADMIN — Resolve / Reopen chat
    // ════════════════════════════════════════════════════════════════════════════
    if (in_array($action, ['resolve','reopen']) && $isAdmin && $method === 'POST') {
        $chatId = (int)($input['chat_id'] ?? 0);
        $reqChannel = $input['channel'] ?? null;
        if (!$chatId) { echo json_encode(['success' => false, 'message' => 'No chat_id']); exit; }
        $effChannel = $adminChannel;
        if (in_array($reqChannel, ['developer', 'registrar']) && $reqChannel === $adminChannel) {
            $effChannel = $reqChannel;
        }
        if (!adminCanAccessChat($pdo, $chatId, $effChannel)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $newStatus = ($action === 'resolve') ? 'resolved' : 'open';
        $pdo->prepare("UPDATE chats SET status = ? WHERE id = ?")->execute([$newStatus, $chatId]);
        echo json_encode(['success' => true, 'status' => $newStatus]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  STUDENT — Unread count
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'unread' && $isStudent) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages m
            JOIN chats c ON c.id = m.chat_id
            WHERE c.student_id = ? AND m.sender_type = 'admin' AND m.is_read = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true, 'unread' => (int)$stmt->fetchColumn()]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  ADMIN — Total unread count
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'admin_unread' && $isAdmin) {
        if (!$adminChannel) {
            echo json_encode(['success' => true, 'unread' => 0]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages m
            JOIN chats c ON c.id = m.chat_id
            WHERE c.channel = ? AND m.sender_type = 'student' AND m.is_read = 0
        ");
        $stmt->execute([$adminChannel]);
        echo json_encode(['success' => true, 'unread' => (int)$stmt->fetchColumn()]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  REGISTRAR/TELLER — Send feedback to Developer
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'registrar_feedback' && $isAdmin && $method === 'POST') {
        $msg = sanitizeText($input['message'] ?? '', MAX_CHAT_MESSAGE_LENGTH);
        if ($msg === '') { echo json_encode(['success' => false, 'message' => 'Message is required']); exit; }
        if (!in_array($adminRole, ['registrar', 'teller'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $adminName = $_SESSION['admin_name'] ?? 'Registrar';
        $adminId   = (int)($_SESSION['admin_id'] ?? 0);
        $pdo->prepare("INSERT INTO registrar_to_developer (from_admin_id, from_admin_name, chat_id, student_name, student_number, message) VALUES (?,?,0,?,?,?)")
            ->execute([$adminId, $adminName, $adminName . ' (feedback)', '', $msg]);
        echo json_encode(['success' => true, 'message' => 'Feedback sent to Developer']);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════════
    //  DEVELOPER — Fetch messages from Registrar (feedback)
    // ════════════════════════════════════════════════════════════════════════════
    if ($action === 'registrar_messages' && $isAdmin && $adminChannel === 'developer') {
        $stmt = $pdo->prepare("SELECT id, from_admin_name, chat_id, student_name, student_number, message, created_at FROM registrar_to_developer ORDER BY created_at DESC LIMIT 100");
        $stmt->execute();
        echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
