<?php
declare(strict_types=1);

try {
    $pdo = require __DIR__ . '/../bootstrap.php';
    exit(0);
} catch (Throwable) {
    exit(1);
}
