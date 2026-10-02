<?php
declare(strict_types=1);

final class AdminController
{
    private const ROLES = ['customer', 'staff', 'admin'];

    /** System-wide dashboard and reports. Aggregates only — no individual customer finances. */
    public static function overview(): array
    {
        require_role('admin');
        $roles = array_column(q_all('SELECT role, COUNT(*) AS n FROM users GROUP BY role'), 'n', 'role');
        $first = (new DateTimeImmutable('first day of this month'))->modify('-5 months');

        $signups = array_column(q_all(
            'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS ym, COUNT(*) AS n FROM users
             WHERE role = \'customer\' AND created_at >= ? GROUP BY ym',
            [$first->format('Y-m-d')]
        ), 'n', 'ym');
        $txs = array_column(q_all(
            'SELECT DATE_FORMAT(transaction_date, \'%Y-%m\') AS ym, COUNT(*) AS n FROM transactions
             WHERE transaction_date >= ? GROUP BY ym',
            [$first->format('Y-m-d')]
        ), 'n', 'ym');
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $first->modify("+$i months");
            $key = $m->format('Y-m');
            $months[] = ['month' => $key, 'label' => $m->format('M'), 'signups' => (int) ($signups[$key] ?? 0), 'transactions' => (int) ($txs[$key] ?? 0)];
        }

        $req = array_column(q_all('SELECT status, COUNT(*) AS n FROM category_requests GROUP BY status'), 'n', 'status');
        $tix = array_column(q_all('SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status'), 'n', 'status');

        return [
            'users' => [
                'total' => array_sum(array_map('intval', $roles)),
                'customers' => (int) ($roles['customer'] ?? 0),
                'staff' => (int) ($roles['staff'] ?? 0),
                'admins' => (int) ($roles['admin'] ?? 0),
                'new_this_month' => (int) q_val('SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')'),
                'active_this_week' => (int) q_val('SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
                'suspended' => (int) q_val('SELECT COUNT(*) FROM users WHERE status = \'suspended\''),
            ],
            'transactions_this_month' => (int) q_val('SELECT COUNT(*) FROM transactions WHERE transaction_date >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')'),
            'requests' => array_map('intval', $req) + ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            'tickets' => array_map('intval', $tix) + ['pending' => 0, 'in_progress' => 0, 'resolved' => 0],
            'months' => $months,
            'top_categories' => q_all(
                'SELECT c.name, COUNT(t.id) AS uses FROM categories c JOIN transactions t ON t.category_id = c.id
                 GROUP BY c.id, c.name ORDER BY uses DESC LIMIT 5'
            ),
            'recent_activity' => q_all(
                'SELECT l.action, l.target_type, l.details, l.created_at, CONCAT(u.first_name, \' \', u.last_name) AS actor, u.role AS actor_role
                 FROM audit_log l LEFT JOIN users u ON u.id = l.actor_id ORDER BY l.created_at DESC, l.id DESC LIMIT 8'
            ),
        ];
    }

    public static function users(): array
    {
        require_role('admin');
        [$page, $per, $offset] = pagination(10);
        $where = ['1 = 1'];
        $params = [];
        $qStr = trim((string) ($_GET['q'] ?? ''));
        if ($qStr !== '') {
            $like = '%' . addcslashes($qStr, '%_\\') . '%';
            $where[] = '(email LIKE ? OR CONCAT(first_name, \' \', last_name) LIKE ?)';
            array_push($params, $like, $like);
        }
        if (in_array($_GET['role'] ?? '', self::ROLES, true)) {
            $where[] = 'role = ?';
            $params[] = $_GET['role'];
        }
        if (in_array($_GET['status'] ?? '', ['active', 'suspended'], true)) {
            $where[] = 'status = ?';
            $params[] = $_GET['status'];
        }
        $sorts = ['created' => 'created_at', 'name' => 'first_name', 'email' => 'email', 'last_login' => 'last_login_at'];
        $sort = $sorts[$_GET['sort'] ?? 'created'] ?? 'created_at';
        $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $w = implode(' AND ', $where);
        $total = (int) q_val("SELECT COUNT(*) FROM users WHERE $w", $params);
        $rows = q_all(
            "SELECT id, first_name, last_name, email, role, status, last_login_at, created_at
             FROM users WHERE $w ORDER BY $sort $dir, id DESC LIMIT $per OFFSET $offset",
            $params
        );
        return paged($rows, $total, $page, $per);
    }

