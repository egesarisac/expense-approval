<?php
declare(strict_types=1);

header('Content-Type: application/json');

try {
    $pdo = require __DIR__ . '/../bootstrap.php';
    
    echo json_encode(['message' => 'Expense Approval API'], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(500);
    error_log('Application bootstrap failed.');
    echo json_encode(['error' => 'Internal server error.'], JSON_THROW_ON_ERROR);
}
