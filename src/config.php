<?php
declare(strict_types=1);

/** Read an environment variable, with an optional fallback name and default. */
function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    }
    return ($value === null || $value === '') ? $default : (string) $value;
}

/** Load KEY=VALUE pairs from a local .env file (development only; Railway sets real env vars). */
(function (): void {
    $file = dirname(__DIR__) . '/.env';
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
})();

function config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    // Railway's MySQL service exposes MYSQLHOST etc. (and MYSQL_URL); DB_* are accepted as fallbacks.
    $url = env('MYSQL_URL') ?? env('DATABASE_URL');
    $parsed = $url ? parse_url($url) : [];

    $config = [
        'env' => env('APP_ENV', 'production'),
        'db' => [
            'host' => env('MYSQLHOST') ?? env('DB_HOST') ?? ($parsed['host'] ?? '127.0.0.1'),
            'port' => (int) (env('MYSQLPORT') ?? env('DB_PORT') ?? ($parsed['port'] ?? 3306)),
            'user' => env('MYSQLUSER') ?? env('DB_USER') ?? ($parsed['user'] ?? 'root'),
            'pass' => env('MYSQLPASSWORD') ?? env('DB_PASSWORD') ?? ($parsed['pass'] ?? ''),
            'name' => env('MYSQLDATABASE') ?? env('DB_NAME') ?? ltrim($parsed['path'] ?? '/lazyledger', '/'),
        ],
    ];
    return $config;
}

function is_dev(): bool
{
    return config()['env'] === 'development';
}

date_default_timezone_set('Asia/Manila');
