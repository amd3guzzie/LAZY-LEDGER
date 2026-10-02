<?php
declare(strict_types=1);

/**
 * Recurring income and expenses (rent, subscriptions, salary). Each series has one open occurrence,
 * next_due_date, which the customer resolves as paid (records a transaction) or not paid (skipped).
 */
final class RecurringController
{
    public const FREQUENCIES = ['weekly', 'monthly', 'yearly'];

    /** Days before the due date that a bill reminder notification is sent. */
    private const REMIND_DAYS = 3;

    private const SELECT = 'SELECT r.id, r.account_id, a.name AS account_name, r.category_id, c.name AS category_name, r.type,
                                   r.amount, r.description, r.frequency, r.anchor_day, r.next_due_date, r.reminded_for
                            FROM recurring_transactions r
                            JOIN accounts a ON a.id = r.account_id
                            LEFT JOIN categories c ON c.id = r.category_id';

    public static function index(): array
    {
        $user = require_role('customer');
        $rows = q_all(self::SELECT . ' WHERE r.user_id = ? ORDER BY r.next_due_date, r.id', [$user['id']]);
        return ['data' => array_map([self::class, 'present'], $rows)];
    }

    /** Record this occurrence as a transaction (today, or the date given) and move to the next one. */
    public static function pay(int $id): array
    {
        $user = require_role('customer');
        $uid = (int) $user['id'];
        $r = self::find($id, $uid);
        $body = read_json();
        $v = new Validator($body);
        $amount = $v->money('amount', 'Amount', required: false) ?? $r['amount'];
        $date = $v->date('date', 'Date', required: false) ?? date('Y-m-d');
        $v->done();

        q(
            'INSERT INTO transactions (account_id, category_id, recurring_id, type, amount, description, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$r['account_id'], $r['category_id'], $id, $r['type'], $amount, $r['description'], $date]
        );
        $tx = TransactionController::find((int) db()->lastInsertId(), $uid);
        $next = self::advance($r);
        audit($uid, 'recurring.paid', 'recurring', $id, "{$r['type']} due {$r['next_due_date']} marked " . ($r['type'] === 'income' ? 'received' : 'paid') . "; next due $next");
        TransactionController::maybeAlertBudget($user, $tx);
        return ['data' => self::find($id, $uid), 'transaction' => $tx];
    }

    /** Mark this occurrence as not paid: nothing is recorded and the series moves to the next due date. */
    public static function skip(int $id): array
    {
        $user = require_role('customer');
        $r = self::find($id, (int) $user['id']);
        $next = self::advance($r);
        audit((int) $user['id'], 'recurring.skipped', 'recurring', $id, "{$r['type']} due {$r['next_due_date']} marked not " . ($r['type'] === 'income' ? 'received' : 'paid') . "; next due $next");
        return ['data' => self::find($id, (int) $user['id'])];
    }

    /** Stop repeating. Transactions already recorded are kept. */
    public static function destroy(int $id): array
    {
        $user = require_role('customer');
        $r = self::find($id, (int) $user['id']);
        q('DELETE FROM recurring_transactions WHERE id = ?', [$id]);
        audit((int) $user['id'], 'recurring.delete', 'recurring', $id, "{$r['frequency']} {$r['type']} stopped");
        return ['ok' => true];
    }

    /**
     * Occurrences due within 30 days (overdue ones included) for the Home tab. Also sends a one-time
     * reminder notification for bills due within REMIND_DAYS when the user has bill reminders on.
     */
    public static function upcoming(array $user): array
    {
        $uid = (int) $user['id'];
        $today = new DateTimeImmutable('today');
        $rows = q_all(
            self::SELECT . ' WHERE r.user_id = ? AND r.next_due_date <= ? ORDER BY r.next_due_date, r.id',
            [$uid, $today->modify('+30 days')->format('Y-m-d')]
        );
        $remindBy = $today->modify('+' . self::REMIND_DAYS . ' days')->format('Y-m-d');
        foreach ($rows as $r) {
            if ($user['bill_reminders'] && $r['type'] === 'expense' && $r['next_due_date'] <= $remindBy && $r['reminded_for'] !== $r['next_due_date']) {
                $when = $r['next_due_date'] < $today->format('Y-m-d') ? 'is overdue' : 'is due ' . self::dueWords($r['next_due_date']);
                notify($uid, "Bill reminder: {$r['description']} $when.");
                q('UPDATE recurring_transactions SET reminded_for = next_due_date WHERE id = ?', [$r['id']]);
            }
        }
        return array_map([self::class, 'present'], $rows);
    }

