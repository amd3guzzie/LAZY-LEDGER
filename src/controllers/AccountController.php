<?php
declare(strict_types=1);

final class AccountController
{
    public const TYPES = ['cash', 'bank', 'e_wallet', 'savings'];

    /** Balance is computed from the opening balance and all transactions (never stored). */
    public const BALANCE_SQL = 'a.opening_balance + COALESCE(SUM(CASE WHEN t.type = \'income\' THEN t.amount ELSE -t.amount END), 0)';

    public static function index(): array
    {
        $user = require_role('customer');
        $rows = q_all(
            'SELECT a.id, a.name, a.type, a.opening_balance, a.created_at,
                    ' . self::BALANCE_SQL . ' AS balance, COUNT(t.id) AS transaction_count
             FROM accounts a
             LEFT JOIN transactions t ON t.account_id = a.id
             WHERE a.user_id = ?
             GROUP BY a.id
             ORDER BY a.created_at, a.id',
            [$user['id']]
        );
        $accounts = array_map([self::class, 'present'], $rows);
        return [
            'data' => $accounts,
            'net_worth' => round(array_sum(array_column($accounts, 'balance')), 2),
        ];
    }

    public static function store(): array
    {
        $user = require_role('customer');
        [$name, $type, $opening] = self::validated(read_json());

        q(
            'INSERT INTO accounts (user_id, name, type, opening_balance) VALUES (?, ?, ?, ?)',
            [$user['id'], $name, $type, $opening]
        );
        $id = (int) db()->lastInsertId();
        audit((int) $user['id'], 'account.create', 'account', $id, "type: $type");
        return ['data' => self::find($id, (int) $user['id'])];
    }

    public static function update(int $id): array
    {
        $user = require_role('customer');
        $before = self::find($id, (int) $user['id']);
        [$name, $type, $opening] = self::validated(read_json());

        q(
            'UPDATE accounts SET name = ?, type = ?, opening_balance = ? WHERE id = ? AND user_id = ?',
            [$name, $type, $opening, $id, $user['id']]
        );
        audit((int) $user['id'], 'account.update', 'account', $id, $before['type'] === $type ? "type: $type" : "type: {$before['type']} → $type");
        return ['data' => self::find($id, (int) $user['id'])];
    }

    public static function destroy(int $id): array
    {
        $user = require_role('customer');
        $acc = self::find($id, (int) $user['id']);
        q('DELETE FROM accounts WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        audit((int) $user['id'], 'account.delete', 'account', $id, "type: {$acc['type']}, {$acc['transaction_count']} transaction(s) removed");
        return ['ok' => true];
    }

    /** Fetch one of the user's accounts or 404 (also used as the ownership check). */
    public static function find(int $id, int $userId): array
    {
        $row = q_one(
            'SELECT a.id, a.name, a.type, a.opening_balance, a.created_at,
                    ' . self::BALANCE_SQL . ' AS balance, COUNT(t.id) AS transaction_count
             FROM accounts a
             LEFT JOIN transactions t ON t.account_id = a.id
             WHERE a.id = ? AND a.user_id = ?
             GROUP BY a.id',
            [$id, $userId]
        );
        if (!$row) {
            fail(404, 'Account not found.');
        }
        return self::present($row);
    }

    private static function validated(array $body): array
    {
        $v = new Validator($body);
        $name = $v->str('name', 'Account name', 80);
        $type = $v->enum('type', 'Account type', self::TYPES);
        $opening = $v->money('opening_balance', 'Starting balance', allowZero: true, required: false) ?? 0.0;
        if (isset($body['opening_balance']) && is_numeric($body['opening_balance']) && (float) $body['opening_balance'] < 0) {
            $v->error('opening_balance', 'Starting balance cannot be negative.');
        }
        $v->done();
        return [$name, $type, $opening];
    }

    private static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'opening_balance' => (float) $row['opening_balance'],
            'balance' => round((float) $row['balance'], 2),
            'transaction_count' => (int) $row['transaction_count'],
            'created_at' => $row['created_at'],
        ];
    }
}
