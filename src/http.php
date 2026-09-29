<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Thrown to abort a request with an HTTP status and a user-facing message. */
final class HttpError extends RuntimeException
{
    public function __construct(int $status, string $message, public readonly array $fields = [])
    {
        parent::__construct($message, $status);
    }
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message, array $fields = []): never
{
    throw new HttpError($status, $message, $fields);
}

function read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fail(400, 'Request body must be valid JSON.');
    }
    return $data;
}

/**
 * Collects field errors while reading validated input.
 * Usage: $v = new Validator($body); $name = $v->str('name', 'Account name', max: 80); $v->done();
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null && $this->data[$key] !== '';
    }

    public function str(string $key, string $label, int $max = 255, bool $required = true, int $min = 1): ?string
    {
        $value = $this->data[$key] ?? null;
        if (!is_scalar($value) && $value !== null) {
            $this->errors[$key] = "$label is invalid.";
            return null;
        }
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            return $required ? null : '';
        }
        if (mb_strlen($value) < $min) {
            $this->errors[$key] = "$label must be at least $min characters.";
        } elseif (mb_strlen($value) > $max) {
            $this->errors[$key] = "$label must be at most $max characters.";
        }
        return $value;
    }

    public function email(string $key, string $label = 'Email'): ?string
    {
        $value = $this->str($key, $label, 190);
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$key] = 'Please enter a valid email address.';
        }
        return $value === null ? null : strtolower($value);
    }

    public function money(string $key, string $label, bool $allowZero = false, bool $required = true): ?float
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            return null;
        }
        if (!is_numeric($value)) {
            $this->errors[$key] = "$label must be a number.";
            return null;
        }
        $num = round((float) $value, 2);
        if ($num < 0 || (!$allowZero && $num == 0)) {
            $this->errors[$key] = $allowZero ? "$label cannot be negative." : 'Valid positive amount required.';
        } elseif ($num > 9999999999.99) {
            $this->errors[$key] = "$label is too large.";
        }
        return $num;
    }

    public function enum(string $key, string $label, array $allowed, bool $required = true): ?string
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            $this->errors[$key] = "$label is invalid.";
            return null;
        }
        return $value;
    }

    public function date(string $key, string $label, bool $required = true): ?string
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            return null;
        }
        if (!is_string($value) || !valid_date($value)) {
            $this->errors[$key] = "$label must be a valid date.";
            return null;
        }
        return $value;
    }

    public function int(string $key, string $label, bool $required = true): ?int
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->errors[$key] = "$label is invalid.";
            return null;
        }
        return (int) $value;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->data[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function error(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    public function done(): void
    {
        if ($this->errors) {
            fail(422, reset($this->errors), $this->errors);
        }
    }
}

function valid_date(string $value): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

/** Parse "YYYY-MM" (defaults to current month) into [firstDay, lastDay] strings. */
function month_range(?string $month): array
{
    if (!$month || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $month = date('Y-m');
    }
    $start = new DateTimeImmutable($month . '-01');
    return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d'), $month];
}

/** Read ?page and ?per_page, returning [page, perPage, offset]. */
function pagination(int $default = 10, int $max = 100): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = (int) ($_GET['per_page'] ?? $default);
    $per = min(max($per, 1), $max);
    return [$page, $per, ($page - 1) * $per];
}

function paged(array $items, int $total, int $page, int $per): array
{
    return [
        'data' => $items,
        'meta' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'pages' => max(1, (int) ceil($total / $per))],
    ];
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "img-src 'self' data:; "
        . "connect-src 'self' https://api.frankfurter.dev; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
