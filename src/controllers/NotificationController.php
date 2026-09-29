<?php
declare(strict_types=1);

final class NotificationController
{
    public static function index(): array
    {
        $user = require_role();
        $rows = q_all(
            'SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 30',
            [$user['id']]
        );
        return [
            'data' => array_map(fn($r) => [
                'id' => (int) $r['id'], 'message' => $r['message'], 'is_read' => (bool) $r['is_read'], 'created_at' => $r['created_at'],
            ], $rows),
            'unread' => (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']]),
        ];
    }

    public static function markRead(int $id): array
    {
        $user = require_role();
        $stmt = q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        if ($stmt->rowCount() === 0 && !q_val('SELECT 1 FROM notifications WHERE id = ? AND user_id = ?', [$id, $user['id']])) {
            fail(404, 'Notification not found.');
        }
        return ['ok' => true];
    }

    public static function markAllRead(): array
    {
        $user = require_role();
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$user['id']]);
        return ['ok' => true];
    }
}
