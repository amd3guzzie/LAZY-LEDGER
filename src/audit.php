<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Append an entry to the audit log (never updated or deleted). */
function audit(int $actorId, string $action, string $targetType, ?int $targetId, string $details = ''): void
{
    // Capture the real IP from the proxy headers (required for Railway)
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (str_contains($ip, ',')) {
        $ip = explode(',', $ip)[0];
    }
    $ip = trim($ip);
    
    // Append the IP address to the details
    $logDetails = $details === '' ? "IP: $ip" : "$details (IP: $ip)";

    q(
        'INSERT INTO audit_log (actor_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)',
        [$actorId, $action, $targetType, $targetId, mb_substr($logDetails, 0, 255)]
    );
}