<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/http.php';

/** The logged-in user row (fresh from DB), or null. Suspended users are logged out. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    start_session();
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $cache = null;
    }
    $user = q_one(
        'SELECT id, first_name, last_name, email, role, status, monthly_budget, budget_alerts, bill_reminders, last_login_at, created_at
         FROM users WHERE id = ?',
        [$id]
    );
    if (!$user || $user['status'] !== 'active') {
        logout_session();
        return $cache = null;
    }
    return $cache = $user;
}

function public_user(array $u): array
{
    return [
        'id' => (int) $u['id'],
        'first_name' => $u['first_name'],
        'last_name' => $u['last_name'],
        'email' => $u['email'],
        'role' => $u['role'],
        'monthly_budget' => $u['monthly_budget'] !== null ? (float) $u['monthly_budget'] : null,
        'budget_alerts' => (bool) $u['budget_alerts'],
        'bill_reminders' => (bool) $u['bill_reminders'],
        'created_at' => $u['created_at'],
    ];
}

function login_session(array $user): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['csrf']);
}

function logout_session(): void
{
    start_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

/** API guard: 401 when not logged in, 403 when role not allowed. Returns the user. */
function require_role(string ...$roles): array
{
    $user = current_user();
    if (!$user) {
        fail(401, 'Please log in to continue.');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        fail(403, 'You do not have permission to do that.');
    }
    return $user;
}

function home_for_role(string $role): string
{
    return match ($role) {
        'admin' => '/admin.php',
        'staff' => '/staff.php',
        default => '/app.php',
    };
}

/** Page guard for the PHP dashboard shells: redirects before any HTML is sent. */
function page_guard(string $role): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /?login=1');
        exit;
    }
    if ($user['role'] !== $role) {
        header('Location: ' . home_for_role($user['role']));
        exit;
    }
    header('Cache-Control: no-store');
    send_security_headers();
    return $user;
}
