<?php
declare(strict_types=1);

namespace App\Expense;

use App\Http\ApiException;
use App\Infrastructure\Logger;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

final class ApproveExpense
{
    public function __construct(private readonly PDO $pdo) {}

    public function execute(int $expenseId, array $actor, ?string $comment): array
    {
        if ($this->pdo->inTransaction()) {
            throw new LogicException('Expense already in a transaction.');
        }
        try {
            $this->pdo->beginTransaction();
            $query = $this->pdo->prepare('SELECT id, employee_id, status FROM expenses WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $expenseId]);
            $expense = $query->fetch();
            if ($expense === false) {
                throw new ApiException(404, 'expense_not_found', 'Expense not found.');
            }

            $employeeQuery = $this->pdo->prepare('SELECT manager_id FROM users WHERE id = :id');
            $employeeQuery->execute(['id' => $expense['employee_id']]);
            $employee = $employeeQuery->fetch();
            if ($employee === false) {
                throw new RuntimeException('Expense employee is missing.');
            }
            $managerId = $employee['manager_id'] === null ? null : (int) $employee['manager_id'];
            if ($actor['role'] !== 'manager' || $managerId !== (int) $actor['id'] || $expense['employee_id'] === (int) $actor['id']) {
                throw new ApiException(403, 'forbidden', 'You do not have permission to approve this expense.');
            }

            if ($expense['status'] !== 'pending') {
                throw new ApiException(409, 'expense_not_pending', 'Expense is not in pending status.');
            }

            $update = $this->pdo->prepare("UPDATE expenses SET status = 'approved' WHERE id = :id AND status = 'pending'");
            $update->execute(['id' => $expenseId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Expense update failed.');
            }
            $now = time();
            $insert = $this->pdo->prepare('INSERT INTO expense_approvals (expense_id, approved_by, comment, approved_at) VALUES (:expense_id, :approved_by, :comment, :approved_at)');
            $insert->execute([
                'expense_id' => $expenseId,
                'approved_by' => $actor['id'],
                'comment' => $comment,
                'approved_at' => gmdate('Y-m-d H:i:s', $now),
            ]);
            $this->pdo->commit();

            return [
                'id' => $expenseId,
                'status' => 'approved',
                'approved_by' => (int) $actor['id'],
                'approved_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
            ];
        } catch (Throwable $e) {
            try {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            } catch (Throwable $rollbackError) {
                Logger::error('rollback_failed', [
                    'operation' => 'approve_expense',
                    'exception_class' => get_class($rollbackError),
                    'error_category' => 'database_failure',
                ]);
            }
            throw $e;
        }
    }
}
