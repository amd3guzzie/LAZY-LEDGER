<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Append an entry to the audit log (never updated or deleted). */
function audit(int $actorId, string $action, string $targetType, ?int $targetId, string $details = ''): void
{
    q(
        'INSERT INTO audit_log (actor_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)',
        [$actorId, $action, $targetType, $targetId, mb_substr($details, 0, 255)]
    );
}
