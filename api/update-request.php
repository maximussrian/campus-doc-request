<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
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
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$document_type_id = (int)($input['document_type_id'] ?? 0);
$purpose = sanitizeText($input['purpose'] ?? '', MAX_PURPOSE_LENGTH);
$notes  = sanitizeText($input['notes'] ?? '', MAX_NOTES_LENGTH);

if (!$id || !$document_type_id || !$purpose) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

try {
    $pdo = Database::getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Use department from user profile (locked at registration)
$userId = (int)$userId;
$stmt = $pdo->prepare('SELECT department FROM users WHERE id = ?');
$stmt->execute([$userId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
$department = !empty($u['department']) ? $u['department'] : sanitizeText($input['department'] ?? '', MAX_DEPARTMENT_LENGTH);
if (empty($department)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Program/course is required. Contact the registrar if your account does not have this set.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM document_requests WHERE id = ? AND user_id = ? AND status = ?');
    $stmt->execute([$id, $userId, 'pending']);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Request not found or cannot be edited (only pending requests can be edited)']);
        exit;
    }

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
            echo json_encode(['success' => false, 'message' => 'Form 137 requires transfer authorization from the registrar.']);
            exit;
        }
    }

    if (!empty($docType['is_one_time'])) {
        $stmt = $pdo->prepare('SELECT id FROM document_requests WHERE user_id = ? AND document_type_id = ? AND status = ?');
        $stmt->execute([$userId, $document_type_id, 'claimed']);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This document is issued only once and has already been released.']);
            exit;
        }
    }

    // Prevent duplicate: cannot change to a document type already requested
    $stmt = $pdo->prepare('SELECT id FROM document_requests WHERE user_id = ? AND document_type_id = ? AND id != ?');
    $stmt->execute([$userId, $document_type_id, $id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You have already requested this document type. Each document can only be requested once.']);
        exit;
    }

    $pdo->prepare('UPDATE document_requests SET document_type_id = ?, department = ?, purpose = ?, notes = ? WHERE id = ? AND user_id = ?')
        ->execute([$document_type_id, $department ?: null, $purpose ?: null, $notes ?: null, $id, $userId]);

    echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update request']);
}
