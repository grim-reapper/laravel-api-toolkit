<?php

namespace Imran\ApiToolkit\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Imran\ApiToolkit\ApiResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ExceptionRenderer
{
    /**
     * Built-in fallback mapping, used when an exception isn't present (or was
     * removed) from config('api-toolkit.exception_map').
     *
     * @var array<class-string, array{status: int, error_code: string}>
     */
    protected array $builtIn = [
        ValidationException::class => ['status' => 422, 'error_code' => 'VALIDATION_ERROR'],
        AuthenticationException::class => ['status' => 401, 'error_code' => 'UNAUTHENTICATED'],
        AuthorizationException::class => ['status' => 403, 'error_code' => 'FORBIDDEN'],
        AccessDeniedHttpException::class => ['status' => 403, 'error_code' => 'FORBIDDEN'],
        ModelNotFoundException::class => ['status' => 404, 'error_code' => 'NOT_FOUND'],
        NotFoundHttpException::class => ['status' => 404, 'error_code' => 'NOT_FOUND'],
        MethodNotAllowedHttpException::class => ['status' => 405, 'error_code' => 'METHOD_NOT_ALLOWED'],
        ThrottleRequestsException::class => ['status' => 429, 'error_code' => 'TOO_MANY_REQUESTS'],
    ];

    public function __construct(protected ApiResponse $response)
    {
    }

    /**
     * Whether this exception, for this request, should be normalized into
     * the package's JSON envelope rather than left to Laravel's default
     * (HTML) rendering.
     */
    public function shouldHandle(Request $request): bool
    {
        if ($request->expectsJson()) {
            return true;
        }

        foreach (config('api-toolkit.paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    public function render(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ApiException) {
            // ApiException already attaches its own headers internally.
            return $e->render($request);
        }

        $mapped = $this->resolveMapping($e);

        if ($mapped !== null) {
            return $this->withExceptionHeaders($e, $this->renderMapped($e, $mapped));
        }

        if ($e instanceof HttpExceptionInterface) {
            return $this->withExceptionHeaders($e, $this->renderHttpException($e));
        }

        return $this->renderServerError($e);
    }

    /**
     * @return array{status: int, error_code: ?string, message: ?string}|null
     */
    protected function resolveMapping(Throwable $e): ?array
    {
        foreach (config('api-toolkit.exception_map', []) as $class => $config) {
            if ($e instanceof $class) {
                return [
                    'status' => $config['status'] ?? 500,
                    'error_code' => $config['error_code'] ?? null,
                    'message' => $config['message'] ?? null,
                ];
            }
        }

        foreach ($this->builtIn as $class => $config) {
            if ($e instanceof $class) {
                return [
                    'status' => $config['status'],
                    'error_code' => $config['error_code'],
                    'message' => null,
                ];
            }
        }

        return null;
    }

    protected function renderMapped(Throwable $e, array $mapped): JsonResponse
    {
        $status = $mapped['status'];
        $errorCode = $mapped['error_code'];
        $message = $mapped['message'] ?? $this->ownMessage($e);

        return $this->response->error($message, $status, $this->extractErrors($e), $errorCode);
    }

    protected function renderHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $errorCode = config("api-toolkit.error_codes.{$status}");

        return $this->response->error($this->ownMessage($e), $status, $this->extractErrors($e), $errorCode);
    }

    protected function renderServerError(Throwable $e): JsonResponse
    {
        // Deliberately does not log here: Laravel's own exception handler
        // already calls report() for every exception (independent of this
        // renderable hook), so logging again here would double every entry.
        // Set api-toolkit.log_unhandled to true only if you've suppressed
        // Laravel's own reporting for these exceptions and still want them
        // captured via this package's channel.
        if (config('api-toolkit.log_unhandled', false)) {
            try {
                Log::channel(config('api-toolkit.log_channel'))->error($e->getMessage(), [
                    'exception' => $e,
                ]);
            } catch (Throwable) {
                // A misconfigured log_channel must not itself crash the
                // renderer while it's trying to handle a different exception.
            }
        }

        $debug = config('api-toolkit.debug', false);
        $message = $debug ? $this->ownMessage($e) : null;

        $response = $this->response->error($message, 500, null, 'SERVER_ERROR');

        if ($debug) {
            $response->setData($this->response->withDebug($response->getData(true), $e));
        }

        return $response;
    }

    /**
     * Structured error details for any exception that exposes them --
     * ValidationException's array-returning errors(), the
     * Contracts\Validation\Validator convention, or a custom/domain
     * exception (mapped via exception_map) that defines its own public
     * errors() method the same way.
     *
     * is_callable() (rather than method_exists()) is used deliberately: it
     * respects visibility, so a private/protected errors() method defined
     * for an unrelated purpose is correctly skipped instead of crashing with
     * a visibility error. The call itself is still wrapped in a try/catch
     * in case a same-named method has an incompatible signature (e.g.
     * required parameters) -- a normalizing exception must never itself
     * throw while trying to render another exception.
     */
    protected function extractErrors(Throwable $e): mixed
    {
        if ($e instanceof ValidationException) {
            return $e->errors();
        }

        if (is_callable([$e, 'errors'])) {
            try {
                return $e->errors();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function ownMessage(Throwable $e): ?string
    {
        return $e->getMessage() !== '' ? $e->getMessage() : null;
    }

    /**
     * Forwards headers the exception itself carries (e.g. Retry-After on a
     * throttling exception, Allow on a method-not-allowed exception) onto
     * the normalized JSON response, matching Laravel's own default behavior.
     */
    protected function withExceptionHeaders(Throwable $e, JsonResponse $response): JsonResponse
    {
        if ($e instanceof HttpExceptionInterface && $e->getHeaders() !== []) {
            $response->headers->add($e->getHeaders());
        }

        return $response;
    }
}
