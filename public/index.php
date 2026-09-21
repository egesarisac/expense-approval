<?php
declare(strict_types=1);

use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Http\Router;
use App\Http\Request;
use App\Infrastructure\Logger;
use App\Auth\AuthController;
use App\Auth\AuthService;
use App\Expense\ApproveExpense;
use App\Expense\ExpenseController;

$requestId = bin2hex(random_bytes(16));

$request = null;
$router = null;
$operation = 'bootstrap';

try {
    $pdo = require __DIR__ . '/../bootstrap.php';

    $operation = 'dispatch';
    $request = Request::capture();
    $router = new Router();
    $registerRoutes = require __DIR__ . '/../routes.php';
    $authService = new AuthService($pdo);
    $auth = new AuthController($authService);
    $expenses = new ExpenseController($authService, new ApproveExpense($pdo));
    $registerRoutes($router, $auth, $expenses);
    $response = $router->dispatch($request);
} catch (ApiException $e) {
    $response = JsonResponse::error($e, $requestId);
} catch (Throwable $e) {
    Logger::error('request_failed', [
        'request_id' => $requestId,
        'operation' => $operation,
        'exception_class' => get_class($e),
        'error_category' => $e instanceof PDOException ? 'database_failure' : 'unexpected_failure',
    ]);
    $response = JsonResponse::error(
        new ApiException(500, 'internal_error', 'An unexpected error occurred.'),
        $requestId,
    );
}

$response->send($requestId);
Logger::info('request_completed', [
    'request_id' => $requestId,
    'route_name' => $router?->routeName() ?? 'unknown',
    'method' => $request?->method ?? ($_SERVER['REQUEST_METHOD'] ?? ''),
    'status' => $response->status,
]);
