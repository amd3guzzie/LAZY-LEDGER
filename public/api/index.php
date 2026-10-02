<?php
declare(strict_types=1);

/**
 * REST API front controller. Every /api/* request is rewritten here (see public/.htaccess).
 */

$src = dirname(__DIR__, 2) . '/src';
require_once $src . '/auth.php';
require_once $src . '/router.php';
require_once $src . '/audit.php';
require_once $src . '/notify.php';
foreach (glob($src . '/controllers/*.php') as $file) {
    require_once $file;
}

send_security_headers();

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof HttpError) {
        $body = ['error' => $e->getMessage()];
        if ($e->fields) {
            $body['fields'] = $e->fields;
        }
        json_out($body, $e->getCode());
    }
    if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062) {
        json_out(['error' => 'That record already exists.'], 409);
    }
    error_log('[lazyledger] ' . $e);
    json_out(['error' => is_dev() ? $e->getMessage() : 'Something went wrong on our end. Please try again.'], 500);
});

start_session();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/api#', '', $path);

// CSRF: every state-changing request must echo the session token in a header.
if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && !csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    fail(403, 'Your session has expired. Please refresh the page and try again.');
}

$r = new Router();

// Auth
$r->post('/auth/register', [AuthController::class, 'register']);
$r->post('/auth/login', [AuthController::class, 'login']);
$r->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$r->post('/auth/reset-password', [AuthController::class, 'resetPassword']);
$r->post('/auth/logout', [AuthController::class, 'logout']);
$r->get('/auth/me', [AuthController::class, 'me']);

// Profile & notifications (any role)
$r->get('/profile', [ProfileController::class, 'show']);
$r->put('/profile', [ProfileController::class, 'update']);
$r->put('/profile/password', [ProfileController::class, 'password']);
$r->delete('/profile', [ProfileController::class, 'destroy']);
$r->put('/profile/tour', [ProfileController::class, 'completeTour']);
$r->get('/notifications', [NotificationController::class, 'index']);
$r->put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
$r->put('/notifications/{id}/read', [NotificationController::class, 'markRead']);

// Customer
$r->get('/accounts', [AccountController::class, 'index']);
$r->post('/accounts', [AccountController::class, 'store']);
$r->put('/accounts/{id}', [AccountController::class, 'update']);
$r->delete('/accounts/{id}', [AccountController::class, 'destroy']);

$r->get('/categories', [CategoryController::class, 'index']);

$r->get('/transactions', [TransactionController::class, 'index']);
$r->get('/transactions/export', [TransactionController::class, 'export']);
$r->get('/transactions/{id}', [TransactionController::class, 'show']);
$r->post('/transactions', [TransactionController::class, 'store']);
$r->put('/transactions/{id}', [TransactionController::class, 'update']);
$r->delete('/transactions/{id}', [TransactionController::class, 'destroy']);

$r->get('/budgets', [BudgetController::class, 'index']);
$r->post('/budgets', [BudgetController::class, 'store']);
$r->put('/budgets/total', [BudgetController::class, 'setTotal']);
$r->put('/budgets/{id}', [BudgetController::class, 'update']);
$r->delete('/budgets/{id}', [BudgetController::class, 'destroy']);

$r->get('/stats/summary', [StatsController::class, 'summary']);
$r->get('/stats/monthly', [StatsController::class, 'monthly']);
$r->get('/stats/categories', [StatsController::class, 'categories']);

$r->get('/category-requests', [RequestController::class, 'index']);
$r->post('/category-requests', [RequestController::class, 'store']);
$r->get('/tickets', [TicketController::class, 'index']);
$r->post('/tickets', [TicketController::class, 'store']);

// Staff
$r->get('/staff/overview', [StaffController::class, 'overview']);
$r->get('/staff/category-requests', [StaffController::class, 'requests']);
$r->put('/staff/category-requests/{id}', [StaffController::class, 'resolveRequest']);
$r->get('/staff/tickets', [StaffController::class, 'tickets']);
$r->put('/staff/tickets/{id}', [StaffController::class, 'updateTicket']);
$r->get('/staff/users', [StaffController::class, 'users']);

// Admin
$r->get('/admin/overview', [AdminController::class, 'overview']);
$r->get('/admin/users', [AdminController::class, 'users']);
$r->post('/admin/users', [AdminController::class, 'createUser']);
$r->put('/admin/users/{id}', [AdminController::class, 'updateUser']);
$r->delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
$r->get('/admin/categories', [AdminController::class, 'categories']);
$r->post('/admin/categories', [AdminController::class, 'createCategory']);
$r->put('/admin/categories/{id}', [AdminController::class, 'updateCategory']);
$r->delete('/admin/categories/{id}', [AdminController::class, 'deleteCategory']);
$r->get('/admin/audit-log', [AdminController::class, 'auditLog']);

$r->dispatch($method, $path);
