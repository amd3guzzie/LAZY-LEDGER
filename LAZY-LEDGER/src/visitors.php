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

/** Record at most one visit per session every 30 minutes. */
function log_visit(): void
{
    start_session();
    if (time() - ($_SESSION['visit_logged_at'] ?? 0) < 1800) {
        return;
    }
    $_SESSION['visit_logged_at'] = time();
    try {
        q('INSERT INTO visitors (ip_address, user_agent, page, user_id) VALUES (?, ?, ?, ?)', [
            client_ip(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            mb_substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), 0, 190),
            $_SESSION['user_id'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[lazyledger] visitor log failed: ' . $e->getMessage());
    }
}