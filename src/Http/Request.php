<?php
declare(strict_types=1);

namespace App\Http;

use JsonException;
use RuntimeException;
use stdClass;

final class Request
{
    private array $headers;
    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        array $headers = [],
        private ?string $body = null,
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public static function capture(): self
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $name)] = $value;
            }
        }
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? '',
            explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0],
            $headers,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        // Read only when requested, so logout and unknown routes ignore the body.
        if ($this->body === null) {
            $body = file_get_contents('php://input', false, null, 0, 8193);
            if ($body === false) {
                throw new RuntimeException('Could not read request body.');
            }
            $this->body = $body;
        }
        if (strlen($this->body) > 8192) {
            throw new ApiException(413, 'payload_too_large', 'Request body exceeds 8 KiB.');
        }
        $mediaType = strtolower(trim(explode(';', $this->header('Content-Type') ?? '')[0]));
        if ($mediaType !== 'application/json') {
            throw new ApiException(415, 'unsupported_media_type', 'Content-Type must be application/json.');
        }
        try {
            $input = json_decode($this->body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(400, 'invalid_json', 'Request body must be a valid JSON object.');
        }
        if (!$input instanceof stdClass) {
            throw new ApiException(400, 'invalid_json', 'Request body must be a valid JSON object.');
        }
        return $this->json = get_object_vars($input);
    }
}
