# Changelog

All notable changes to `laravel-api-toolkit` will be documented in this file.

## [1.1.0] - 2026-07-19

Initial public release.

### Added
- `ApiResponse` facade and `ApiResponser` controller trait with `success`, `created`, `deleted`, `noContent`, `loading`, `error`, `unauthorized`, `forbidden`, `notFound`, and `validationError` methods.
- Global `api_success()` / `api_error()` helper functions.
- Automatic, zero-config exception handler hook (`ExceptionRenderer`) normalizing `ValidationException`, `AuthenticationException`, `AuthorizationException`, `ModelNotFoundException`, 404/405/429 HTTP exceptions, and uncaught 500s into a single JSON envelope — no changes to your `Handler.php`/`bootstrap/app.php` required.
- Configurable, frontend-friendly flat validation error format (`{field, message}`), with a `nested` (Laravel-native) option.
- Environment-aware debug information (exception class/file/line, optional stack trace) on uncaught 500s only, shown only when enabled — never included for known/mapped exceptions.
- User-extensible `exception_map` config for mapping custom/domain exceptions to a status/error_code/message without touching handler code.
- `ApiException`: a throwable, typed API error with static factories (`badRequest`, `unauthorized`, `forbidden`, `notFound`, `conflict`, `validation`, `tooManyRequests`, `make`), structured `errors`, custom headers, self-rendering `render()`, and a `report()` override that suppresses default logging for expected (< 500) client errors while still logging genuine 5xx failures.
- Automatic pagination support: `ApiResponse::success()` detects `LengthAwarePaginator`, simple `Paginator`, and `CursorPaginator` (raw, or wrapped in a Resource collection) and unwraps them into `data` + `meta.pagination`. Toggle via the `paginate` config key.
- Any exception (mapped via `exception_map`, or built-in) that defines a public `errors()` method has that used as the response's structured `errors` array, not just `ValidationException`.
- HTTP headers carried by an exception (`Retry-After`, `Allow`, etc.) are preserved onto the normalized JSON response, matching Laravel's default behavior.
- Publishable config and language file.

### Hardening
- Unhandled exceptions are never double-logged: Laravel's own automatic exception reporting already runs independently of this package's rendering hook, so `log_unhandled` defaults to `false`; when enabled, the logging call is wrapped in a try/catch so a misconfigured `log_channel` can't itself crash the renderer.
- Error responses never ship an empty `message`, even for an HTTP status outside the built-in map (e.g. `abort(418)`) — default-message resolution falls back through config, then to the status's own standard HTTP reason phrase.
- Validation-error normalization safely detects `Illuminate\Contracts\Validation\Validator` and `ValidationException` instances directly, and avoids mangling arbitrary objects' private/protected properties into the response if a wrong type is passed.
- The `errors()` duck-typing convention uses `is_callable()` (not `method_exists()`) wrapped in a try/catch, so a `private`/`protected` `errors()` method defined for an unrelated purpose, or one with an incompatible signature, is safely skipped rather than crashing the renderer.
- `ApiResponse::error()` omits the `errors` key entirely when formatted errors turn out empty, instead of shipping `"errors": []` — consistent with how `meta` is only added when non-empty.
- `composer.json` explicitly declares `illuminate/validation`, `illuminate/auth`, and `illuminate/database`, matching the namespaces actually imported.

### Testing
- 53 tests covering the response builder, exception renderer (including header preservation, `errors()` duck-typing edge cases, and pagination across all three paginator types), the controller trait, and full HTTP round-trips — including an end-to-end test proving `auto_register = false` genuinely disables the automatic hook.
