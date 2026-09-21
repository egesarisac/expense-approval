<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\TestWith;

require_once __DIR__ . '/ApiTestCase.php';

final class ExpenseApprovalTest extends ApiTestCase
{
    public function testManagerApprovesAssignedExpense(): void
    {
        $response = $this->request('/expenses/42/approve', [], $this->login());
        self::assertSame(200, $response['status']);
        self::assertSame(1, $response['json']['data']['approved_by']);
        self::assertSame('approved', $this->pdo->query('SELECT status FROM expenses WHERE id = 42')->fetchColumn());
        $rows = $this->pdo->query('SELECT * FROM expense_approvals WHERE expense_id = 42')->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['approved_by']);
        self::assertSame(str_replace(' ', 'T', $rows[0]['approved_at']) . 'Z', $response['json']['data']['approved_at']);
    }

    public function testSqlLikeCommentIsStoredLiterally(): void
    {
        $comment = "Sentinel-comment '; DROP TABLE users; --";
        self::assertSame(200, $this->request('/expenses/42/approve', ['comment' => $comment], $this->login())['status']);
        self::assertSame($comment, $this->pdo->query('SELECT comment FROM expense_approvals WHERE expense_id = 42')->fetchColumn());
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testRepeatedApprovalReturnsConflictWithoutDuplicateHistory(): void
    {
        $token = $this->login();
        self::assertSame(200, $this->request('/expenses/42/approve', [], $token)['status']);
        self::assertSame(409, $this->request('/expenses/42/approve', [], $token)['status']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM expense_approvals WHERE expense_id = 42')->fetchColumn());
    }

    public function testSuccessfulApprovalDoesNotLogSensitiveValues(): void
    {
        $token = $this->login();
        $comment = 'private-comment-sentinel';
        self::assertSame(200, $this->request('/expenses/42/approve', ['comment' => $comment], $token)['status']);
        $logs = file_get_contents(getenv('TEST_API_LOG'));
        foreach ([$token, $comment, 'demo-password', 'manager.a@app.test'] as $secret) {
            self::assertStringNotContainsString($secret, $logs);
        }
    }

    public function testEmployeeCannotApprove(): void
    {
        self::assertSame(403, $this->request('/expenses/42/approve', [], $this->login('employee.a@app.test'))['status']);
        $this->assertPending();
    }

    public function testUnrelatedManagerCannotApprove(): void
    {
        self::assertSame(403, $this->request('/expenses/42/approve', [], $this->login('manager.b@app.test'))['status']);
        $this->assertPending();
    }

    #[TestWith(['employee.a@app.test'], 'employee')]
    #[TestWith(['manager.b@app.test'], 'unrelated manager')]
    public function testPermissionIsCheckedBeforeApprovedStatus(string $email): void
    {
        self::assertSame(403, $this->request('/expenses/44/approve', [], $this->login($email))['status']);
    }

    public function testManagerCannotApproveOwnExpense(): void
    {
        $token = $this->login();
        $this->pdo->exec('UPDATE users SET manager_id = 1 WHERE id = 1');
        $this->pdo->exec('UPDATE expenses SET employee_id = 1 WHERE id = 42');
        self::assertSame(403, $this->request('/expenses/42/approve', [], $token)['status']);
        $this->assertPending();
    }

    public function testMissingExpenseReturnsNotFound(): void
    {
        self::assertSame(404, $this->request('/expenses/999/approve', [], $this->login())['status']);
    }

    public function testInsertFailureRollsBackAndReturnsSanitizedCorrelatedError(): void
    {
        $token = $this->login();
        $this->pdo->exec("CREATE TRIGGER reject_approval BEFORE INSERT ON expense_approvals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'private-db-failure-sentinel'");
        try {
            $response = $this->request('/expenses/42/approve', [], $token);
            self::assertSame(500, $response['status']);
            // This connection is independent of the HTTP action's transaction.
            $this->assertPending();
            self::assertStringNotContainsString('private-db-failure-sentinel', $response['raw']);
            $logs = file_get_contents(getenv('TEST_API_LOG'));
            self::assertStringContainsString($response['headers']['x-request-id'], $logs);
            self::assertStringContainsString('request_failed', $logs);
            self::assertStringNotContainsString('private-db-failure-sentinel', $logs);
            self::assertStringNotContainsString($token, $logs);
        } finally {
            $this->pdo->exec('DROP TRIGGER reject_approval');
        }
    }

    #[TestWith(['42', '{"comment":42}', 'application/json', 422], 'comment type')]
    #[TestWith(['42', '{', 'application/json', 400], 'bad JSON')]
    #[TestWith(['42', '[]', 'application/json', 400], 'array JSON')]
    #[TestWith(['42', '{}', 'text/plain', 415], 'media type')]
    public function testInvalidApprovalDoesNotMutate(string $id, string $body, string $type, int $status): void
    {
        self::assertSame($status, $this->request("/expenses/$id/approve", $body, $this->login(), 'POST', $type)['status']);
        $this->assertPending();
    }
}
