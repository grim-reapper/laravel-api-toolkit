# Laravel API Toolkit

Standardized JSON API responses and an automatic, environment-aware exception handler for Laravel APIs — built for teams shipping a REST/JSON API to a frontend (Next.js, Vue, mobile, etc.) that needs one predictable response shape, always.

- A clean `ApiResponse` facade/trait: `ApiResponse::success($data)`, `ApiResponse::error($message, $code)`, `ApiResponse::loading()`, and more.
- Zero-config exception normalization: validation errors, `ModelNotFound`, auth/authorization failures, 404/405/429, and uncaught 500s are all automatically converted into the same JSON envelope — no changes to your `Handler.php` or `bootstrap/app.php` required.
- Validation errors are flattened into a frontend-friendly `field`/`message` array by default (configurable back to Laravel's native nested shape).
- Environment-aware: stack traces and exception details are only ever included when `APP_DEBUG` (or the package's own debug flag) is on.
- A typed `ApiException` you can throw directly from domain/business code (`throw ApiException::notFound('Order not found.')`) with its own status/error_code/structured errors/headers.
- Paginators passed into `success()` are automatically unwrapped into `data` + `meta.pagination` — no manual reshaping needed.
- Custom/domain exceptions mapped via `exception_map` can carry their own structured `errors` array, and HTTP headers an exception carries (`Retry-After`, `Allow`, ...) are preserved onto the normalized response.

## Installation

```bash
composer require imran/laravel-api-toolkit
```

The service provider and `ApiResponse` facade are auto-discovered. Publish the config (and optionally the language file) if you want to customize anything:

```bash
php artisan vendor:publish --tag=api-toolkit-config
php artisan vendor:publish --tag=api-toolkit-lang
```

## Response contracts

**Success**
```json
{
    "success": true,
    "message": "Request was successful.",
    "data": { "id": 1, "name": "Jane Doe" },
    "meta": { "total": 1 }
}
```
`meta` is omitted entirely when empty.

**Error**
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "error_code": "VALIDATION_ERROR",
    "errors": [
        { "field": "email", "message": "The email field is required." }
    ]
}
```
`errors` is omitted when not applicable. With `api-toolkit.debug` enabled, error responses also include a `debug` key (`exception`, `file`, `line`, and `trace` if `show_trace` is on) — but only for genuinely uncaught/unmapped exceptions (the 500 fallback). A mapped/known exception (validation, not-found, your own `exception_map`/`ApiException` entries, ...) never gets a `debug` key regardless of `debug` mode, since its status/message/error_code are already accurate and self-explanatory — `debug` exists to help diagnose the unexpected, not to duplicate what's already in the response.

**Loading / processing** (HTTP 202 — for async jobs / polling endpoints)
```json
{
    "success": true,
    "status": "processing",
    "message": "Your request is being processed.",
    "data": { "job_id": "abc-123" }
}
```

**Paginated success** — pass a paginator straight into `success()`:
```php
return ApiResponse::success(User::paginate(15));
// or with a resource: return ApiResponse::success(UserResource::collection(User::paginate(15)));
```
```json
{
    "success": true,
    "message": "Request was successful.",
    "data": [ { "id": 1 }, { "id": 2 } ],
    "meta": {
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 42,
            "last_page": 3,
            "from": 1,
            "to": 15,
            "path": "https://example.com/api/users",
            "nav": { "first": "...?page=1", "last": "...?page=3", "prev": null, "next": "...?page=2" }
        }
    }
}
```
Works with `LengthAwarePaginator` (`paginate()`), the simple `Paginator` (`simplePaginate()` — same shape minus `total`/`last_page`/`nav.last`), and `CursorPaginator` (`cursorPaginate()` — `next_cursor`/`prev_cursor` instead of page numbers), whether passed raw or wrapped in a Resource collection. Set `api-toolkit.paginate` to `false` to disable and pass paginators through untouched.

## Usage

### Facade

```php
use Imran\ApiToolkit\Facades\ApiResponse;

return ApiResponse::success($user);
return ApiResponse::success($user, 'User fetched.', 200, ['total' => 1]);
return ApiResponse::created($user);
return ApiResponse::deleted();
return ApiResponse::noContent();
return ApiResponse::loading('Export queued.', ['job_id' => $job->id]);

return ApiResponse::error('Something went wrong.', 400);
return ApiResponse::unauthorized();
return ApiResponse::forbidden();
return ApiResponse::notFound('User not found.');
return ApiResponse::validationError($validator->errors());
```

### Controller trait

Prefer calling these methods directly on the controller instead of importing the facade everywhere:

```php
use Imran\ApiToolkit\Traits\ApiResponser;

class UserController extends Controller
{
    use ApiResponser;

    public function show(User $user)
    {
        return $this->success($user);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        return $this->created($user);
    }
}
```

The trait uses common names (`success`, `error`, `created`, `deleted`, `forbidden`, `notFound`, ...). If a base controller you're adopting this into already defines one of these itself, PHP will raise a "trait method has not been applied, because there are collisions" fatal error. Alias around it:

```php
class Controller extends BaseController
{
    use ApiResponser {
        success as protected apiSuccess;
        error as protected apiError;
    }

    // your own success()/error() methods keep working; call apiSuccess()/apiError() for this package's versions
}
```

### Global helpers

```php
return api_success($user);
return api_error('Invalid request.', 400);
```

### Throwing typed API errors: `ApiException`

For business/domain errors, throw `ApiException` directly instead of round-tripping through `exception_map` config:

```php
use Imran\ApiToolkit\Exceptions\ApiException;

