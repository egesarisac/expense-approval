<?php
declare(strict_types=1);

use App\Infrastructure\Database;

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');

return Database::connect();
