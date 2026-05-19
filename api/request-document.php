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

require_once __DIR__ . '/session_init.php';
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$document_type_id = (int)($input['document_type_id'] ?? 0);
$purpose = sanitizeText($input['purpose'] ?? '', MAX_PURPOSE_LENGTH);
$notes  = sanitizeText($input['notes'] ?? '', MAX_NOTES_LENGTH);

if (!$document_type_id || $purpose === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill document type and purpose']);
    exit;
}

try {
    $pdo = Database::getConnection();

    $pdo->exec("CREATE TABLE IF NOT EXISTS document_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        document_type_id INT NOT NULL,
        department VARCHAR(255) NULL,
        purpose VARCHAR(255) NULL,
        status ENUM('pending', 'processing', 'ready', 'claimed') DEFAULT 'pending',
        notes TEXT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_requests (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Expand column if it was previously too short
    try { $pdo->exec("ALTER TABLE document_requests MODIFY department VARCHAR(255)"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE document_requests MODIFY purpose VARCHAR(255)"); } catch (PDOException $e) {}
    // Ensure users table has department/course (for new installs or pre-migration DBs)
    try { $pdo->exec('ALTER TABLE users ADD COLUMN department VARCHAR(255) NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN course VARCHAR(255) NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN transfer_authorized TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN graduated TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_types ADD COLUMN is_one_time TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_types ADD COLUMN requires_transfer_auth TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}

    // Use department from user profile if already locked; otherwise get from form (first request) and save it
    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT department, course FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    $department = !empty($u['department']) ? $u['department'] : sanitizeText($input['department'] ?? '', MAX_DEPARTMENT_LENGTH);
    if (empty($department)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select your program. This will be recorded as your official program for all future requests.']);
        exit;
    }
    $isFirstProgramSelection = empty($u['department']);

    $stmt = $pdo->prepare('SELECT id, is_one_time, requires_transfer_auth FROM document_types WHERE id = ? AND is_active = 1');
    $stmt->execute([$document_type_id]);
    $docType = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$docType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document type']);
        exit;
    }

    if (!empty($docType['requires_transfer_auth'])) {
        $stmt = $pdo->prepare('SELECT transfer_authorized FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($u['transfer_authorized'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Form 137 is only available for students transferring to another school. Contact the registrar for authorization.']);
            exit;
        }
    }

    if (!empty($docType['is_one_time'])) {
        $stmt = $pdo->prepare('SELECT id FROM document_requests WHERE user_id = ? AND document_type_id = ? AND status = ?');
        $stmt->execute([$userId, $document_type_id, 'claimed']);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This document is issued only once per student and has already been released.']);
            exit;
        }
    }

    // Prevent duplicate: same user cannot request same document type more than once
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM document_requests WHERE user_id = ? AND document_type_id = ?');
    $stmt->execute([$userId, $document_type_id]);
    if ((int)$stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You have already requested this document type. Each document can only be requested once.'
        ]);
        exit;
    }

    $limit = defined('DAILY_REQUEST_LIMIT') ? (int)DAILY_REQUEST_LIMIT : 50;
    $stmt = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE DATE(requested_at) = CURDATE()");
    $todayCount = (int)($stmt->fetchColumn());
    if ($todayCount >= $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Daily limit of ' . $limit . ' requests has been reached. Please try again tomorrow.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO document_requests (user_id, document_type_id, department, purpose, notes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $document_type_id, $department ?: null, $purpose ?: null, $notes ?: null]);

    // Permanently record program on first document request – cannot be changed thereafter
    if ($isFirstProgramSelection && $department) {
        $pdo->prepare('UPDATE users SET department = ?, course = ? WHERE id = ?')
            ->execute([$department, $department, $userId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Document request submitted successfully',
        'request_id' => (int)$pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit request: ' . $e->getMessage()]);
}
