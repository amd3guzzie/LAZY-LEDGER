<?php
declare(strict_types=1);

/** Customer side of category requests (staff side lives in StaffController). */
final class RequestController
{
    public static function index(): array
    {
        $user = require_role('customer');
        $rows = q_all(
            'SELECT id, requested_name, requested_type, reason, status, created_at, resolved_at
             FROM category_requests WHERE user_id = ? ORDER BY created_at DESC, id DESC',
            [$user['id']]
        );
        return ['data' => array_map(fn($r) => ['id' => (int) $r['id']] + $r, $rows)];
    }

    public static function store(): array
    {
        $user = require_role('customer');
        $v = new Validator(read_json());
        $name = $v->str('requested_name', 'Category name', 60, min: 2);
        $type = $v->enum('requested_type', 'Category type', ['income', 'expense']);
        $reason = $v->str('reason', 'Reason', 255, required: false);
        $v->done();

        if (q_val(
            'SELECT 1 FROM categories WHERE name = ? AND type = ? AND (is_global = 1 OR user_id = ?)',
            [$name, $type, $user['id']]
        )) {
            fail(409, 'You already have that category.', ['requested_name' => 'That category already exists.']);
        }
        if (q_val(
            'SELECT 1 FROM category_requests WHERE user_id = ? AND requested_name = ? AND requested_type = ? AND status = \'pending\'',
            [$user['id'], $name, $type]
        )) {
            fail(409, 'You already requested that category.', ['requested_name' => 'Already requested and pending review.']);
        }

        q(
            'INSERT INTO category_requests (user_id, requested_name, requested_type, reason) VALUES (?, ?, ?, ?)',
            [$user['id'], $name, $type, $reason]
        );
        return ['id' => (int) db()->lastInsertId()];
    }
}
