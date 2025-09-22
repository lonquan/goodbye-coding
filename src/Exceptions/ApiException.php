<?php

declare(strict_types=1);

namespace GoodbyeCoding\Migration\Exceptions;

/**
 * API 异常类.
 *
 * 处理所有 API 调用相关的异常
 */
class ApiException extends MigrationException
{
    protected ?int $httpCode = null;
    protected ?string $responseBody = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $context = [],
        ?int $httpCode = null,
        ?string $responseBody = null
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->httpCode = $httpCode;
        $this->responseBody = $responseBody;
    }

    public function getHttpCode(): ?int
    {
        return $this->httpCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public static function unauthorized(string $message = 'API authentication failed', array $context = []): self
    {
        return new self($message, 401, null, $context, 401);
    }

    public static function notFound(string $message = 'Resource not found', array $context = []): self
    {
        return new self($message, 404, null, $context, 404);
    }

    public static function rateLimit(string $message = 'API rate limit exceeded', array $context = []): self
    {
        return new self($message, 403, null, $context, 403);
    }

    public static function validation(string $message = 'Validation failed', array $context = []): self
    {
        return new self($message, 422, null, $context, 422);
    }

    public static function server(string $message = 'Server error', array $context = []): self
    {
        return new self($message, 500, null, $context, 500);
    }
}
