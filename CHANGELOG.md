# Changelog

All notable changes to `laravel-api-toolkit` will be documented in this file.

## [1.0.0] - 2026-07-18

### Added
- `ApiResponse` facade and `ApiResponser` controller trait with `success`, `created`, `deleted`, `noContent`, `loading`, `error`, `unauthorized`, `forbidden`, `notFound`, and `validationError` methods.
- Global `api_success()` / `api_error()` helper functions.
- Automatic, zero-config exception handler hook (`ExceptionRenderer`) normalizing `ValidationException`, `AuthenticationException`, `AuthorizationException`, `ModelNotFoundException`, 404/405/429 HTTP exceptions, and uncaught 500s into a single JSON envelope.
- Configurable, frontend-friendly flat validation error format (`{field, message}`), with a `nested` (Laravel-native) option.
- Environment-aware debug information (exception class/file/line, optional stack trace) shown only when enabled.
- User-extensible `exception_map` config for mapping custom/domain exceptions without touching handler code.
- Publishable config and language file.

### Fixed (pre-release hardening pass)
- Removed a double-logging bug: unhandled exceptions were logged both by this package and by Laravel's own automatic exception reporting (which runs independently of the rendering hook). `log_unhandled` now defaults to `false`.
- Fixed error responses shipping an empty `message` for exceptions with no message and an HTTP status outside the built-in map (e.g. `abort(418)`). Default-message resolution is now centralized in `ApiResponse` with a final fallback to the status's standard HTTP reason phrase.
- Hardened validation-error normalization to detect `Illuminate\Contracts\Validation\Validator` and `ValidationException` instances directly, and to avoid mangling arbitrary objects' private/protected properties into the response if a wrong type is passed.

### Added (v1.1 — extended flexibility pass)
- `ApiException`: a throwable, typed API error with static factories (`badRequest`, `unauthorized`, `forbidden`, `notFound`, `conflict`, `validation`, `tooManyRequests`, `make`), structured `errors`, custom headers, self-rendering `render()`, and a `report()` override that suppresses default logging for expected (< 500) client errors while still logging genuine 5xx failures.
- Automatic pagination support: `ApiResponse::success()` now detects `LengthAwarePaginator`, simple `Paginator`, and `CursorPaginator` (raw, or wrapped in a Resource collection) and unwraps them into `data` + `meta.pagination`. Toggle via the new `paginate` config key.
- Any exception (mapped via `exception_map`, or built-in) that defines a public `errors()` method now has that used as the response's structured `errors` array, not just `ValidationException`.
- HTTP headers carried by an exception (`Retry-After`, `Allow`, etc.) are now preserved onto the normalized JSON response, matching Laravel's default behavior.

### Fixed (v1.1 follow-up hardening)
- The new `errors()` duck-typing used `is_callable()` instead of `method_exists()`, and wraps the call in a try/catch: `method_exists()` would have matched a `private`/`protected` `errors()` method defined for an unrelated purpose (crashing with a visibility error when called from outside the class), and a same-named method with required parameters would have thrown an uncaught `ArgumentCountError` while trying to render an unrelated exception.

### Fixed / Changed (code review pass)
- `composer.json` now declares `illuminate/validation`, `illuminate/auth`, and `illuminate/database` explicitly — the package imports classes from all three namespaces directly, and the dependency graph should reflect that rather than relying on them arriving transitively.
- The optional `Log::channel()` call in `renderServerError()` (only reached when `log_unhandled` is enabled) is now wrapped in a try/catch, so a misconfigured `log_channel` can't itself crash the renderer while it's trying to handle an unrelated exception.
- `ApiResponse::error()` now omits the `errors` key entirely when the formatted errors turn out empty, instead of shipping `"errors": []` — consistent with how `meta` is already only added when non-empty.
- Added an end-to-end feature test proving `auto_register = false` (set before boot) genuinely prevents the `renderable()` hook from being registered, and that `ApiException` still self-renders regardless of that setting.
- README now documents the `ApiResponser` trait's common method names can collide with an existing base controller's own methods, with the trait-aliasing workaround, and clarifies that `debug` info is only ever attached to the uncaught/unmapped (500) fallback, never to known/mapped exceptions.
