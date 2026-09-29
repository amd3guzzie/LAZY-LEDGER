<?php
declare(strict_types=1);

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public static function register(): array
    {
        $body = read_json();
        $v = new Validator($body);
        $first = $v->str('first_name', 'First name', 60);
        $last = $v->str('last_name', 'Last name', 60, required: false);
        $email = $v->email('email');
        $password = (string) ($body['password'] ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');
        self::checkPassword($v, $password, 'password');
        if ($password !== $confirm) {
            $v->error('confirm_password', 'Passwords do not match.');
        }
        $v->done();

        if (q_val('SELECT 1 FROM users WHERE email = ?', [$email])) {
            fail(409, 'An account with that email already exists.', ['email' => 'An account with that email already exists.']);
        }

        q(
            'INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [$first, $last, $email, password_hash($password, PASSWORD_DEFAULT), 'customer']
        );
        $user = q_one('SELECT * FROM users WHERE id = ?', [(int) db()->lastInsertId()]);
        notify((int) $user['id'], 'Welcome to LazyLedger! Start by adding an account and setting a budget.');
        login_session($user);
        q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        return ['user' => public_user($user), 'redirect' => home_for_role($user['role']), 'csrf' => csrf_token()];
    }

    public static function login(): array
    {
        start_session();
        $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
        if ($lockedUntil > time()) {
            fail(429, 'Too many failed attempts. Please wait a minute and try again.');
        }

        $body = read_json();
        $v = new Validator($body);
        $email = $v->email('email');
        $password = (string) ($body['password'] ?? '');
        if ($password === '') {
            $v->error('password', 'Password is required.');
        }
        $v->done();

        $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        // Verify against a dummy hash when the user doesn't exist, so timing doesn't reveal valid emails.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (!password_verify($password, $hash) || !$user) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= self::MAX_ATTEMPTS) {
                $_SESSION['login_failures'] = 0;
                $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
            }
            fail(401, 'Invalid email or password.');
        }
        if ($user['status'] !== 'active') {
            fail(403, 'This account has been suspended. Please contact support.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        login_session($user);
        q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        return ['user' => public_user($user), 'redirect' => home_for_role($user['role']), 'csrf' => csrf_token()];
    }

    public static function logout(): array
    {
        logout_session();
        return ['redirect' => '/'];
    }

    public static function me(): array
    {
        $user = require_role();
        return ['user' => public_user($user)];
    }

    public static function checkPassword(Validator $v, string $password, string $field): void
    {
        if (strlen($password) < 8) {
            $v->error($field, 'Password must be at least 8 characters.');
        } elseif (strlen($password) > 72) {
            $v->error($field, 'Password must be at most 72 characters.');
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $v->error($field, 'Password must contain at least one letter and one number.');
        }
    }
}
