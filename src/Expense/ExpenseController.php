<?php
declare(strict_types=1);

namespace App\Expense;

use App\Auth\AuthService;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Http\Request;

final class ExpenseController
{
    public function __construct(private readonly AuthService $auth, private readonly ApproveExpense $approveExpense) {}

    public function approve(Request $request, string $id): JsonResponse
    {
        $actor = $this->auth->authenticate($request->header('Authorization'));
        $fields = $request->json();
        $errors = [];


        $comment = $fields['comment'] ?? null;
        if ($comment !== null) {
            if (!is_string($comment)) {
                $errors['comment'] = 'Comment must be a string or null.';
            } else {
                $comment = trim($comment);
                if (mb_strlen($comment, 'UTF-8') > 500) {
                    $errors['comment'] = 'Comment must be 500 characters maximum.';
                }
                if ($comment === '') {
                    $comment = null;
                }
            }
        }
        if ($errors !== []) {
            throw new ApiException(422, 'validation_failed', 'Request validation failed.', $errors);
        }

        return new JsonResponse(200, ['data' => $this->approveExpense->execute((int) $id, $actor, $comment)]);
    }
}
