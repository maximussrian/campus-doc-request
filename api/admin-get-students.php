<?php
header('Content-Type: application/json');
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar', 'teller']);
requirePermission('view_students');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo  = Database::getConnection();
    try { $pdo->exec('ALTER TABLE users ADD COLUMN transfer_authorized TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN graduated TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (PDOException $e) {}

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.student_number,
            u.names,
            u.surnames,
            u.email,
            u.department AS program,
            u.is_active,
            u.transfer_authorized,
            u.graduated,
            u.created_at,
            COUNT(dr.id)                                         AS total_requests,
            SUM(dr.status = 'pending')                           AS pending,
            SUM(dr.status = 'processing')                        AS processing,
            SUM(dr.status = 'ready')                             AS ready,
            SUM(dr.status = 'claimed')                           AS claimed
        FROM users u
        LEFT JOIN document_requests dr ON dr.user_id = u.id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'students' => $students]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
