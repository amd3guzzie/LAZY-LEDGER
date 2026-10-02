<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Append an entry to the audit log. $actorId is null when nobody is logged in
 * (e.g. a failed login for an unknown email). Never throws: auditing must not break the request.
 */
function audit(?int $actorId, string $action, string $targetType, ?int $targetId, string $details = ''): void
{
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
    } catch (Throwable $e) {
        error_log('[lazyledger] audit insert failed: ' . $e->getMessage());
    }
}
