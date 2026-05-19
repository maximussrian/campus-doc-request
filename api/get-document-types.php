<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../config/database.php';

session_start();

try {
    $pdo = Database::getConnection();

    // Migrations for existing DBs
    try { $pdo->exec('ALTER TABLE document_types ADD COLUMN is_one_time TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE document_types ADD COLUMN requires_transfer_auth TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN transfer_authorized TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE users ADD COLUMN graduated TINYINT(1) DEFAULT 0'); } catch (PDOException $e) {}

    // Create document_types if not exists and seed
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        is_one_time TINYINT(1) DEFAULT 0,
        requires_transfer_auth TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("INSERT IGNORE INTO document_types (name, slug, description) VALUES
        ('Official Transcript of Records (TOR)', 'tor', 'For employment, board exams, promotions, further studies, or international purposes'),
        ('Diploma', 'diploma', 'Upon graduation'),
        ('Certificate of Enrollment (Active)', 'cert-enrollment-active', 'Verification of active student status'),
        ('Certificate of Enrollment (Inactive)', 'cert-enrollment-inactive', 'Verification of inactive student status'),
        ('Good Moral Character Certificate', 'good-moral', 'Character certification'),
        ('Authentication and Verification of Documents', 'auth-verification', 'Authentication and verification of academic documents'),
        ('Certificate of Grades (COG)', 'cert-grades', 'Certificate of Grades'),
        ('GWA Certificate', 'gwa', 'General Weighted Average certificate'),
        ('Copy of Certificate of Registration (COR)', 'cor', 'Copy of Certificate of Registration'),
        ('Transfer Credentials', 'transfer-credentials', 'Credentials for transfer to another school'),
        ('Graduation Documents', 'graduation-docs', 'Graduation-related documents'),
        ('Form 137 (Permanent Record)', 'form-137', 'Strictly controlled; only for students transferring. Requires institutional authorization.')");

    $pdo->exec("UPDATE document_types SET is_one_time = 1 WHERE slug = 'diploma'");
    $pdo->exec("UPDATE document_types SET requires_transfer_auth = 1 WHERE slug = 'form-137'");

    $stmt = $pdo->query('SELECT id, name, slug, description, is_one_time, requires_transfer_auth FROM document_types WHERE is_active = 1 ORDER BY name');
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If student is logged in, mark restricted types as unavailable (still shown, but disabled)
    if (!empty($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT transfer_authorized, graduated FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        $transferAuthorized = !empty($u['transfer_authorized']);
        $graduated = !empty($u['graduated']);

        $stmt = $pdo->prepare('SELECT document_type_id FROM document_requests WHERE user_id = ? AND status = ?');
        $stmt->execute([$userId, 'claimed']);
        $claimedDocIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'document_type_id');

        foreach ($types as &$t) {
            $t['unavailable'] = 0;
            $t['unavailable_reason'] = null;
            if (!empty($t['is_one_time']) && in_array((int)$t['id'], $claimedDocIds)) {
                $t['unavailable'] = 1;
                $t['unavailable_reason'] = 'Already released (one-time issuance)';
            } elseif (!empty($t['requires_transfer_auth']) && !$transferAuthorized) {
                $t['unavailable'] = 1;
                $t['unavailable_reason'] = 'Requires transfer authorization from registrar';
            }
        }
        unset($t);
    }

    echo json_encode(['success' => true, 'document_types' => $types]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load document types']);
}
