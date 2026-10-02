<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mailer.php';

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
        $emailChanged = $email !== null && $email !== $user['email'];
        if ($emailChanged) {
            $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
            $current = (string) ($body['current_password'] ?? '');
            if ($current === '') {
                $v->error('current_password', 'You must enter your current password to change your email address.');
            } elseif (!password_verify($current, $hash)) {
                $v->error('current_password', 'Incorrect password. Email update denied.');
            }
        }
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

        $changes = [];
        if ($first !== $user['first_name'] || $last !== $user['last_name']) {
            $changes[] = 'name';
        }
        if ($emailChanged) {
            $changes[] = "email {$user['email']} → $email";
        }
        if ($alerts !== (bool) $user['budget_alerts']) {
            $changes[] = 'budget alerts ' . ($alerts ? 'on' : 'off');
        }
        if ($reminders !== (bool) $user['bill_reminders']) {
            $changes[] = 'bill reminders ' . ($reminders ? 'on' : 'off');
        }
        if ($changes) {
            audit((int) $user['id'], 'profile.update', 'user', (int) $user['id'], implode('; ', $changes));
        }

        // Alert the OLD address so a hijacked session can't silently take over the account.
        if ($emailChanged) {
            send_transactional_email(
                $user['email'],
                $user['first_name'],
                'Security Alert: Email Changed',
                email_layout(
                    'Your email address was changed',
                    '<p>Your LazyLedger account email was just changed to <b>' . e($email) . '</b>.</p>'
                    . '<p>If you did not authorize this, please contact support immediately.</p>'
                )
            );
        }
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
        audit((int) $user['id'], 'profile.password_change', 'user', (int) $user['id'], $user['email']);
        return ['ok' => true];
    }

    /** Mark the dashboard tutorial as finished (or skipped) so it doesn't auto-start again. */
    public static function completeTour(): array
    {
        $user = require_role('customer');
        q('UPDATE users SET tour_completed_at = COALESCE(tour_completed_at, NOW()) WHERE id = ?', [$user['id']]);
        return ['ok' => true];
    }

    /** Customers may permanently delete their own account (requires password). */
    public static function destroy(): array
    {
        $user = require_role('customer');
        $password = (string) (read_json()['password'] ?? '');
        $hash = (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($password, $hash)) {
            audit((int) $user['id'], 'profile.delete_failed', 'user', (int) $user['id'], 'Wrong password on account deletion');
            fail(422, 'Password is incorrect.', ['password' => 'Password is incorrect.']);
        }
        // Logged before the delete; the FK then nulls actor_id, so the email in details keeps it traceable.
        audit((int) $user['id'], 'profile.delete', 'user', (int) $user['id'], $user['email'] . ' deleted their own account');
        q('DELETE FROM users WHERE id = ?', [$user['id']]);
        logout_session();
        return ['redirect' => '/'];
    }
}
