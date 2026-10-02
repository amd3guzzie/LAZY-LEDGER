<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/mailer.php';
require_once dirname(__DIR__) . '/audit.php';
require_once dirname(__DIR__) . '/currencies.php';

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public const GENDERS = ['male', 'female', 'non_binary', 'prefer_not_to_say'];
    private const MIN_AGE = 13;

    private const RESET_CODE_MINUTES = 10;
    private const RESET_MAX_ATTEMPTS = 5;
    private const RESET_COOLDOWN_SECONDS = 60;
    private const RESET_MAX_PER_HOUR = 5;

    public static function register(): array
    {
        $body = read_json();
        $v = new Validator($body);
        
        // Enforce a minimum of 2 characters for names to prevent single-letter inputs
        $first = $v->str('first_name', 'First name', 60, true, 2);
        $last = $v->str('last_name', 'Last name', 60, true, 2);
        
        $email = $v->email('email');
        $birthDate = $v->date('birth_date', 'Date of birth');
        $gender = $v->enum('gender', 'Gender', self::GENDERS);
        $currency = $v->enum('currency', 'Currency', array_keys(CURRENCIES));
        $password = (string) ($body['password'] ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');
        self::checkPassword($v, $password, 'password');
        if ($password !== $confirm) {
            $v->error('confirm_password', 'Passwords do not match.');
        }
        if ($birthDate !== null) {
            $age = (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable('today'));
            if ($age->invert === 1) {
                $v->error('birth_date', 'Date of birth cannot be in the future.');
            } elseif ($age->y < self::MIN_AGE) {
                $v->error('birth_date', 'You must be at least ' . self::MIN_AGE . ' years old to sign up.');
            } elseif ($age->y > 120) {
                $v->error('birth_date', 'Please enter a valid date of birth.');
            }
        }
        if (($body['privacy_consent'] ?? false) !== true) {
            $v->error('privacy_consent', 'Please confirm your details and agree to the Data Privacy Notice.');
        }
        $v->done();

        if (q_val('SELECT 1 FROM users WHERE email = ?', [$email])) {
            fail(409, 'An account with that email already exists.', ['email' => 'An account with that email already exists.']);
        }

        q(
            'INSERT INTO users (first_name, last_name, email, password_hash, role, birth_date, gender, currency, privacy_consent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$first, $last, $email, password_hash($password, PASSWORD_DEFAULT), 'customer', $birthDate, $gender, $currency]
        );
        $user = q_one('SELECT * FROM users WHERE id = ?', [(int) db()->lastInsertId()]);

        audit((int) $user['id'], 'auth.register', 'user', (int) $user['id'], "$email · currency $currency · accepted privacy notice");
        
        notify((int) $user['id'], 'Welcome to LazyLedger! Start by adding an account and setting a budget.');
        
        send_transactional_email(
            $user['email'], 
            $user['first_name'], 
            'Welcome to LazyLedger!', 
            email_layout(
                'Welcome, ' . $user['first_name'] . '!',
                '<p>Your LazyLedger account is ready. Log in with <b>' . e($email) . '</b> to set your first budget.</p>'
                . '<p>Your amounts will be tracked in <b>' . e($currency) . '</b>.</p>'
                . '<p>If you did not create this account, please ignore this email or contact support.</p>',
                'Log in to LazyLedger',
                '/?login=1'
            )
        );

        // No session is started: the new customer must log in (which also passes the CAPTCHA).
        return ['ok' => true, 'email' => $email, 'message' => 'Account created! Please log in to continue.'];
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

        $recaptchaSecret = '6LeC6dYtAAAAAB99au6I0sF7Da6Wz6efdgL1RPb9';
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
        // file_get_contents() returns false when Google is unreachable; treat that as a failed check.
        $captchaData = is_string($verifyResult) ? json_decode($verifyResult) : null;
        
        error_log('Captcha Score: ' . ($captchaData->score ?? 'n/a'));

        // v3 requires checking both success and the bot probability score (>= 0.5 is standard)
        if (!$captchaData || !$captchaData->success || !isset($captchaData->score) || $captchaData->score < 0.5) {
            audit(null, 'auth.captcha_failed', 'user', null, $email);
            fail(401, 'Suspicious bot activity detected. Please try again later.');
        }

        $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        
        if (!password_verify($password, $hash) || !$user) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            $actorId = $user ? (int) $user['id'] : null;
            audit($actorId, 'auth.login_failed', 'user', $actorId, $email . ($user ? '' : ' (no such account)'));
            
            if ($_SESSION['login_failures'] >= self::MAX_ATTEMPTS) {
                $_SESSION['login_failures'] = 0;
                $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
                audit($actorId, 'auth.lockout', 'user', $actorId, "$email: too many failed attempts");
                fail(429, 'Too many failed attempts. Please contact the admin of the page.');
            }
            fail(401, 'Invalid email or password.');
        }
        
        if ($user['status'] !== 'active') {
            audit((int) $user['id'], 'auth.login_blocked', 'user', (int) $user['id'], "$email: account suspended");
            fail(403, 'This account has been suspended. Please contact support.');
        }
        
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        login_session($user);
        q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        $_SESSION['login_failures'] = 0;
        audit((int) $user['id'], 'auth.login', 'user', (int) $user['id'], $email);

        return ['user' => public_user($user), 'redirect' => home_for_role($user['role']), 'csrf' => csrf_token()];
    }

    /**
     * Step 1 of "Forgot password": email a 6-digit one-time code. The response is the same whether
     * or not the email has an account, so this endpoint can't be used to discover who is registered.
     */
    public static function forgotPassword(): array
    {
        $v = new Validator(read_json());
        $email = $v->email('email');
        $v->done();

        $response = [
            'ok' => true,
            'message' => 'If an account exists for ' . $email . ', we sent a 6-digit code to it. The code expires in '
                . self::RESET_CODE_MINUTES . ' minutes.',
        ];

        $user = q_one('SELECT id, first_name, email, status FROM users WHERE email = ?', [$email]);
        if (!$user || $user['status'] !== 'active') {
            $actorId = $user ? (int) $user['id'] : null;
            audit($actorId, 'auth.reset_requested', 'user', $actorId, $email . ($user ? ' (suspended, no code sent)' : ' (no such account)'));
            return $response;
        }
        $userId = (int) $user['id'];

        // Throttle: one code per minute and a few per hour, so nobody can flood the inbox.
        $recent = q_one(
            'SELECT COALESCE(SUM(created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)), 0) AS last_minute, COUNT(*) AS last_hour
             FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [self::RESET_COOLDOWN_SECONDS, $userId]
        );
        if ((int) $recent['last_minute'] > 0) {
            fail(429, 'A code was just sent. Please wait a minute before requesting another one.');
        }
        if ((int) $recent['last_hour'] >= self::RESET_MAX_PER_HOUR) {
            fail(429, 'Too many reset requests. Please try again in an hour.');
        }

        // Only the newest code works.
        q('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        q(
            'INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$userId, password_hash($code, PASSWORD_DEFAULT), self::RESET_CODE_MINUTES]
        );
        audit($userId, 'auth.reset_requested', 'user', $userId, $email);

        send_transactional_email(
            $user['email'],
            $user['first_name'],
            'Your LazyLedger password reset code',
            email_layout(
                'Reset your password',
                '<p>Hi ' . e($user['first_name']) . ', use this code to reset your LazyLedger password:</p>'
                . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#c14f27;margin:16px 0">' . $code . '</p>'
                . '<p>The code expires in ' . self::RESET_CODE_MINUTES . ' minutes and can only be used once.</p>'
                . '<p>If you did not ask to reset your password, you can ignore this email; your password stays the same.</p>'
            )
        );
        return $response;
    }

    /** Step 2 of "Forgot password": check the emailed code and set the new password. */
    public static function resetPassword(): array
    {
        $body = read_json();
        $v = new Validator($body);
        $email = $v->email('email');
        $code = preg_replace('/\s+/', '', (string) ($body['code'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if (!preg_match('/^\d{6}$/', $code)) {
            $v->error('code', 'Enter the 6-digit code from the email.');
        }
        self::checkPassword($v, $password, 'password');
        if ($password !== (string) ($body['confirm_password'] ?? '')) {
            $v->error('confirm_password', 'Passwords do not match.');
        }
        $v->done();

        $invalid = 'That code is invalid or has expired. Please request a new one.';
        $user = q_one('SELECT id, first_name, email, status FROM users WHERE email = ?', [$email]);
        $reset = $user ? q_one(
            'SELECT id, code_hash, attempts FROM password_resets
             WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$user['id']]
        ) : null;
        if (!$user || $user['status'] !== 'active' || !$reset) {
            fail(422, $invalid, ['code' => $invalid]);
        }
        $userId = (int) $user['id'];

        if (!password_verify($code, $reset['code_hash'])) {
            $left = self::RESET_MAX_ATTEMPTS - ((int) $reset['attempts'] + 1);
            if ($left <= 0) {
                q('UPDATE password_resets SET attempts = attempts + 1, used_at = NOW() WHERE id = ?', [$reset['id']]);
                audit($userId, 'auth.reset_failed', 'user', $userId, "$email: too many wrong codes, code cancelled");
                $msg = 'Too many incorrect attempts. Please request a new code.';
                fail(422, $msg, ['code' => $msg]);
            }
            q('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?', [$reset['id']]);
            audit($userId, 'auth.reset_failed', 'user', $userId, "$email: wrong code");
            $msg = "Incorrect code. $left attempt" . ($left === 1 ? '' : 's') . ' left.';
            fail(422, $msg, ['code' => $msg]);
        }

        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
        q('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        audit($userId, 'auth.password_reset', 'user', $userId, $email);
        notify($userId, 'Your password was reset using an emailed code.');

        send_transactional_email(
            $user['email'],
            $user['first_name'],
            'Your LazyLedger password was changed',
            email_layout(
                'Password changed',
                '<p>Hi ' . e($user['first_name']) . ', the password for your LazyLedger account was just reset.</p>'
                . '<p>If this was not you, reset your password again right away and open a support ticket.</p>',
                'Log in to LazyLedger',
                '/?login=1'
            )
        );

        return ['ok' => true, 'email' => $email, 'message' => 'Password updated! Please log in with your new password.'];
    }

    public static function logout(): array
    {
        $user = current_user();
        if ($user) {
            audit((int) $user['id'], 'auth.logout', 'user', (int) $user['id'], $user['email']);
        }
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
