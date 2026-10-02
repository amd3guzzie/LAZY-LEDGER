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

    public static function register(): array
    {
        $body = read_json();
        $v = new Validator($body);
        
        // Enforce a minimum of 2 characters for names to prevent single-letter inputs
        $first = $v->str('first_name', 'First name', 60, true, 2);
        $last = $v->str('last_name', 'Last name', 60, false, 2);
        
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
