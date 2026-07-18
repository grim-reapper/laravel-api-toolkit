<?php

namespace Imran\ApiToolkit;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ApiResponse
{
    public function success($data = null, ?string $message = null, int $code = 200, array $meta = [])
    {
        if (config('api-toolkit.paginate', true) && ($paginated = $this->extractPaginatedPayload($data)) !== null) {
            $data = $paginated['data'];
            $meta = ['pagination' => $paginated['pagination']] + $meta;
        }

        $payload = [
            'success' => true,
            'message' => $message ?? $this->message('success'),
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return Response::json($payload, $code);
    }

    public function created($data = null, ?string $message = null, array $meta = [])
    {
        return $this->success($data, $message ?? $this->message('created'), 201, $meta);
    }

    public function deleted(?string $message = null)
    {
        return $this->success(null, $message ?? $this->message('deleted'), 200);
    }

    public function noContent()
    {
        return Response::noContent();
    }

    public function loading(?string $message = null, array $data = [])
    {
        return Response::json([
            'success' => true,
            'status' => 'processing',
            'message' => $message ?? $this->message('processing'),
            'data' => $data,
        ], 202);
    }

    public function error(?string $message = null, int $code = 400, $errors = null, ?string $errorCode = null)
    {
        $payload = [
            'success' => false,
            'message' => $message ?? $this->statusDefaultMessage($code),
            'error_code' => $this->resolveErrorCode($code, $errorCode),
        ];

        if ($errors !== null) {
            $formattedErrors = $this->formatErrors($errors);

            // Omit the key entirely rather than shipping "errors": [] --
            // consistent with how `meta` is only added when non-empty.
            if (! empty($formattedErrors)) {
                $payload['errors'] = $formattedErrors;
            }
        }

        return Response::json($payload, $code);
    }

    public function unauthorized(?string $message = null)
    {
        return $this->error($message, 401);
    }

    public function forbidden(?string $message = null)
    {
        return $this->error($message, 403);
    }

    public function notFound(?string $message = null)
    {
        return $this->error($message, 404);
    }

    public function validationError($errors, ?string $message = null)
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Attach environment-aware debug information for an unhandled exception.
     */
    public function withDebug(array $payload, Throwable $e): array
    {
        if (! config('api-toolkit.debug', false)) {
            return $payload;
        }

        $debug = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if (config('api-toolkit.show_trace', false)) {
            $debug['trace'] = collect($e->getTrace())->map(fn ($frame) => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ])->all();
        }

        $payload['debug'] = $debug;

        return $payload;
    }

    /**
     * Normalize validation errors into the configured shape (flat|nested).
     */
    protected function formatErrors($errors): array
    {
        $bag = $this->toMessageBag($errors);

        if (config('api-toolkit.errors_format', 'flat') === 'nested') {
            return $bag->toArray();
        }

        $flat = [];

        foreach ($bag->toArray() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $flat[] = ['field' => $field, 'message' => $message];
            }
        }

        return $flat;
    }

    protected function toMessageBag($errors): MessageBag
    {
        if ($errors instanceof MessageBag) {
            return $errors;
        }

        if ($errors instanceof ValidationException) {
            return new MessageBag($errors->errors());
        }

        if ($errors instanceof ValidatorContract) {
            return $errors->errors();
        }

        if ($errors instanceof Arrayable) {
            $errors = $errors->toArray();
        }

        if (is_string($errors)) {
            $errors = ['error' => [$errors]];
        }

        // Anything else (an arbitrary object) would mangle private/protected
        // property names into the response via the (array) cast below, so
        // it's reduced to a safe, generic message instead.
        if (is_object($errors)) {
            $errors = ['error' => [get_class($errors).' cannot be converted to validation errors.']];
        }

        return new MessageBag((array) $errors);
    }

    /**
     * If $data is a paginator (raw, or wrapped in a Resource collection),
     * extract its items and build the pagination meta.
     *
     * @return array{data: mixed, pagination: array}|null
     */
    protected function extractPaginatedPayload($data): ?array
    {
        $collection = $data instanceof ResourceCollection ? $data : null;
        $resource = $collection?->resource ?? $data;

        if (! $this->isPaginator($resource)) {
            return null;
        }

        if ($collection !== null) {
            // The caller already built a proper Resource collection (e.g.
            // UserResource::collection($paginator)) -- trust its own
            // serialization, which resolves each resource correctly.
            $wire = $collection->toResponse($this->currentRequest())->getData(true);

            return [
                'data' => $wire['data'] ?? [],
                'pagination' => array_merge($wire['meta'] ?? [], ['nav' => $wire['links'] ?? []]),
            ];
        }

        // A raw paginator with no resource wrapping: its items could be
        // Eloquent models, plain arrays, stdClass rows (e.g. from
        // DB::table()->paginate()), or scalars, so they're normalized here
        // rather than assuming a JsonResource-shaped collection.
        $items = collect($resource->items())->map(function ($item) {
            if ($item instanceof JsonResource) {
                return $item->resolve($this->currentRequest());
            }

            if ($item instanceof Arrayable) {
                return $item->toArray();
            }

            return is_object($item) ? (array) $item : $item;
        })->all();

        return [
            'data' => $items,
            'pagination' => $this->paginationMeta($resource),
        ];
    }

    protected function paginationMeta($paginator): array
    {
        $meta = [
            'per_page' => $paginator->perPage(),
            'path' => $paginator->path(),
        ];

        if ($paginator instanceof CursorPaginatorContract) {
            $meta['next_cursor'] = $paginator->nextCursor()?->encode();
            $meta['prev_cursor'] = $paginator->previousCursor()?->encode();
        } else {
            $meta['current_page'] = $paginator->currentPage();
            $meta['from'] = $paginator->firstItem();
            $meta['to'] = $paginator->lastItem();

            if ($paginator instanceof LengthAwarePaginatorContract) {
                $meta['last_page'] = $paginator->lastPage();
                $meta['total'] = $paginator->total();
            }
        }

        $meta['nav'] = [
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];

        if ($paginator instanceof LengthAwarePaginatorContract) {
            $meta['nav']['first'] = $paginator->url(1);
            $meta['nav']['last'] = $paginator->url($paginator->lastPage());
        }

        return $meta;
    }

    protected function isPaginator($value): bool
    {
        return $value instanceof LengthAwarePaginatorContract
            || $value instanceof PaginatorContract
            || $value instanceof CursorPaginatorContract;
    }

    /**
     * Resolves the current request even outside a real HTTP lifecycle (e.g.
     * a queued job or console command building a paginated payload), where
     * the 'request' binding may not exist.
     */
    protected function currentRequest(): Request
    {
        return app()->bound('request') ? app('request') : Request::create('/');
    }

    protected function resolveErrorCode(int $code, ?string $override): ?string
    {
        return $override ?? config("api-toolkit.error_codes.{$code}");
    }

    /**
     * The default message for an error response of the given HTTP status,
     * falling back through config('messages') and finally to the status's
     * own standard HTTP reason phrase so a message is never left empty.
     */
    protected function statusDefaultMessage(int $code): string
    {
        $key = config("api-toolkit.status_messages.{$code}");

        if ($key && ($text = $this->message($key)) !== '') {
            return $text;
        }

        return SymfonyResponse::$statusTexts[$code] ?? 'An error occurred.';
    }

    protected function message(string $key): string
    {
        $translated = __("api-toolkit::api-toolkit.{$key}");

        if ($translated !== "api-toolkit::api-toolkit.{$key}") {
            return $translated;
        }

        return config("api-toolkit.messages.{$key}", '');
    }
}