    public static function createUser(): array
    {
        $admin = require_role('admin');
        $body = read_json();
        $v = new Validator($body);
        $first = $v->str('first_name', 'First name', 60);
        $last = $v->str('last_name', 'Last name', 60, required: false);
        $email = $v->email('email');
        $role = $v->enum('role', 'Role', self::ROLES);
        $password = (string) ($body['password'] ?? '');
        AuthController::checkPassword($v, $password, 'password');
        $v->done();

        if (q_val('SELECT 1 FROM users WHERE email = ?', [$email])) {
            fail(409, 'An account with that email already exists.', ['email' => 'Email already in use.']);
        }
        q(
            'INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [$first, $last, $email, password_hash($password, PASSWORD_DEFAULT), $role]
        );
        $id = (int) db()->lastInsertId();
        audit((int) $admin['id'], 'user.create', 'user', $id, "$email as $role");
        return ['id' => $id];
    }

    /** Change a user's role and/or status (suspend / restore). */
    public static function updateUser(int $id): array
    {
        $admin = require_role('admin');
        $target = q_one('SELECT id, email, role, status FROM users WHERE id = ?', [$id]);
        if (!$target) {
            fail(404, 'User not found.');
        }
        if ($id === (int) $admin['id']) {
            fail(422, 'You cannot change your own role or status.');
        }
        $body = read_json();
        $v = new Validator($body);
        $role = $v->enum('role', 'Role', self::ROLES, required: false) ?? $target['role'];
        $status = $v->enum('status', 'Status', ['active', 'suspended'], required: false) ?? $target['status'];
        $v->done();

        q('UPDATE users SET role = ?, status = ? WHERE id = ?', [$role, $status, $id]);
        if ($role !== $target['role']) {
            audit((int) $admin['id'], 'user.role_change', 'user', $id, "{$target['email']}: {$target['role']} → $role");
        }
        if ($status !== $target['status']) {
            audit((int) $admin['id'], $status === 'suspended' ? 'user.suspend' : 'user.restore', 'user', $id, $target['email']);
        }
        return ['ok' => true];
    }

    public static function deleteUser(int $id): array
    {
        $admin = require_role('admin');
        if ($id === (int) $admin['id']) {
            fail(422, 'You cannot delete your own account.');
        }
        $target = q_one('SELECT email FROM users WHERE id = ?', [$id]);
        if (!$target) {
            fail(404, 'User not found.');
        }
        q('DELETE FROM users WHERE id = ?', [$id]);
        audit((int) $admin['id'], 'user.delete', 'user', $id, $target['email']);
        return ['ok' => true];
    }

    public static function categories(): array
    {
        require_role('admin');
        $rows = q_all(
            'SELECT c.id, c.name, c.type, c.created_at,
                    (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id) AS transaction_count,
                    (SELECT COUNT(DISTINCT a.user_id) FROM transactions t JOIN accounts a ON a.id = t.account_id WHERE t.category_id = c.id) AS user_count
             FROM categories c WHERE c.is_global = 1 ORDER BY c.type DESC, c.name'
        );
        return ['data' => $rows];
    }

    public static function createCategory(): array
    {
        $admin = require_role('admin');
        [$name, $type] = self::validatedCategory(read_json(), null);
        q('INSERT INTO categories (user_id, name, type, is_global) VALUES (NULL, ?, ?, 1)', [$name, $type]);
        $id = (int) db()->lastInsertId();
        audit((int) $admin['id'], 'category.create', 'category', $id, "$name ($type)");
        return ['id' => $id];
    }

