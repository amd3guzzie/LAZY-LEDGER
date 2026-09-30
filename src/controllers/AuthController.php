<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/mailer.php';

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public static function register(): array
    {
        $body = read_json();
        $v = new Validator($body);
        
        // Enforce a minimum of 2 characters for names to prevent single-letter inputs
        $first = $v->str('first_name', 'First name', 60, true, 2);
        $last = $v->str('last_name', 'Last name', 60, false, 2);
        
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
        
        // Send the welcome email via Brevo
        send_transactional_email(
            $user['email'], 
            $user['first_name'], 
            'Welcome to LazyLedger!', 
            '<h1>Welcome!</h1><p>Start by adding an account and setting your first budget.</p>'
        );

        login_session($user);
        q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        return ['user' => public_user($user), 'redirect' => home_for_role($user['role']), 'csrf' => csrf_token()];
    }

    public static function login(): array
    {
        start_session();
        $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
        if ($lockedUntil > time()) {
            fail(429, 'Too many failed attempts. Please contact the admin of the page.');
        }

        $body = read_json();
        $v = new Validator($body);
        
        $email = $v->email('email');
        $password = (string) ($body['password'] ?? '');
        $captchaResponse = (string) ($body['g-recaptcha-response'] ?? '');
        
        if ($password === '') {
            $v->error('password', 'Password is required.');
        }
        if ($captchaResponse === '') {
            $v->error('g-recaptcha-response', 'Please complete the CAPTCHA to prove you are human.');
        }
        $v->done();

        // --- VERIFY CAPTCHA WITH GOOGLE ---
        $recaptchaSecret = env('6LeC6dYtAAAAAB99au6I0sF7Da6Wz6efdgL1RPb9');
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'secret' => $recaptchaSecret,
                    'response' => $captchaResponse
                ])
            ]
        ]);
        
        $verifyResult = file_get_contents($verifyUrl, false, $context);
        $captchaData = json_decode($verifyResult);
        
        if (!$captchaData || !$captchaData->success) {
            fail(401, 'CAPTCHA verification failed. Please try again.');
        }
        // --- END CAPTCHA VERIFICATION ---

        $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        
        if (!password_verify($password, $hash) || !$user) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            
            if ($_SESSION['login_failures'] >= self::MAX_ATTEMPTS) {
                $_SESSION['login_failures'] = 0;
                $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
                fail(429, 'Too many failed attempts. Please contact the admin of the page.');
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

    public static function updateProfile(): array
    {
        $user = require_role();
        $body = read_json();
        $v = new Validator($body);

        $currentDbUser = q_one('SELECT * FROM users WHERE id = ?', [$user['id']]);

        $first = $v->str('first_name', 'First name', 60, true, 2);
        $last = $v->str('last_name', 'Last name', 60, false, 2);
        
        $newEmail = $v->email('email');
        $currentPassword = (string) ($body['current_password'] ?? '');

        // Preserve toggle preferences if they are passed, otherwise keep current
        $budgetAlerts = isset($body['budget_alerts']) ? (int)(bool)$body['budget_alerts'] : $currentDbUser['budget_alerts'];
        $billReminders = isset($body['bill_reminders']) ? (int)(bool)$body['bill_reminders'] : $currentDbUser['bill_reminders'];

        // Password verification check triggers ONLY if the email is changed
        if ($newEmail !== $currentDbUser['email']) {
            if ($currentPassword === '') {
                $v->error('current_password', 'You must enter your current password to change your email address.');
            } elseif (!password_verify($currentPassword, $currentDbUser['password_hash'])) {
                $v->error('current_password', 'Incorrect password. Email update denied.');
            }

            if (q_val('SELECT 1 FROM users WHERE email = ? AND id != ?', [$newEmail, $user['id']])) {
                $v->error('email', 'An account with that email already exists.');
            }
        }

        $v->done();

        q(
            'UPDATE users SET first_name = ?, last_name = ?, email = ?, budget_alerts = ?, bill_reminders = ? WHERE id = ?',
            [$first, $last, $newEmail, $budgetAlerts, $billReminders, $user['id']]
        );

        // Alert the OLD email about the change
        if ($newEmail !== $currentDbUser['email']) {
            send_transactional_email(
                $currentDbUser['email'], 
                $currentDbUser['first_name'], 
                'Security Alert: Email Changed', 
                '<h1>Security Alert</h1><p>Your LazyLedger account email was just changed to <b>' . e($newEmail) . '</b>. If you did not authorize this, please contact support immediately.</p>'
            );
        }

        $updatedUser = q_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        
        // Return 'data' array so your customer.js state.me = res.data updates properly
        return ['data' => public_user($updatedUser), 'message' => 'Profile updated successfully.'];
    }
}