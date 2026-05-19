<?php
header('Content-Type: application/json');
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar', 'teller']);
if (($_SESSION['admin_role'] ?? '') === 'registrar') requirePermission('manage_requests');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$days = max(1, min(90, (int)($_GET['days'] ?? 14)));
$limit = defined('DAILY_REQUEST_LIMIT') ? (int)DAILY_REQUEST_LIMIT : 50;

$deptWhere = '';
$deptParams = [];
if (($_SESSION['admin_role'] ?? '') === 'teller') {
    $pdoDept = Database::getConnection();
    $permRow = $pdoDept->prepare('SELECT permissions FROM admins WHERE id = ? AND is_active = 1');
    $permRow->execute([(int)($_SESSION['admin_id'] ?? 0)]);
    $permJson = $permRow->fetchColumn();
    $perm = $permJson ? json_decode($permJson, true) : null;
    $depts = (is_array($perm) && isset($perm['assigned_departments']) && is_array($perm['assigned_departments']))
        ? array_values(array_filter(array_map('trim', $perm['assigned_departments']))) : [];
    if (!empty($depts)) {
        $phs = implode(',', array_fill(0, count($depts), '?'));
        $deptWhere = " AND department IN ($phs)";
        $deptParams = $depts;
    }
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT DATE(requested_at) AS request_date, COUNT(*) AS total
        FROM document_requests
        WHERE requested_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) {$deptWhere}
        GROUP BY DATE(requested_at)
        ORDER BY request_date DESC
    ");
    $stmt->execute(array_merge([$days], $deptParams));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $todayCount = 0;
    foreach ($rows as &$row) {
        $row['total'] = (int)$row['total'];
        $row['limit'] = $limit;
        $row['at_limit'] = $row['total'] >= $limit;
        if ($row['request_date'] === date('Y-m-d')) {
            $todayCount = $row['total'];
        }
    }

    echo json_encode([
        'success' => true,
        'daily' => $rows,
        'today_count' => $todayCount,
        'limit' => $limit
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
