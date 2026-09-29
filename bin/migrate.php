<?php
declare(strict_types=1);

/**
 * Creates the schema and seeds starter data. Safe to run repeatedly (runs on every container boot).
 * Usage: php bin/migrate.php
 */

require_once dirname(__DIR__) . '/src/db.php';

function out(string $msg): void
{
    fwrite(STDOUT, "[migrate] $msg\n");
}

// Wait for MySQL (it may still be starting when the app container boots).
$pdo = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $pdo = db();
        break;
    } catch (PDOException $e) {
        out("Database not ready (attempt $attempt/30): " . $e->getMessage());
        sleep(2);
    }
}
if (!$pdo) {
    out('Could not connect to the database. Check MYSQLHOST / MYSQLUSER / MYSQLPASSWORD / MYSQLDATABASE.');
    exit(1);
}

// 1. Schema
$sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}
out('Schema is up to date.');

// 2. Global categories (added only if missing)
$globals = [
    ['Salary', 'income'], ['Allowance', 'income'], ['Freelance', 'income'], ['Other Income', 'income'],
    ['Rents and bills', 'expense'], ['Groceries', 'expense'], ['Transport', 'expense'], ['Take Out', 'expense'],
    ['Entertainment', 'expense'], ['Shopping', 'expense'], ['Health', 'expense'], ['School', 'expense'],
];
foreach ($globals as [$name, $type]) {
    if (!q_val('SELECT 1 FROM categories WHERE is_global = 1 AND name = ? AND type = ?', [$name, $type])) {
        q('INSERT INTO categories (user_id, name, type, is_global) VALUES (NULL, ?, ?, 1)', [$name, $type]);
    }
}

// 3. Starter users — only when the users table is empty, and only for roles with a password in env.
if ((int) q_val('SELECT COUNT(*) FROM users') > 0) {
    out('Users already exist; skipping seed.');
    exit(0);
}

$seeds = [
    ['admin', env('SEED_ADMIN_EMAIL', 'admin@lazyledger.test'), env('SEED_ADMIN_PASSWORD'), 'System', 'Admin'],
    ['staff', env('SEED_STAFF_EMAIL', 'staff@lazyledger.test'), env('SEED_STAFF_PASSWORD'), 'Support', 'Staff'],
    ['customer', env('SEED_CUSTOMER_EMAIL', 'demo@lazyledger.test'), env('SEED_CUSTOMER_PASSWORD'), 'Sydney', 'Magdaluyo'],
];
$customerId = null;
foreach ($seeds as [$role, $email, $password, $first, $last]) {
    if (!$password) {
        out("SEED_" . strtoupper($role) . "_PASSWORD not set; skipping $role account.");
        continue;
    }
    q(
        'INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
        [$first, $last, strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]
    );
    out("Created $role account $email");
    if ($role === 'customer') {
        $customerId = (int) $pdo->lastInsertId();
    }
}

