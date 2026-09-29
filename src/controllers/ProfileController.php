<?php
declare(strict_types=1);

/** Any logged-in user can view/update their own profile and password. */
final class ProfileController
{
    public static function show(): array
    {
        return ['data' => public_user(require_role())];
    }

    public static function update(): array
    {
        $user = require_role();
        $body = read_json();
        $v = new Validator($body);
        $first = $v->str('first_name', 'First name', 60);
        $last = $v->str('last_name', 'Last name', 60, required: false);
        $email = $v->email('email');
        $v->done();

        if (q_val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) {
            fail(409, 'That email is already used by another account.', ['email' => 'That email is already in use.']);
        }
        $alerts = array_key_exists('budget_alerts', $body) ? $v->bool('budget_alerts') : (bool) $user['budget_alerts'];
        $reminders = array_key_exists('bill_reminders', $body) ? $v->bool('bill_reminders') : (bool) $user['bill_reminders'];

        q(
            'UPDATE users SET first_name = ?, last_name = ?, email = ?, budget_alerts = ?, bill_reminders = ? WHERE id = ?',
            [$first, $last, $email, (int) $alerts, (int) $reminders, $user['id']]
        );
        return ['data' => public_user(q_one('SELECT * FROM users WHERE id = ?', [$user['id']]))];
    }

    public static function password(): array
    {
        $user = require_role();
        $body = read_json();
        $current = (string) ($body['current_password'] ?? '');
        $new = (string) ($body['new_password'] ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');

        $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        $v = new Validator($body);
        if (!password_verify($current, $hash)) {
            $v->error('current_password', 'Current password is incorrect.');
        }
        AuthController::checkPassword($v, $new, 'new_password');
        if ($new !== $confirm) {
            $v->error('confirm_password', 'Passwords do not match.');
        }
        $v->done();

        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        return ['ok' => true];
    }

    /** Customers may permanently delete their own account (requires password). */
    public static function destroy(): array
    {
        $user = require_role('customer');
        $password = (string) (read_json()['password'] ?? '');
        $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($password, $hash)) {
            fail(422, 'Password is incorrect.', ['password' => 'Password is incorrect.']);
        }
        q('DELETE FROM users WHERE id = ?', [$user['id']]);
        logout_session();
        return ['redirect' => '/'];
    }
}
