<?php
/**
 * Router for PHP's built-in server (local dev without Apache/Docker), mirroring public/.htaccess:
 *   php -S localhost:8000 -t public bin/dev-router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/api(/|$)#', $path)) {
    require __DIR__ . '/../public/api/index.php';
    return true;
}
if (preg_match('#(^|/)\.#', $path)) {
    http_response_code(403);
    return true;
}
return false; // let the built-in server serve the file
