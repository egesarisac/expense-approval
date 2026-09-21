<?php
declare(strict_types=1);

namespace App\Auth;

use App\Http\ApiException;
use PDO;

final class AuthService
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$EoaYX5c9Md4kNXWA7DUwmes4laswnKpMnJouwZzcNr4CCuRrCHKoC';

    public function __construct(private readonly PDO $pdo) {}

    public function login(string $email, string $password): array
    {
        $query = $this->pdo->prepare('SELECT id, password FROM users WHERE email = :email');
        $query->execute(['email' => $email]);
        $user = $query->fetch();
        $valid = password_verify($password, $user === false ? self::DUMMY_PASSWORD_HASH : $user['password']);
        if (!$valid || $user === false) {
            throw new ApiException(401, 'invalid_credentials', 'Invalid email or password.');
        }

        $token = bin2hex(random_bytes(32));
        $now = time();
        $insert = $this->pdo->prepare('INSERT INTO access_tokens (user_id, token_hash, created_at, expires_at) VALUES (:user_id, :token_hash, :created_at, :expires_at)');
        $insert->execute([
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'expires_at' => gmdate('Y-m-d H:i:s', $now + 3600),
        ]);

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600];
    }

    public function authenticate(?string $authorization): array
    {
        if ($authorization === null || !preg_match('/\A(?i:Bearer)[ \t]+([a-f0-9]{64})\z/', $authorization, $matches)) {
            throw $this->unauthenticated();
        }

        $query = $this->pdo->prepare('SELECT t.id AS token_id, u.id, u.email, u.role, u.manager_id
            FROM access_tokens t JOIN users u ON u.id = t.user_id
            WHERE t.token_hash = :token_hash AND t.expires_at > UTC_TIMESTAMP()');
        $query->execute(['token_hash' => hash('sha256', $matches[1])]);
        $user = $query->fetch();
        if ($user === false) {
            throw $this->unauthenticated();
        }
        return $user;
    }

    public function logout(int $tokenId): void
    {
        $query = $this->pdo->prepare('DELETE FROM access_tokens WHERE id = :id');
        $query->execute(['id' => $tokenId]);
    }

    private function unauthenticated(): ApiException
    {
        return new ApiException(401, 'unauthenticated', 'Authentication required.');
    }
}
