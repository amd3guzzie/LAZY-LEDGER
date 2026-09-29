<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Create an in-app notification for a user. */
function notify(int $userId, string $message): void
{
    q('INSERT INTO notifications (user_id, message) VALUES (?, ?)', [$userId, mb_substr($message, 0, 255)]);
}
