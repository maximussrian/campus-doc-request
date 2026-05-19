<?php
/**
 * Audit logging helper. Call auditLog() after require database.
 */
function auditLog(PDO $pdo, ?int $adminId, ?string $adminName, string $action, ?string $details = null): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = strlen($ip ?? '') > 45 ? substr($ip, 0, 45) : $ip;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (admin_id, admin_name, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, $adminName, $action, $details, $ip]);
    } catch (Throwable $e) { /* ignore */ }
}
