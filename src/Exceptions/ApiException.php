<?php

namespace Imran\ApiToolkit\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Imran\ApiToolkit\ApiResponse;
use RuntimeException;
use Throwable;

/**
 * A throwable, typed API error. Self-renders into the package's standard
 * JSON envelope even if api-toolkit.auto_register is disabled, since
 * Laravel calls an exception's own render() method when it defines one.
 */
class ApiException extends RuntimeException
{
    protected array $headers;

    protected mixed $errors;

    protected ?string $errorCode;

    protected int $statusCode;

    public function __construct(
        string $message = '',
        int $statusCode = 400,
        ?string $errorCode = null,
        mixed $errors = null,
        array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->errors = $errors;
        $this->headers = $headers;
    }

    public static function make(string $message = '', int $statusCode = 400, ?string $errorCode = null, mixed $errors = null): static
    {
        return new static($message, $statusCode, $errorCode, $errors);
    }

    public static function badRequest(?string $message = null, mixed $errors = null): static
    {
        return new static($message ?? '', 400, 'BAD_REQUEST', $errors);
    }

    public static function unauthorized(?string $message = null): static
    {
        return new static($message ?? '', 401, 'UNAUTHENTICATED');
    }

    public static function forbidden(?string $message = null): static
    {
        return new static($message ?? '', 403, 'FORBIDDEN');
    }

    public static function notFound(?string $message = null): static
    {
        return new static($message ?? '', 404, 'NOT_FOUND');
    }

    public static function conflict(?string $message = null, mixed $errors = null): static
    {
        return new static($message ?? '', 409, 'CONFLICT', $errors);
    }

    public static function validation(mixed $errors, ?string $message = null): static
    {
        return new static($message ?? '', 422, 'VALIDATION_ERROR', $errors);
    }

    public static function tooManyRequests(?string $message = null, ?int $retryAfterSeconds = null): static
    {
        return new static(
            $message ?? '',
            429,
            'TOO_MANY_REQUESTS',
            null,
            $retryAfterSeconds !== null ? ['Retry-After' => (string) $retryAfterSeconds] : []
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Duck-typed the same way as Illuminate\Contracts\Validation\Validator
     * and ValidationException, so ExceptionRenderer treats it identically.
     */
    public function errors(): mixed
    {
        return $this->errors;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function render(Request $request): JsonResponse
    {
        $response = app(ApiResponse::class)->error(
            $this->getMessage() !== '' ? $this->getMessage() : null,
            $this->statusCode,
            $this->errors,
            $this->errorCode
        );

        if ($this->headers !== []) {
            $response->headers->add($this->headers);
        }

        return $response;
    }

    /**
     * Skip Laravel's default exception logging for expected (< 500) API
     * errors -- they're normal application flow, not failures, matching
     * how Laravel itself treats ValidationException/ModelNotFoundException
     * by default. Genuine 5xx errors are still logged normally. Override
     * in a subclass for different behavior.
     */
    public function report(): bool
    {
        return $this->statusCode < 500;
    }

    public function context(): array
    {
        return array_filter([
            'error_code' => $this->errorCode,
            'status_code' => $this->statusCode,
        ]);
    }
}
