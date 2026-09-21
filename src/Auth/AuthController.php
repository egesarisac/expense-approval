<?php
declare(strict_types=1);

namespace App\Auth;

use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Http\Request;

final class AuthController
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(Request $request): JsonResponse
    {
        $fields = $request->json();
        $errors = [];

        $email = is_string($fields['email'] ?? null) ? strtolower(trim($fields['email'])) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email is required.';
        }
        $password = $fields['password'] ?? null;
        if (!is_string($password) || $password === '') {
            $errors['password'] = 'Password is required.';
        }
        if ($errors !== []) {
            throw new ApiException(422, 'validation_failed', 'Request validation failed.', $errors);
        }

        return new JsonResponse(200, ['data' => $this->authService->login($email, $password)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->authService->authenticate($request->header('Authorization'));
        $this->authService->logout((int) $user['token_id']);
        return new JsonResponse(204);
    }
}
