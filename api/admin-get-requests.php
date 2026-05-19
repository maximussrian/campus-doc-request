<?php
header('Content-Type: application/json');
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar', 'teller']);
// Registrar must have manage_requests; tellers always have handle_requests (always ON)
if (($_SESSION['admin_role'] ?? '') === 'registrar') requirePermission('manage_requests');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

try {
    $pdo = Database::getConnection();
    try { $pdo->exec('ALTER TABLE document_requests ADD COLUMN processed_by_admin_id INT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_requests ADD COLUMN processed_by_name VARCHAR(150) NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_requests ADD COLUMN processed_at DATETIME NULL'); } catch (PDOException $e) {}
    $status = validateRequestStatus($_GET['status'] ?? '');
    $where  = [];
    $params = [];

    if ($status) {
        $where[] = 'dr.status = ?';
        $params[] = $status;
    }

    // Department-based RBAC: tellers see only requests for their assigned departments
    $role = $_SESSION['admin_role'] ?? '';
    $depts = [];
    if ($role === 'teller') {
        // Always fetch from DB to avoid session staleness
        $permRow = $pdo->prepare('SELECT permissions FROM admins WHERE id = ? AND is_active = 1');
        $permRow->execute([(int)($_SESSION['admin_id'] ?? 0)]);
        $permJson = $permRow->fetchColumn();
        $perm = $permJson ? json_decode($permJson, true) : null;
        $depts = (is_array($perm) && isset($perm['assigned_departments']) && is_array($perm['assigned_departments']))
            ? array_values(array_filter(array_map('trim', $perm['assigned_departments']))) : [];
        if (!empty($depts)) {
            $placeholders = implode(',', array_fill(0, count($depts), '?'));
            $where[] = "dr.department IN ($placeholders)";
            $params = array_merge($params, $depts);
        }
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderBy = ($status === 'pending') ? 'dr.requested_at ASC' : 'dr.requested_at DESC';
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.status, dr.department, dr.purpose, dr.notes, dr.requested_at, dr.updated_at,
               dr.processed_by_admin_id, dr.processed_by_name, dr.processed_at,
               u.names, u.surnames, u.student_number, u.email,
               dt.name AS document_name
        FROM document_requests dr
        JOIN users u ON u.id = dr.user_id
        JOIN document_types dt ON dt.id = dr.document_type_id
        $whereClause
        ORDER BY $orderBy
    ");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
