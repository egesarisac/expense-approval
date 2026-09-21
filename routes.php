<?php
declare(strict_types=1);

use App\Http\JsonResponse;
use App\Http\Router;
use App\Http\Request;
use App\Auth\AuthController;
use App\Expense\ExpenseController;

return static function (Router $router, AuthController $auth, ExpenseController $expenses): void {
    $router->add('GET', '/', static fn (): JsonResponse => new JsonResponse(
        200,
        ['message' => 'Expense Approval API'],
    ));
    $router->add('POST', '/login', [$auth, 'login']);
    $router->add('POST', '/logout', [$auth, 'logout']);
    $router->add('POST', '/expenses/{id}/approve', [$expenses, 'approve']);
};
