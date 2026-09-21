<?php
declare(strict_types=1);

use App\Infrastructure\Database;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    private static ?string $demoPasswordHash = null;
    protected PDO $pdo;

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'test' || getenv('DB_HOST') !== 'test-db'
            || getenv('DB_NAME') !== 'expense_approval_test') {
            throw new RuntimeException('Refusing to reset a non-test database.');
        }
        // Refresh the database
        $this->pdo = Database::connect();
        $this->pdo->exec('DROP TRIGGER IF EXISTS reject_approval');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($this->pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                $this->pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $this->pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
        // Reuse the bcrypt hash to speed up the tests
        self::$demoPasswordHash ??= password_hash('demo-password', PASSWORD_BCRYPT);
        $seed = require __DIR__ . '/../database/seed.php';
        $seed($this->pdo, self::$demoPasswordHash);
    }

    protected function request(string $path, mixed $body = [], ?string $token = null, string $method = 'POST', string $type = 'application/json'): array
    {
        $headers = [];
        $curl = curl_init(rtrim(getenv('TEST_APP_URL'), '/') . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => array_filter(['Content-Type: ' . $type, $token === null ? null : 'Authorization: Bearer ' . $token]),
            CURLOPT_POSTFIELDS => is_string($body) ? $body : json_encode((object) $body, JSON_THROW_ON_ERROR),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower($name)] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $raw = curl_exec($curl);
        self::assertNotFalse($raw, curl_error($curl));
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        self::assertSame('no-store', $headers['cache-control'] ?? null);
        self::assertNotEmpty($headers['x-request-id'] ?? null);
        $json = $raw === '' ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if ($status >= 400) {
            self::assertSame($headers['x-request-id'], $json['request_id']);
        }
        return ['status' => $status, 'json' => $json, 'raw' => $raw, 'headers' => $headers];
    }

    protected function login(string $email = 'manager.a@app.test'): string
    {
        $response = $this->request('/login', ['email' => $email, 'password' => 'demo-password']);
        self::assertSame(200, $response['status']);
        return $response['json']['data']['access_token'];
    }

    protected function assertPending(): void
    {
        self::assertSame('pending', $this->pdo->query('SELECT status FROM expenses WHERE id = 42')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM expense_approvals WHERE expense_id = 42')->fetchColumn());
    }

}
