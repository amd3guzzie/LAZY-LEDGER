<?php declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/** Resolve the visitor IP, accounting for the platform's forwarding header. */
function client_ip(): string
{
    $raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $raw)[0]);
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

/**
 * Save one row in the visitors table: IP address, time, page and what the visitor did
 * (for example "Viewed page" or 'Clicked "Sign up free"').
 */
function log_visit(string $action = 'Viewed page', ?string $page = null): void
{
    start_session();
    // Simple flood guard: at most 500 log rows per browser session.
    $_SESSION['visit_count'] = ($_SESSION['visit_count'] ?? 0) + 1;
    if ($_SESSION['visit_count'] > 500) {
        return;
    }
    $page ??= (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    try {
        q('INSERT INTO visitors (ip_address, user_agent, page, action, user_id) VALUES (?, ?, ?, ?, ?)', [
            client_ip(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            mb_substr($page, 0, 190),
            mb_substr($action, 0, 120),
            $_SESSION['user_id'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[lazyledger] visitor log failed: ' . $e->getMessage());
    }
}
