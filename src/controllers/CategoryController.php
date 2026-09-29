<?php
declare(strict_types=1);

final class CategoryController
{
    /** Categories visible to a customer: global ones plus their own approved custom ones. */
    public static function index(): array
    {
        $user = require_role('customer');
        return ['data' => self::visibleTo((int) $user['id'])];
    }

    public static function visibleTo(int $userId): array
    {
        $rows = q_all(
            'SELECT id, name, type, is_global FROM categories
             WHERE is_global = 1 OR user_id = ?
             ORDER BY type DESC, name',
            [$userId]
        );
        return array_map(fn($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'type' => $r['type'],
            'is_global' => (bool) $r['is_global'],
        ], $rows);
    }

    /** Ensure a category is usable by this user (and optionally of a given type); 422 otherwise. */
    public static function assertUsable(int $categoryId, int $userId, ?string $type = null): array
    {
        $row = q_one(
            'SELECT id, name, type FROM categories WHERE id = ? AND (is_global = 1 OR user_id = ?)',
            [$categoryId, $userId]
        );
        if (!$row) {
            fail(422, 'Please choose a valid category.', ['category_id' => 'Please choose a valid category.']);
        }
        if ($type !== null && $row['type'] !== $type) {
            fail(422, "That category is for {$row['type']}, not {$type}.", ['category_id' => 'Category type does not match.']);
        }
        return $row;
    }
}
