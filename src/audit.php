<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function audit(int $actorId, string $action, string $targetType, ?int $targetId, string $details = ''): void
{
    // 1. Tell Railway the function started
    error_log("AUDIT TRACE: Function fired for User ID: $actorId, Action: $action");

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (str_contains($ip, ',')) {
        $ip = explode(',', $ip)[0];
    }
    $ip = trim($ip);
    
    $logDetails = $details === '' ? "IP: $ip" : "$details (IP: $ip)";

    try {
        q(
            'INSERT INTO audit_log (actor_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)',
            [$actorId, $action, $targetType, $targetId, mb_substr($logDetails, 0, 255)]
        );
        // 2. Tell Railway the query succeeded
        error_log("AUDIT TRACE: Database insert successful.");
    } catch (Throwable $e) {
        // 3. Catch any hidden SQL errors and print them!
        error_log("AUDIT TRACE SQL ERROR: " . $e->getMessage());
    }
}