    public static function updateCategory(int $id): array
    {
        $admin = require_role('admin');
        $cat = q_one('SELECT name, type FROM categories WHERE id = ? AND is_global = 1', [$id]);
        if (!$cat) {
            fail(404, 'Category not found.');
        }
        [$name, $type] = self::validatedCategory(read_json(), $id);
        if ($type !== $cat['type'] && q_val('SELECT 1 FROM transactions WHERE category_id = ? LIMIT 1', [$id])) {
            fail(422, 'This category is already used by transactions, so its type cannot change.', ['type' => 'Type locked: category in use.']);
        }
        q('UPDATE categories SET name = ?, type = ? WHERE id = ?', [$name, $type, $id]);
        audit((int) $admin['id'], 'category.update', 'category', $id, "{$cat['name']} → $name ($type)");
        return ['ok' => true];
    }

    public static function deleteCategory(int $id): array
    {
        $admin = require_role('admin');
        $cat = q_one('SELECT name FROM categories WHERE id = ? AND is_global = 1', [$id]);
        if (!$cat) {
            fail(404, 'Category not found.');
        }
        // Transactions keep their data but become "Uncategorized" (FK ON DELETE SET NULL).
        q('DELETE FROM categories WHERE id = ?', [$id]);
        audit((int) $admin['id'], 'category.delete', 'category', $id, $cat['name']);
        return ['ok' => true];
    }

    public static function auditLog(): array
    {
        require_role('admin');
        [$page, $per, $offset] = pagination(15);
        $where = ['1 = 1'];
        $params = [];
        $qStr = trim((string) ($_GET['q'] ?? ''));
        if ($qStr !== '') {
            $like = '%' . addcslashes($qStr, '%_\\') . '%';
            $where[] = '(l.action LIKE ? OR l.details LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (in_array($_GET['role'] ?? '', self::ROLES, true)) {
            $where[] = 'u.role = ?';
            $params[] = $_GET['role'];
        }
        if (($_GET['category'] ?? '') !== '' && preg_match('/^[a-z_]+$/', (string) $_GET['category'])) {
            $where[] = 'l.action LIKE ?';
            $params[] = $_GET['category'] . '.%';
        }
        if (valid_date((string) ($_GET['from'] ?? ''))) {
            $where[] = 'l.created_at >= ?';
            $params[] = $_GET['from'] . ' 00:00:00';
        }
        if (valid_date((string) ($_GET['to'] ?? ''))) {
            $where[] = 'l.created_at <= ?';
            $params[] = $_GET['to'] . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        $total = (int) q_val("SELECT COUNT(*) FROM audit_log l LEFT JOIN users u ON u.id = l.actor_id WHERE $w", $params);
        $rows = q_all(
            "SELECT l.id, l.action, l.target_type, l.target_id, l.details, l.created_at,
                    u.email AS actor_email, CONCAT(u.first_name, ' ', u.last_name) AS actor_name, u.role AS actor_role
             FROM audit_log l LEFT JOIN users u ON u.id = l.actor_id
             WHERE $w ORDER BY l.created_at DESC, l.id DESC LIMIT $per OFFSET $offset",
            $params
        );
        return paged($rows, $total, $page, $per);
    }

    private static function validatedCategory(array $body, ?int $ignoreId): array
    {
        $v = new Validator($body);
        $name = $v->str('name', 'Category name', 60, min: 2);
        $type = $v->enum('type', 'Type', ['income', 'expense']);
        $v->done();
        if (q_val(
            'SELECT 1 FROM categories WHERE is_global = 1 AND name = ? AND type = ? AND id <> ?',
            [$name, $type, $ignoreId ?? 0]
        )) {
            fail(409, 'A global category with that name already exists.', ['name' => 'Already exists.']);
        }
        return [$name, $type];
    }
}
