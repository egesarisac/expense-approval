<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\TestWith;

require_once __DIR__ . '/ApiTestCase.php';

final class AuthTest extends ApiTestCase
{
    public function testLoginNormalizesEmail(): void
    {
        $this->login(' MANAGER.A@APP.TEST ');
    }

    public function testLoginStoresOnlyTokenHash(): void
    {
        $token = $this->login();
        $stored = $this->pdo->query('SELECT token_hash FROM access_tokens')->fetchColumn();
        self::assertSame(hash('sha256', $token), $stored);
        self::assertNotSame($token, $stored);
    }

    public function testLoginIssuesOneHourToken(): void
    {
        $response = $this->request('/login', ['email' => 'manager.a@app.test', 'password' => 'demo-password']);
        self::assertSame(200, $response['status']);
        self::assertSame(3600, $response['json']['data']['expires_in']);
        self::assertSame(3600, (int) $this->pdo->query('SELECT TIMESTAMPDIFF(SECOND, created_at, expires_at) FROM access_tokens')->fetchColumn());
    }

    public function testRepeatedLoginIssuesDistinctTokens(): void
    {
        self::assertNotSame($this->login(), $this->login());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM access_tokens')->fetchColumn());
    }

    public function testLogoutReturnsEmptyResponse(): void
    {
        $response = $this->request('/logout', [], $this->login());
        self::assertSame(204, $response['status']);
        self::assertSame('', $response['raw']);
    }

    public function testLogoutRevokesCurrentToken(): void
    {
        $token = $this->login();
        self::assertSame(204, $this->request('/logout', [], $token)['status']);
        self::assertSame(401, $this->request('/expenses/42/approve', [], $token)['status']);
        $this->assertPending();
    }

    public function testLogoutPreservesOtherTokens(): void
    {
        $first = $this->login();
        $second = $this->login();
        self::assertSame(204, $this->request('/logout', [], $first)['status']);
        self::assertSame(204, $this->request('/logout', [], $second)['status']);
    }

    public function testWrongAndUnknownCredentialsHaveSameError(): void
    {
        $wrong = $this->request('/login', ['email' => 'manager.a@app.test', 'password' => 'wrong-secret']);
        $unknown = $this->request('/login', ['email' => 'unknown@app.test', 'password' => 'wrong-secret']);
        self::assertSame(401, $wrong['status']);
        self::assertSame(401, $unknown['status']);
        self::assertSame($wrong['json']['error'], $unknown['json']['error']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM access_tokens')->fetchColumn());
    }

    #[TestWith([null], 'missing')]
    #[TestWith(['bad-token'], 'malformed')]
    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], 'unknown')]
    public function testInvalidTokenCannotApprove(?string $token): void
    {
        self::assertSame(401, $this->request('/expenses/42/approve', [], $token)['status']);
        $this->assertPending();
    }

    public function testExpiredTokenCannotApprove(): void
    {
        $token = $this->login();
        $this->pdo->exec('UPDATE access_tokens SET created_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR, expires_at = UTC_TIMESTAMP()');
        self::assertSame(401, $this->request('/expenses/42/approve', [], $token)['status']);
        $this->assertPending();
    }

    #[TestWith([[]], 'missing fields')]
    #[TestWith([['email' => "' OR 1=1 --", 'password' => 'x']], 'SQL-like email')]
    #[TestWith([['email' => 'a@app.test', 'password' => []]], 'password type')]
    public function testInvalidLoginInputReturnsValidationError(array $body): void
    {
        self::assertSame(422, $this->request('/login', $body)['status']);
    }
}