throw ApiException::notFound('Order not found.');
throw ApiException::forbidden();
throw ApiException::conflict('Email already registered.');
throw ApiException::validation(['email' => ['Already taken.']]);
throw ApiException::tooManyRequests('Slow down.', retryAfterSeconds: 30);
throw ApiException::badRequest('Malformed payload.');

// Or fully custom:
throw ApiException::make('Upstream provider timed out.', 502, 'UPSTREAM_TIMEOUT');
```

It self-renders into the standard JSON envelope even if `auto_register` is disabled (Laravel calls an exception's own `render()` method when it defines one — this runs *before* the package's `exception_map`/built-in mapping, so `ApiException` always wins regardless of that config). Because of this, `ApiException` always responds with JSON, even for a request that didn't ask for it — only throw it from API code paths.

By default, `report()` suppresses Laravel's normal exception logging for expected client errors (status < 500) — the same way Laravel itself already treats `ValidationException`/`ModelNotFoundException` — while genuine 5xx errors are still logged as usual. Extend `ApiException` and override `report()` if you want different behavior.

## Automatic exception handling

The moment the package is installed, any exception thrown while handling a request that expects JSON (or matches `config('api-toolkit.paths')`, `api/*` by default) is normalized automatically:

| Exception | Status | `error_code` |
|---|---|---|
| `ValidationException` | 422 | `VALIDATION_ERROR` |
| `AuthenticationException` | 401 | `UNAUTHENTICATED` |
| `AuthorizationException` / `AccessDeniedHttpException` | 403 | `FORBIDDEN` |
| `ModelNotFoundException` / `NotFoundHttpException` | 404 | `NOT_FOUND` |
| `MethodNotAllowedHttpException` | 405 | `METHOD_NOT_ALLOWED` |
| `ThrottleRequestsException` | 429 | `TOO_MANY_REQUESTS` |
| Any other `HttpExceptionInterface` | its own status | looked up from `error_codes` |
| Anything else (uncaught 500s) | 500 | `SERVER_ERROR` |

Requests that don't expect JSON and don't match `paths` are left completely untouched — your normal Blade error pages keep working.

### Adding your own exceptions

Map any exception (including your own domain exceptions) in `config/api-toolkit.php` without writing any handler code:

```php
'exception_map' => [
    \App\Exceptions\InsufficientBalanceException::class => [
        'status' => 422,
        'error_code' => 'INSUFFICIENT_BALANCE',
        'message' => 'The account balance is too low for this operation.',
    ],
],
```

If a mapped (or built-in) exception defines its own public `errors()` method — the same convention `ValidationException` and `Illuminate\Contracts\Validation\Validator` already use — its return value is used as the response's `errors` array automatically:

```php
class InsufficientBalanceException extends \Exception
{
    public function errors(): array
    {
        return ['balance' => ['The account balance is too low for this operation.']];
    }
}
```

HTTP headers the exception itself carries are also preserved onto the normalized response — e.g. `Retry-After` on a throttling exception or `Allow` on a method-not-allowed exception — matching Laravel's own default behavior.

### Disabling auto-registration

If you'd rather wire it up yourself (e.g. you already have a heavily customized `Handler.php`), set:

```php
// config/api-toolkit.php
'auto_register' => false,
```

and call the renderer manually from your own exception handler:

```php
use Imran\ApiToolkit\Exceptions\ExceptionRenderer;

$renderer = app(ExceptionRenderer::class);

if ($renderer->shouldHandle($request)) {
    return $renderer->render($e, $request);
}
```

## Configuration reference (`config/api-toolkit.php`)

| Key | Default | Description |
|---|---|---|
| `auto_register` | `true` | Automatically hook into Laravel's exception handler. |
| `paths` | `['api/*']` | Path patterns always treated as API requests. |
| `debug` | `env('API_TOOLKIT_DEBUG', env('APP_DEBUG'))` | Include exception details on 500s. |
| `show_trace` | `false` | Include the full stack trace when `debug` is on. |
| `errors_format` | `flat` | `flat` (array of `{field, message}`) or `nested` (Laravel default). |
| `paginate` | `true` | Auto-unwrap paginators passed to `success()` into `data` + `meta.pagination`. |
| `log_unhandled` | `false` | Log uncaught/unmapped exceptions through this package too. Off by default — Laravel already reports every exception through its own logging independently of this package, so leaving this on would double every log entry. Only turn it on if you've suppressed Laravel's own reporting for these exceptions. |
| `log_channel` | `null` | Log channel to use when `log_unhandled` is on (`null` = default). |
| `messages` | see config | Fallback messages, also translatable via `resources/lang/{locale}/api-toolkit.php`. |
| `error_codes` | see config | HTTP status → machine-readable error code. |
| `status_messages` | see config | HTTP status → which `messages` key to use as the default message. Statuses not listed fall back to the status's own standard HTTP reason phrase, so a response is never shipped with an empty message. |
| `exception_map` | see config | Exception class → status/error_code/message. |

### Message resolution order

For any error response, the message shown is resolved in this order: (1) an explicit `$message` argument, (2) an `exception_map` override, (3) the exception's own non-empty message, (4) the `status_messages` → `messages` lookup for that HTTP status, (5) the status's standard HTTP reason phrase (e.g. `Unprocessable Entity`) as the final fallback. A response's `message` is never empty.

## Testing

```bash
composer install
composer test
```

## License

MIT.
