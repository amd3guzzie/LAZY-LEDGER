<?php
declare(strict_types=1);

final class StatsController
{
    /** Everything the customer Home tab needs for one month. */
    public static function summary(): array
    {
        $user = require_role('customer');
        $uid = (int) $user['id'];
        [$start, $end, $month] = month_range($_GET['month'] ?? null);
        $prevStart = (new DateTimeImmutable($start))->modify('-1 month')->format('Y-m-d');
        $prevEnd = (new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');

        $cur = self::totals($uid, $start, $end);
        $prev = self::totals($uid, $prevStart, $prevEnd);

        $budgets = BudgetController::forMonth($uid, $start, $end);
        $assigned = array_sum(array_column($budgets, 'amount_limit'));
        $budgeted = $user['monthly_budget'] !== null ? (float) $user['monthly_budget'] : $assigned;

        $recent = q_all(
            'SELECT t.id, t.type, t.amount, t.description, t.transaction_date, c.name AS category_name, a.name AS account_name
             FROM transactions t JOIN accounts a ON a.id = t.account_id LEFT JOIN categories c ON c.id = t.category_id
             WHERE a.user_id = ? ORDER BY t.transaction_date DESC, t.id DESC LIMIT 5',
            [$uid]
        );
        $upcoming = q_all(
            'SELECT a.id, a.name, a.due_date, ' . AccountController::BALANCE_SQL . ' AS balance
             FROM accounts a LEFT JOIN transactions t ON t.account_id = a.id
             WHERE a.user_id = ? AND a.type = \'credit_card\' AND a.due_date IS NOT NULL
               AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             GROUP BY a.id ORDER BY a.due_date',
            [$uid]
        );
        $counts = q_one(
            'SELECT (SELECT COUNT(*) FROM accounts WHERE user_id = :u1) AS accounts,
                    (SELECT COUNT(*) FROM budgets WHERE user_id = :u2) AS budgets,
                    (SELECT COUNT(*) FROM transactions t JOIN accounts a ON a.id = t.account_id WHERE a.user_id = :u3) AS transactions',
            ['u1' => $uid, 'u2' => $uid, 'u3' => $uid]
        );

        return [
            'month' => $month,
            'income' => $cur['income'],
            'expense' => $cur['expense'],
            'income_change' => self::pctChange($prev['income'], $cur['income']),
            'expense_change' => self::pctChange($prev['expense'], $cur['expense']),
            'budgeted' => round($budgeted, 2),
            'left_to_spend' => round($budgeted - $cur['expense'], 2),
            'pct_used' => $budgeted > 0 ? round($cur['expense'] / $budgeted * 100, 1) : null,
            'budgets' => $budgets,
            'alerts' => array_values(array_filter($budgets, fn($b) => $b['pct'] >= 90)),
            'breakdown' => self::categoryBreakdown($uid, $start, $end),
            'recent' => array_map(fn($r) => [
                'id' => (int) $r['id'],
                'type' => $r['type'],
                'amount' => (float) $r['amount'],
                'description' => $r['description'],
                'transaction_date' => $r['transaction_date'],
                'category_name' => $r['category_name'],
                'account_name' => $r['account_name'],
            ], $recent),
            'upcoming' => array_map(fn($r) => [
                'id' => (int) $r['id'], 'name' => $r['name'], 'due_date' => $r['due_date'], 'balance' => (float) $r['balance'],
            ], $upcoming),
            'counts' => array_map('intval', $counts),
        ];
    }

    /** Income and spending per month for the last N months (3, 6 or 12). */
    public static function monthly(): array
    {
        $user = require_role('customer');
        $months = in_array((int) ($_GET['months'] ?? 6), [3, 6, 12], true) ? (int) $_GET['months'] : 6;
        $first = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');

        $rows = q_all(
            'SELECT DATE_FORMAT(t.transaction_date, \'%Y-%m\') AS ym,
                    SUM(CASE WHEN t.type = \'income\' THEN t.amount ELSE 0 END) AS income,
                    SUM(CASE WHEN t.type = \'expense\' THEN t.amount ELSE 0 END) AS expense
             FROM transactions t JOIN accounts a ON a.id = t.account_id
             WHERE a.user_id = ? AND t.transaction_date >= ?
             GROUP BY ym',
            [$user['id'], $first->format('Y-m-d')]
        );
        $byMonth = array_column($rows, null, 'ym');

        $data = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $first->modify("+$i months");
            $key = $m->format('Y-m');
            $income = (float) ($byMonth[$key]['income'] ?? 0);
            $expense = (float) ($byMonth[$key]['expense'] ?? 0);
            $data[] = [
                'month' => $key,
                'label' => $m->format('M'),
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($income - $expense, 2),
            ];
        }
        return ['data' => $data];
    }

    /** Top spending categories for a month, with share of total spend. */
    public static function categories(): array
    {
        $user = require_role('customer');
        [$start, $end, $month] = month_range($_GET['month'] ?? null);
        return ['month' => $month, 'data' => self::categoryBreakdown((int) $user['id'], $start, $end)];
    }

    private static function categoryBreakdown(int $uid, string $start, string $end): array
    {
        $rows = q_all(
            'SELECT COALESCE(c.name, \'Uncategorized\') AS name, SUM(t.amount) AS total
             FROM transactions t JOIN accounts a ON a.id = t.account_id LEFT JOIN categories c ON c.id = t.category_id
             WHERE a.user_id = ? AND t.type = \'expense\' AND t.transaction_date BETWEEN ? AND ?
             GROUP BY c.id, c.name ORDER BY total DESC',
            [$uid, $start, $end]
        );
        $sum = array_sum(array_map(fn($r) => (float) $r['total'], $rows));
        return array_map(fn($r) => [
            'name' => $r['name'],
            'total' => round((float) $r['total'], 2),
            'pct' => $sum > 0 ? round((float) $r['total'] / $sum * 100, 1) : 0,
        ], $rows);
    }

    private static function totals(int $uid, string $start, string $end): array
    {
        $row = q_one(
            'SELECT COALESCE(SUM(CASE WHEN t.type = \'income\' THEN t.amount END), 0) AS income,
                    COALESCE(SUM(CASE WHEN t.type = \'expense\' THEN t.amount END), 0) AS expense
             FROM transactions t JOIN accounts a ON a.id = t.account_id
             WHERE a.user_id = ? AND t.transaction_date BETWEEN ? AND ?',
            [$uid, $start, $end]
        );
        return ['income' => round((float) $row['income'], 2), 'expense' => round((float) $row['expense'], 2)];
    }

    private static function pctChange(float $prev, float $cur): ?float
    {
        return $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null;
    }
}
