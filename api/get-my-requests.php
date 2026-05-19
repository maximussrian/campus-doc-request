<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT dr.id, dr.document_type_id, dr.department, dr.purpose, dr.status, dr.notes, dr.requested_at,
               dt.name AS document_name
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        WHERE dr.user_id = ?
        ORDER BY dr.requested_at DESC
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'requests' => $requests]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load requests']);
}
