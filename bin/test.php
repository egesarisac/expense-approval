<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$url = parse_url(getenv('TEST_APP_URL') ?: '');
$host = $url['host'];
$port = $url['port'] ?? 80;
$log = tempnam(sys_get_temp_dir(), 'expense-api-');
$server = proc_open([PHP_BINARY, '-S', $host . ':' . $port, '-t', 'public', 'public/index.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
if (!is_resource($server)) {
    throw new RuntimeException('Could not start test HTTP server.');
}
fclose($pipes[0]);
putenv('TEST_API_LOG=' . $log);
try {
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @fsockopen($host, $port);
        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    if (!$ready) {
        throw new RuntimeException('Test HTTP server did not become ready.');
    }
    $tests = proc_open([PHP_BINARY, 'vendor/bin/phpunit'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
    $status = is_resource($tests) ? proc_close($tests) : 1;
} finally {
    proc_terminate($server);
    proc_close($server);
    unlink($log);
}
exit($status);
