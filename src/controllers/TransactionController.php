<?php
declare(strict_types=1);

final class TransactionController
{
    /** Whitelisted sort columns (user input never reaches ORDER BY directly). */
    private const SORTS = [
        'date' => 't.transaction_date',
        'amount' => 't.amount',
        'description' => 't.description',
        'category' => 'category_name',
        'account' => 'account_name',
    ];

    private const SELECT = 'SELECT t.id, t.type, t.amount, t.description, t.transaction_date, t.created_at,
                                   t.account_id, a.name AS account_name, t.category_id, c.name AS category_name
                            FROM transactions t
                            JOIN accounts a ON a.id = t.account_id
                            LEFT JOIN categories c ON c.id = t.category_id';

    public static function index(): array
    {
        $user = require_role('customer');
        [$where, $params] = self::filters((int) $user['id']);
        [$page, $per, $offset] = pagination(10, 100);

        $sortKey = $_GET['sort'] ?? 'date';
        $sort = self::SORTS[$sortKey] ?? self::SORTS['date'];
        $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $total = (int) q_val(
            'SELECT COUNT(*) FROM transactions t JOIN accounts a ON a.id = t.account_id
             LEFT JOIN categories c ON c.id = t.category_id WHERE ' . $where,
            $params
        );
        $rows = q_all(
            self::SELECT . " WHERE $where ORDER BY $sort $dir, t.id $dir LIMIT $per OFFSET $offset",
            $params
        );
        return paged(array_map([self::class, 'present'], $rows), $total, $page, $per);
    }

    public static function show(int $id): array
    {
        $user = require_role('customer');
        return ['data' => self::find($id, (int) $user['id'])];
    }

    public static function store(): array
    {
        $user = require_role('customer');
        $data = self::validated(read_json(), (int) $user['id']);
        q(
            'INSERT INTO transactions (account_id, category_id, type, amount, description, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$data['account_id'], $data['category_id'], $data['type'], $data['amount'], $data['description'], $data['transaction_date']]
        );
        $tx = self::find((int) db()->lastInsertId(), (int) $user['id']);
        self::maybeAlertBudget($user, $tx);
        return ['data' => $tx];
    }

    public static function update(int $id): array
    {
        $user = require_role('customer');
        self::find($id, (int) $user['id']);
        $data = self::validated(read_json(), (int) $user['id']);
        q(
            'UPDATE transactions SET account_id = ?, category_id = ?, type = ?, amount = ?, description = ?, transaction_date = ?
             WHERE id = ?',
            [$data['account_id'], $data['category_id'], $data['type'], $data['amount'], $data['description'], $data['transaction_date'], $id]
        );
        return ['data' => self::find($id, (int) $user['id'])];
    }

    public static function destroy(int $id): array
    {
        $user = require_role('customer');
        self::find($id, (int) $user['id']);
        q('DELETE FROM transactions WHERE id = ?', [$id]);
        return ['ok' => true];
    }