    /**
     * Keep a transaction's series in step with the transaction form. $recurring null means the
     * client didn't send the field, so nothing changes. Returns the series id to store on the transaction.
     */
    public static function syncFromTransaction(int $userId, array $tx, ?bool $recurring, ?string $frequency): ?int
    {
        $existing = $tx['recurring_id'] !== null
            ? q_one('SELECT * FROM recurring_transactions WHERE id = ? AND user_id = ?', [$tx['recurring_id'], $userId])
            : null;
        if ($recurring === null) {
            return $existing ? (int) $existing['id'] : null;
        }
        if (!$recurring) {
            if ($existing) {
                q('DELETE FROM recurring_transactions WHERE id = ?', [$existing['id']]);
                audit($userId, 'recurring.delete', 'recurring', (int) $existing['id'], "{$existing['frequency']} {$existing['type']} stopped");
            }
            return null;
        }

        $fields = [$tx['account_id'], $tx['category_id'], $tx['type'], $tx['amount'], $tx['description']];
        if ($existing) {
            // Edits apply to future occurrences. A new frequency restarts the schedule from this transaction.
            if ($existing['frequency'] !== $frequency) {
                $anchor = (int) substr($tx['transaction_date'], 8, 2);
                $next = self::firstDueAfter($tx['transaction_date'], $frequency, $anchor);
            } else {
                $anchor = (int) $existing['anchor_day'];
                $next = $existing['next_due_date'];
            }
            q(
                'UPDATE recurring_transactions SET account_id = ?, category_id = ?, type = ?, amount = ?, description = ?,
                        frequency = ?, anchor_day = ?, next_due_date = ? WHERE id = ?',
                [...$fields, $frequency, $anchor, $next, $existing['id']]
            );
            audit($userId, 'recurring.update', 'recurring', (int) $existing['id'], "$frequency {$tx['type']}, next due $next");
            return (int) $existing['id'];
        }

        $anchor = (int) substr($tx['transaction_date'], 8, 2);
        $next = self::firstDueAfter($tx['transaction_date'], $frequency, $anchor);
        q(
            'INSERT INTO recurring_transactions (user_id, account_id, category_id, type, amount, description, frequency, anchor_day, next_due_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, ...$fields, $frequency, $anchor, $next]
        );
        $id = (int) db()->lastInsertId();
        audit($userId, 'recurring.create', 'recurring', $id, "$frequency {$tx['type']}, next due $next");
        return $id;
    }

    /** The occurrence after $date. Monthly/yearly keep the anchor day where the month allows (31st → 28th/29th/30th). */
    public static function nextDate(string $date, string $frequency, int $anchorDay): string
    {
        $d = new DateTimeImmutable($date);
        if ($frequency === 'weekly') {
            return $d->modify('+7 days')->format('Y-m-d');
        }
        $first = $d->modify('first day of this month')->modify($frequency === 'yearly' ? '+1 year' : '+1 month');
        $day = min($anchorDay, (int) $first->format('t'));
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->format('Y-m-d');
    }

    /** First occurrence after $date that is today or later, so logging an old bill doesn't create a backlog. */
    private static function firstDueAfter(string $date, string $frequency, int $anchorDay): string
    {
        $today = date('Y-m-d');
        $next = self::nextDate($date, $frequency, $anchorDay);
        while ($next < $today) {
            $next = self::nextDate($next, $frequency, $anchorDay);
        }
        return $next;
    }

    private static function advance(array $r): string
    {
        $next = self::nextDate($r['next_due_date'], $r['frequency'], $r['anchor_day']);
        q('UPDATE recurring_transactions SET next_due_date = ?, reminded_for = NULL WHERE id = ?', [$next, $r['id']]);
        return $next;
    }

    private static function find(int $id, int $userId): array
    {
        $row = q_one(self::SELECT . ' WHERE r.id = ? AND r.user_id = ?', [$id, $userId]);
        if (!$row) {
            fail(404, 'Recurring item not found.');
        }
        return self::present($row);
    }

    private static function dueWords(string $date): string
    {
        $days = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date))->days;
        return $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");
    }

    private static function present(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'account_id' => (int) $r['account_id'],
            'account_name' => $r['account_name'],
            'category_id' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
            'category_name' => $r['category_name'],
            'type' => $r['type'],
            'amount' => (float) $r['amount'],
            'description' => $r['description'],
            'frequency' => $r['frequency'],
            'anchor_day' => (int) $r['anchor_day'],
            'next_due_date' => $r['next_due_date'],
            'reminded_for' => $r['reminded_for'],
            'overdue' => $r['next_due_date'] < date('Y-m-d'),
        ];
    }
}