// 4. Demo data for the demo customer: accounts, six months of transactions, this month's budgets.
if ($customerId) {
    $cat = fn(string $name) => (int) q_val('SELECT id FROM categories WHERE is_global = 1 AND name = ?', [$name]);
    $nextDue = (new DateTimeImmutable('first day of next month'))->modify('+4 days')->format('Y-m-d');

    q('INSERT INTO accounts (user_id, name, type, opening_balance) VALUES (?, ?, ?, ?)', [$customerId, 'Cash', 'cash', 2000]);
    $cash = (int) $pdo->lastInsertId();
    q('INSERT INTO accounts (user_id, name, type, opening_balance) VALUES (?, ?, ?, ?)', [$customerId, 'BDO Savings', 'bank', 15000]);
    $bdo = (int) $pdo->lastInsertId();
    q('INSERT INTO accounts (user_id, name, type, opening_balance) VALUES (?, ?, ?, ?)', [$customerId, 'GCash', 'e_wallet', 500]);
    $gcash = (int) $pdo->lastInsertId();
    q('INSERT INTO accounts (user_id, name, type, opening_balance, due_date) VALUES (?, ?, ?, ?, ?)', [$customerId, 'Credit Card', 'credit_card', 0, $nextDue]);
    $card = (int) $pdo->lastInsertId();

    mt_srand(122);
    $today = new DateTimeImmutable('today');
    $insert = $pdo->prepare(
        'INSERT INTO transactions (account_id, category_id, type, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $templates = [
        ['Groceries', ['SM Supermarket', 'Puregold', 'Robinsons Supermarket'], 600, 1600, [$cash, $card]],
        ['Take Out', ['Starbucks', "McDonald's", 'Jollibee', 'Mang Inasal'], 150, 450, [$cash, $gcash]],
        ['Transport', ['Grab', 'Jeepney fare', 'LRT load', 'Gas'], 60, 700, [$cash, $gcash]],
        ['Entertainment', ['Netflix', 'Cinema', 'Spotify'], 150, 600, [$card, $gcash]],
    ];
    for ($m = 5; $m >= 0; $m--) {
        $monthStart = $today->modify('first day of this month')->modify("-$m months");
        $lastDay = $m === 0 ? (int) $today->format('j') : (int) $monthStart->format('t');
        $day = fn(int $d) => $monthStart->modify('+' . (min($d, $lastDay) - 1) . ' days')->format('Y-m-d');

        $insert->execute([$bdo, $cat('Salary'), 'income', 21000, 'Salary', $day(1)]);
        if ($lastDay >= 15) {
            $insert->execute([$bdo, $cat('Salary'), 'income', 21000, 'Salary', $day(15)]);
        }
        $insert->execute([$cash, $cat('Allowance'), 'income', 5000, 'Allowance', $day(2)]);
        $insert->execute([$gcash, $cat('Freelance'), 'income', 3000, 'Freelance design gig', $day(10)]);
        $insert->execute([$bdo, $cat('Rents and bills'), 'expense', 9800 + mt_rand(0, 8) * 100, 'Rent & utilities', $day(3)]);
        foreach ($templates as [$category, $names, $min, $max, $accounts]) {
            $count = mt_rand(3, 6);
            for ($i = 0; $i < $count; $i++) {
                $insert->execute([
                    $accounts[array_rand($accounts)],
                    $cat($category),
                    'expense',
                    mt_rand($min, $max),
                    $names[array_rand($names)],
                    $day(mt_rand(1, 28)),
                ]);
            }
        }
    }

    $start = $today->modify('first day of this month')->format('Y-m-d');
    $end = $today->modify('last day of this month')->format('Y-m-d');
    foreach ([['Rents and bills', 12000], ['Groceries', 6000], ['Take Out', 2500], ['Transport', 3000], ['Entertainment', 1500]] as [$name, $limit]) {
        q(
            'INSERT INTO budgets (user_id, category_id, amount_limit, start_date, end_date) VALUES (?, ?, ?, ?, ?)',
            [$customerId, $cat($name), $limit, $start, $end]
        );
    }
    q('UPDATE users SET monthly_budget = 30000 WHERE id = ?', [$customerId]);
    q('INSERT INTO category_requests (user_id, requested_name, requested_type, reason) VALUES (?, ?, ?, ?)',
        [$customerId, 'Pet Supplies', 'expense', 'I buy food and litter for my cat every month.']);
    q('INSERT INTO support_tickets (user_id, subject, message) VALUES (?, ?, ?)',
        [$customerId, 'Budget alert timing', 'Can I get the budget alert earlier than 90%? I would like a heads-up at around 75%.']);
    q('INSERT INTO notifications (user_id, message) VALUES (?, ?)', [$customerId, 'Welcome to LazyLedger! Your demo data is ready to explore.']);
    out('Seeded demo data for the demo customer.');
}
