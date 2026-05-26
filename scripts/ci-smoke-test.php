<?php
/**
 * CI smoke tests for US-08: View Monthly Total Requests.
 * Runs without a live MySQL connection.
 */
declare(strict_types=1);

require_once __DIR__ . '/../api/validation_helper.php';

$failures = 0;

function assertTrue(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        $failures++;
    }
}

// Month validation (admin month/year picker input)
assertTrue(validateMonth('2026-05') === '2026-05', 'Valid month YYYY-MM is accepted');
assertTrue(validateMonth('2026-13') === '', 'Invalid month number is rejected');
assertTrue(validateMonth('2026/05') === '', 'Wrong month format is rejected');
assertTrue(validateMonth('') === '', 'Empty month is rejected');

// Monthly count query logic (SQLite stand-in for MySQL DATE_FORMAT)
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE document_requests (id INTEGER PRIMARY KEY, requested_at TEXT NOT NULL)');
$pdo->exec("INSERT INTO document_requests (requested_at) VALUES ('2026-05-01 09:00:00')");
$pdo->exec("INSERT INTO document_requests (requested_at) VALUES ('2026-05-20 14:30:00')");
$pdo->exec("INSERT INTO document_requests (requested_at) VALUES ('2026-04-30 23:59:59')");
$pdo->exec("INSERT INTO document_requests (requested_at) VALUES ('2026-06-01 00:00:01')");

$countFor = static function (PDO $pdo, string $month): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE strftime('%Y-%m', requested_at) = ?");
    $stmt->execute([$month]);
    return (int) $stmt->fetchColumn();
};

assertTrue($countFor($pdo, '2026-05') === 2, 'May 2026 total is accurate (2 requests)');
assertTrue($countFor($pdo, '2026-04') === 1, 'April 2026 total is accurate (1 request)');
assertTrue($countFor($pdo, '2026-01') === 0, 'Empty month returns zero (A1: no records)');

// API endpoint must exist and be syntactically valid
$apiFile = __DIR__ . '/../api/admin-get-report-summary.php';
assertTrue(is_file($apiFile), 'Monthly total API file exists');
$syntaxCheck = shell_exec(PHP_BINARY . ' -l ' . escapeshellarg($apiFile) . ' 2>&1') ?? '';
assertTrue(str_contains($syntaxCheck, 'No syntax errors'), 'Monthly total API passes PHP syntax check');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} smoke test(s) failed.\n");
    exit(1);
}

echo "US-08 monthly total smoke tests passed.\n";
