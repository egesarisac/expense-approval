<?php
declare(strict_types=1);

header('Content-Type: application/json');
echo json_encode(['message' => 'Expense Approval API'], JSON_THROW_ON_ERROR);
