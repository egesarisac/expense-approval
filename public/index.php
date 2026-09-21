<?php
declare(strict_types=1);

use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Http\Router;
use App\Infrastructure\Logger;

$requestId = bin2hex(random_bytes(16));

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$router = null;
$operation = 'bootstrap';

try {
    $pdo = require __DIR__ . '/../bootstrap.php';

    $operation = 'dispatch';
    $router = new Router();
    $registerRoutes = require __DIR__ . '/../routes.php';
    $registerRoutes($router);
    $path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
    $response = $router->dispatch($method, $path);
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
    'method' => $method,
    'status' => $response->status,
]);
