<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/audit.php';

final class ReviewController
{
    /** GET /reviews - latest reviews with author name + date, and the average rating. */
    public static function index(): array
    {
        $user = require_role('customer');
        $rows = q_all(
            "SELECT r.rating, r.comment, r.updated_at, r.user_id,
                    CONCAT(u.first_name, IF(u.last_name = '', '', CONCAT(' ', LEFT(u.last_name, 1), '.'))) AS author
             FROM reviews r JOIN users u ON u.id = r.user_id
             ORDER BY r.updated_at DESC LIMIT 50"
        );
        $rows = array_map(function (array $r) use ($user): array {
            $r['mine'] = (int) $r['user_id'] === (int) $user['id'];
            unset($r['user_id']);
            return $r;
        }, $rows);
        $summary = q_one('SELECT COUNT(*) AS total, ROUND(AVG(rating), 1) AS average FROM reviews');
        return [
            'data' => $rows,
            'summary' => [
                'total' => (int) $summary['total'],
                'average' => $summary['average'] !== null ? (float) $summary['average'] : null,
            ],
        ];
    }

    /** POST /reviews - create, or update the customer's existing review. */
    public static function store(): array
    {
        $user = require_role('customer');
        $v = new Validator(read_json());
        $rating = $v->intRange('rating', 'Rating', 1, 5);
        $comment = $v->str('comment', 'Comment', 500, true, 5);
        $v->done();

        q(
            'INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)',
            [$user['id'], $rating, $comment]
        );
        audit((int) $user['id'], 'review.save', 'review', null, "rating $rating");
        return ['ok' => true, 'message' => 'Thanks for your feedback!'];
    }
}