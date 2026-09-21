<?php
declare(strict_types=1);

use App\Http\JsonResponse;
use App\Http\Router;

return static function (Router $router): void {
    $router->add('GET', '/', static fn (): JsonResponse => new JsonResponse(
        200,
        ['message' => 'Expense Approval API'],
    ));
};
