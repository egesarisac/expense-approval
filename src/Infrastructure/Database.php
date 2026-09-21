<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        $config = [];
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
            $value = getenv($name);
            if ($value === false || $value === '') {
                throw new RuntimeException('Missing database configuration.');
            }
            $config[$name] = $value;
        }

        $pdo = new PDO(
            "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4",
            $config['DB_USER'],
            $config['DB_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ],
        );
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
