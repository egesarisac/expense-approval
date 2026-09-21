<?php
declare(strict_types=1);

namespace App\Infrastructure;

use Throwable;

final class Logger
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('error', $event, $context);
    }

    private static function write(string $level, string $event, array $context): void
    {
        try {
            // Filter the fields to avoid logging sensitive data
            $fields = array_intersect_key($context, array_flip([
                'request_id', 'route_name', 'method', 'status',
                'operation', 'exception_class', 'error_category',
            ]));
            $fields = array_filter($fields, static fn (mixed $value): bool => is_scalar($value) || $value === null);
            
            error_log(json_encode([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'event' => $event,
            ] + $fields, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            // Ignore JSON encoding errors
        }
    }
}
