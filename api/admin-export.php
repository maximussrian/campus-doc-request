<?php
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['developer', 'registrar', 'teller']);
requirePermission('export_reports');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$format   = validateExportFormat($_GET['format'] ?? 'csv');
$status   = validateRequestStatus($_GET['status'] ?? '');
$dateFrom = validateDate($_GET['date_from'] ?? '');
$dateTo   = validateDate($_GET['date_to'] ?? '');

// Build WHERE clause
$where  = [];
$params = [];

if ($status) {
    $where[]  = 'dr.status = ?';
    $params[] = $status;
}
if ($dateFrom) {
    $where[]  = 'DATE(dr.requested_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[]  = 'DATE(dr.requested_at) <= ?';
    $params[] = $dateTo;
}
// Department-based RBAC for tellers (fetch from DB to avoid session staleness)
if (($_SESSION['admin_role'] ?? '') === 'teller') {
    $pdoConn = Database::getConnection();
    $permRow = $pdoConn->prepare('SELECT permissions FROM admins WHERE id = ? AND is_active = 1');
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

$whereSQL = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $pdo  = Database::getConnection();
    $sql  = "
        SELECT
            dr.id                                     AS request_id,
            u.student_number                          AS student_number,
            CONCAT(u.names, ' ', u.surnames)          AS full_name,
            u.email                                   AS email,
            dt.name                                   AS document_type,
            dr.department                             AS program,
            dr.purpose                                AS purpose,
            dr.notes                                  AS notes,
            dr.status                                 AS status,
            DATE_FORMAT(dr.requested_at, '%Y-%m-%d')  AS date_requested,
            DATE_FORMAT(dr.updated_at,   '%Y-%m-%d')  AS last_updated
        FROM document_requests dr
        JOIN users u          ON u.id  = dr.user_id
        JOIN document_types dt ON dt.id = dr.document_type_id
        $whereSQL
        ORDER BY dr.requested_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
    exit;
}

// Human-readable column headers
$colHeaders = [
    'request_id'     => 'Request ID',
    'student_number' => 'Student Number',
    'full_name'      => 'Full Name',
    'email'          => 'Email',
    'document_type'  => 'Document Type',
    'program'        => 'Program',
    'purpose'        => 'Purpose',
    'notes'          => 'Notes',
    'status'         => 'Status',
    'date_requested' => 'Date Requested',
    'last_updated'   => 'Last Updated',
];

// Filename (dates are validated, safe for use in filename)
$label = 'document_requests';
if ($dateFrom && $dateTo)  $label .= '_' . preg_replace('/[^0-9\-]/', '', $dateFrom) . '_to_' . preg_replace('/[^0-9\-]/', '', $dateTo);
elseif ($dateFrom)         $label .= '_from_' . preg_replace('/[^0-9\-]/', '', $dateFrom);
elseif ($dateTo)           $label .= '_until_' . preg_replace('/[^0-9\-]/', '', $dateTo);
$label .= '_' . date('Ymd_His');

// Helper: escape for XML
function xmlEsc($val) {
    return htmlspecialchars((string)($val !== null ? $val : ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/* ════════════════════════════════
   CSV
════════════════════════════════ */
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $label . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, array_values($colHeaders));
    foreach ($rows as $row) {
        $line = [];
        foreach (array_keys($colHeaders) as $key) {
            $line[] = isset($row[$key]) ? $row[$key] : '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/* ════════════════════════════════
   Excel — SpreadsheetML (.xls)
   Opens natively in Excel & LibreOffice
════════════════════════════════ */
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $label . '.xls"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$statusColors = [
    'pending'    => 'FFFDE7',
    'processing' => 'E3F2FD',
    'ready'      => 'E8F5E9',
    'claimed'    => 'ECEFF1',
];

$colWidths = [60, 100, 140, 180, 200, 200, 160, 160, 80, 100, 100];

// Output XML — avoid any whitespace before the declaration
ob_end_clean();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo '          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo '          xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";

// Styles
echo '<Styles>' . "\n";
echo '  <Style ss:ID="header">' . "\n";
echo '    <Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>' . "\n";
echo '    <Interior ss:Color="#DD0426" ss:Pattern="Solid"/>' . "\n";
echo '    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>' . "\n";
echo '  </Style>' . "\n";
echo '  <Style ss:ID="row_default">' . "\n";
echo '    <Alignment ss:Vertical="Center" ss:WrapText="1"/>' . "\n";
echo '    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/></Borders>' . "\n";
echo '  </Style>' . "\n";
foreach ($statusColors as $st => $color) {
    echo '  <Style ss:ID="status_' . $st . '">' . "\n";
    echo '    <Font ss:Bold="1"/>' . "\n";
    echo '    <Interior ss:Color="#' . $color . '" ss:Pattern="Solid"/>' . "\n";
    echo '    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
    echo '  </Style>' . "\n";
}
echo '</Styles>' . "\n";

echo '<Worksheet ss:Name="Document Requests">' . "\n";
echo '<Table>' . "\n";

// Column widths
foreach ($colWidths as $w) {
    echo '<Column ss:Width="' . $w . '"/>' . "\n";
}

// Header row
echo '<Row ss:Height="28">' . "\n";
foreach ($colHeaders as $label_h) {
    echo '<Cell ss:StyleID="header"><Data ss:Type="String">' . xmlEsc($label_h) . '</Data></Cell>' . "\n";
}
echo '</Row>' . "\n";

// Data rows
$keys = array_keys($colHeaders);
foreach ($rows as $row) {
    $st = strtolower(isset($row['status']) ? $row['status'] : '');
    echo '<Row ss:Height="20">' . "\n";
    foreach ($keys as $i => $key) {
        $val       = isset($row[$key]) ? $row[$key] : '';
        $cellStyle = ($key === 'status' && isset($statusColors[$st])) ? 'status_' . $st : 'row_default';
        echo '<Cell ss:StyleID="' . $cellStyle . '"><Data ss:Type="String">' . xmlEsc($val) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
}

echo '</Table>' . "\n";
echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">' . "\n";
echo '  <FreezePanes/><FrozenNoSplit/>' . "\n";
echo '  <SplitHorizontal>1</SplitHorizontal>' . "\n";
echo '  <TopRowBottomPane>1</TopRowBottomPane>' . "\n";
echo '  <ActivePane>2</ActivePane>' . "\n";
echo '</WorksheetOptions>' . "\n";
echo '</Worksheet>' . "\n";
echo '</Workbook>' . "\n";
exit;
