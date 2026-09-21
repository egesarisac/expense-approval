<?php
declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    private readonly ?string $json;

    public function __construct(
        public readonly int $status,
        ?array $body = null,
        private readonly array $headers = [],
    ) {
        $this->json = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);
    }

    public static function error(ApiException $error, string $requestId): self
    {
        $body = ['code' => $error->errorCode, 'message' => $error->getMessage()];
        if ($error->details !== []) {
            $body['details'] = $error->details;
        }
        return new self($error->status, ['error' => $body, 'request_id' => $requestId], $error->headers);
    }

    public function send(string $requestId): void
    {
        http_response_code($this->status);
        header('X-Request-ID: ' . $requestId);
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->json !== null && $this->status !== 204) {
            header('Content-Type: application/json; charset=utf-8');
            echo $this->json;
        }
    }
}
