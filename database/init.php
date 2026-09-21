<?php
declare(strict_types=1);

$pdo = null;
$exitCode = 0;
try {
    $pdo = require __DIR__ . '/../bootstrap.php';

    $tableQuery = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'";
    $tables = $pdo->query($tableQuery)->fetchAll(PDO::FETCH_COLUMN);
    if ($tables === []) {
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        $tables = $pdo->query($tableQuery)->fetchAll(PDO::FETCH_COLUMN);
    }

    $empty = true;
    foreach ($tables as $table) {
        $identifier = '`' . str_replace('`', '``', $table) . '`';
        if ($pdo->query("SELECT 1 FROM $identifier LIMIT 1")->fetchColumn() !== false) {
            $empty = false;
            break;
        }
    }
    if ($empty) {
        $seed = require __DIR__ . '/seed.php';
        $seed($pdo);
        fwrite(STDOUT, "Database initialized with seed data.\n");
    } else {
        fwrite(STDOUT, "Existing database recognized; no data changed.\n");
    }
} catch (Throwable $e) {
    $message = get_class($e) === RuntimeException::class
        ? $e->getMessage() : 'Database initialization failed.';
    fwrite(STDERR, $message . "\n");
    $exitCode = 1;
}
exit($exitCode);