    /** CSV download of the currently filtered ledger. */
    public static function export(): never
    {
        $user = require_role('customer');
        [$where, $params] = self::filters((int) $user['id']);
        $rows = q_all(self::SELECT . " WHERE $where ORDER BY t.transaction_date DESC, t.id DESC", $params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lazyledger-transactions-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads ₱ and accents correctly
        fputcsv($out, ['Date', 'Description', 'Category', 'Account', 'Type', 'Amount']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['transaction_date'],
                self::csvSafe($r['description']),
                self::csvSafe($r['category_name'] ?? 'Uncategorized'),
                self::csvSafe($r['account_name']),
                $r['type'],
                ($r['type'] === 'expense' ? '-' : '') . number_format((float) $r['amount'], 2, '.', ''),
            ]);
        }
        fclose($out);
        exit;
    }

    public static function find(int $id, int $userId): array
    {
        $row = q_one(self::SELECT . ' WHERE t.id = ? AND a.user_id = ?', [$id, $userId]);
        if (!$row) {
            fail(404, 'Transaction not found.');
        }
        return self::present($row);
    }

    /** Build the WHERE clause from query-string filters. Always scoped to the user's own accounts. */
    private static function filters(int $userId): array
    {
        $where = ['a.user_id = ?'];
        $params = [$userId];

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(t.description LIKE ? OR c.name LIKE ? OR a.name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if (in_array($_GET['type'] ?? '', ['income', 'expense'], true)) {
            $where[] = 't.type = ?';
            $params[] = $_GET['type'];
        }
        if (ctype_digit((string) ($_GET['account_id'] ?? ''))) {
            $where[] = 't.account_id = ?';
            $params[] = (int) $_GET['account_id'];
        }
        if (($_GET['category_id'] ?? '') === 'none') {
            $where[] = 't.category_id IS NULL';
        } elseif (ctype_digit((string) ($_GET['category_id'] ?? ''))) {
            $where[] = 't.category_id = ?';
            $params[] = (int) $_GET['category_id'];
        }
        if (valid_date((string) ($_GET['from'] ?? ''))) {
            $where[] = 't.transaction_date >= ?';
            $params[] = $_GET['from'];
        }
        if (valid_date((string) ($_GET['to'] ?? ''))) {
            $where[] = 't.transaction_date <= ?';
            $params[] = $_GET['to'];
        }
        return [implode(' AND ', $where), $params];
    }

    private static function validated(array $body, int $userId): array
    {
        $v = new Validator($body);
        $type = $v->enum('type', 'Type', ['income', 'expense']);
        $amount = $v->money('amount', 'Amount');
        $description = $v->str('description', 'Description', 120);
        if (($body['description'] ?? null) !== null && trim((string) $body['description']) === '') {
            $v->error('description', 'Please input a description.');
        }
        $date = $v->date('transaction_date', 'Date');
        $accountId = $v->int('account_id', 'Account');
        $categoryId = $v->int('category_id', 'Category', required: false);
        if ($date && $date > date('Y-m-d', strtotime('+1 year'))) {
            $v->error('transaction_date', 'Date is too far in the future.');
        }
        $v->done();

        if (!q_val('SELECT 1 FROM accounts WHERE id = ? AND user_id = ?', [$accountId, $userId])) {
            fail(422, 'Please choose one of your accounts.', ['account_id' => 'Please choose one of your accounts.']);
        }
        if ($categoryId !== null) {
            CategoryController::assertUsable($categoryId, $userId, $type);
        }
        return [
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date,
            'account_id' => $accountId,
            'category_id' => $categoryId,
        ];
    }

    /** Notify the user once a category crosses 90% / 100% of its budget for the month. */
    private static function maybeAlertBudget(array $user, array $tx): void
    {
        if ($tx['type'] !== 'expense' || !$tx['category_id'] || !$user['budget_alerts']) {
            return;
        }
        $budget = q_one(
            'SELECT b.amount_limit, b.start_date, b.end_date, c.name FROM budgets b JOIN categories c ON c.id = b.category_id
             WHERE b.user_id = ? AND b.category_id = ? AND ? BETWEEN b.start_date AND b.end_date',
            [$user['id'], $tx['category_id'], $tx['transaction_date']]
        );
        if (!$budget) {
            return;
        }
        $spent = (float) q_val(
            'SELECT COALESCE(SUM(t.amount), 0) FROM transactions t JOIN accounts a ON a.id = t.account_id
             WHERE a.user_id = ? AND t.category_id = ? AND t.type = \'expense\' AND t.transaction_date BETWEEN ? AND ?',
            [$user['id'], $tx['category_id'], $budget['start_date'], $budget['end_date']]
        );
        $limit = (float) $budget['amount_limit'];
        $before = $spent - $tx['amount'];
        if ($spent > $limit && $before <= $limit) {
            notify((int) $user['id'], "You've gone over your {$budget['name']} budget for this month.");
        } elseif ($spent >= 0.9 * $limit && $before < 0.9 * $limit) {
            notify((int) $user['id'], "Heads up: you've used 90% of your {$budget['name']} budget.");
        }
    }

    /** Prevent spreadsheet formula injection in exported cells. */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private static function present(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'type' => $r['type'],
            'amount' => (float) $r['amount'],
            'description' => $r['description'],
            'transaction_date' => $r['transaction_date'],
            'account_id' => (int) $r['account_id'],
            'account_name' => $r['account_name'],
            'category_id' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
            'category_name' => $r['category_name'],
            'created_at' => $r['created_at'],
        ];
    }
}
