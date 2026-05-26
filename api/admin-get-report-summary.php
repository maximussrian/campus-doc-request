<?php
header('Content-Type: application/json');
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar', 'teller']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/validation_helper.php';

$date  = validateDate($_GET['date'] ?? '');
$month = validateMonth($_GET['month'] ?? '');

if (($_SESSION['admin_role'] ?? '') === 'registrar') {
    if ($date) {
        requirePermission('manage_requests');
    } elseif ($month) {
        requirePermission('export_reports');
    }
}

$deptWhere = '';
$deptWhereDr = '';
$deptParams = [];
if (($_SESSION['admin_role'] ?? '') === 'teller') {
    $pdo = Database::getConnection();
    $permRow = $pdo->prepare('SELECT permissions FROM admins WHERE id = ? AND is_active = 1');
    $permRow->execute([(int)($_SESSION['admin_id'] ?? 0)]);
    $permJson = $permRow->fetchColumn();
    $perm = $permJson ? json_decode($permJson, true) : null;
    $depts = (is_array($perm) && isset($perm['assigned_departments']) && is_array($perm['assigned_departments']))
        ? array_values(array_filter(array_map('trim', $perm['assigned_departments']))) : [];
    if (!empty($depts)) {
        $phs = implode(',', array_fill(0, count($depts), '?'));
        $deptWhere = " AND department IN ($phs)";
        $deptWhereDr = " AND dr.department IN ($phs)";
        $deptParams = $depts;
    }
}

try {
    $pdo = Database::getConnection();
    $limit = defined('DAILY_REQUEST_LIMIT') ? (int)DAILY_REQUEST_LIMIT : 50;

    if ($date) {
        $d = $date;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM document_requests
            WHERE DATE(requested_at) = ? {$deptWhere}
        ");
        $stmt->execute(array_merge([$d], $deptParams));
        $total = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT dt.name AS document_name, COUNT(*) AS count
            FROM document_requests dr
            JOIN document_types dt ON dt.id = dr.document_type_id
            WHERE DATE(dr.requested_at) = ? {$deptWhereDr}
            GROUP BY dr.document_type_id
            ORDER BY count DESC
        ");
        $stmt->execute(array_merge([$d], $deptParams));
        $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'date' => $d,
            'total' => $total,
            'limit' => $limit,
            'breakdown' => $breakdown,
        ]);
        exit;
    }

    if ($month) {
        $m = $month;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM document_requests
            WHERE DATE_FORMAT(requested_at, '%Y-%m') = ? {$deptWhere}
        ");
        $stmt->execute(array_merge([$m], $deptParams));
        $total = (int)$stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'month' => $m,
            'total' => $total,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Provide date or month (YYYY-MM-DD or YYYY-MM)']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
