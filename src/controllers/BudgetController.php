<?php
declare(strict_types=1);

final class BudgetController
{
    /** Budgets for a month (?month=YYYY-MM), each with how much has been spent in its category. */
    public static function index(): array
    {
        $user = require_role('customer');
        [$start, $end, $month] = month_range($_GET['month'] ?? null);
        $budgets = self::forMonth((int) $user['id'], $start, $end);

        $monthly = $user['monthly_budget'] !== null ? (float) $user['monthly_budget'] : null;
        $assigned = round(array_sum(array_column($budgets, 'amount_limit')), 2);
        $spent = (float) q_val(
            'SELECT COALESCE(SUM(t.amount), 0) FROM transactions t JOIN accounts a ON a.id = t.account_id
             WHERE a.user_id = ? AND t.type = \'expense\' AND t.transaction_date BETWEEN ? AND ?',
            [$user['id'], $start, $end]
        );
        $budgeted = $monthly ?? $assigned;

        return [
            'month' => $month,
            'data' => $budgets,
            'summary' => [
                'monthly_budget' => $monthly,
                'budgeted' => $budgeted,
                'assigned' => $assigned,
                'unassigned' => $monthly !== null ? round($monthly - $assigned, 2) : null,
                'spent' => round($spent, 2),
                'pct_used' => $budgeted > 0 ? round($spent / $budgeted * 100, 1) : null,
            ],
        ];
    }

    public static function forMonth(int $userId, string $start, string $end): array
    {
        $rows = q_all(
            'SELECT b.id, b.category_id, c.name AS category_name, b.amount_limit, b.start_date, b.end_date,
                    COALESCE((
                        SELECT SUM(t.amount) FROM transactions t JOIN accounts a ON a.id = t.account_id
                        WHERE a.user_id = b.user_id AND t.category_id = b.category_id AND t.type = \'expense\'
                          AND t.transaction_date BETWEEN b.start_date AND b.end_date
                    ), 0) AS spent
             FROM budgets b
             JOIN categories c ON c.id = b.category_id
             WHERE b.user_id = ? AND b.start_date <= ? AND b.end_date >= ?
             ORDER BY b.amount_limit DESC, c.name',
            [$userId, $end, $start]
        );
        return array_map(function ($r) {
            $limit = (float) $r['amount_limit'];
            $spent = (float) $r['spent'];
            return [
                'id' => (int) $r['id'],
                'category_id' => (int) $r['category_id'],
                'category_name' => $r['category_name'],
                'amount_limit' => $limit,
                'spent' => round($spent, 2),
                'remaining' => round($limit - $spent, 2),
                'pct' => $limit > 0 ? round($spent / $limit * 100, 1) : 0,
                'start_date' => $r['start_date'],
                'end_date' => $r['end_date'],
            ];
        }, $rows);
    }

    public static function store(): array
    {
        $user = require_role('customer');
        $body = read_json();
        $v = new Validator($body);
        $categoryId = $v->int('category_id', 'Category');
        $limit = $v->money('amount_limit', 'Budget amount');
        $v->done();
        [$start, $end] = month_range($body['month'] ?? null);

        CategoryController::assertUsable($categoryId, (int) $user['id'], 'expense');
        if (q_val('SELECT 1 FROM budgets WHERE user_id = ? AND category_id = ? AND start_date = ?', [$user['id'], $categoryId, $start])) {
            fail(409, 'You already have a budget for that category this month.', ['category_id' => 'Budget already exists for this category.']);
        }
        q(
            'INSERT INTO budgets (user_id, category_id, amount_limit, start_date, end_date) VALUES (?, ?, ?, ?, ?)',
            [$user['id'], $categoryId, $limit, $start, $end]
        );
        return ['data' => self::find((int) db()->lastInsertId(), (int) $user['id'])];
    }

    public static function update(int $id): array
    {
        $user = require_role('customer');
        self::find($id, (int) $user['id']);
        $v = new Validator(read_json());
        $limit = $v->money('amount_limit', 'Budget amount');
        $v->done();
        q('UPDATE budgets SET amount_limit = ? WHERE id = ? AND user_id = ?', [$limit, $id, $user['id']]);
        return ['data' => self::find($id, (int) $user['id'])];
    }

    public static function destroy(int $id): array
    {
        $user = require_role('customer');
        self::find($id, (int) $user['id']);
        q('DELETE FROM budgets WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        return ['ok' => true];
    }

    /** Set (or clear with null) the user's total monthly budget. */
    public static function setTotal(): array
    {
        $user = require_role('customer');
        $body = read_json();
        $amount = null;
        if (($body['monthly_budget'] ?? null) !== null && $body['monthly_budget'] !== '') {
            $v = new Validator($body);
            $amount = $v->money('monthly_budget', 'Monthly budget');
            $v->done();
        }
        q('UPDATE users SET monthly_budget = ? WHERE id = ?', [$amount, $user['id']]);
        return ['monthly_budget' => $amount];
    }

    private static function find(int $id, int $userId): array
    {
        $row = q_one('SELECT start_date, end_date FROM budgets WHERE id = ? AND user_id = ?', [$id, $userId]);
        if (!$row) {
            fail(404, 'Budget not found.');
        }
        foreach (self::forMonth($userId, $row['start_date'], $row['end_date']) as $b) {
            if ($b['id'] === $id) {
                return $b;
            }
        }
        fail(404, 'Budget not found.');
    }
}
