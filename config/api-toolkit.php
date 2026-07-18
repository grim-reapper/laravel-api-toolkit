<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-register exception handling
    |--------------------------------------------------------------------------
    |
    | When enabled, the package hooks itself into Laravel's exception handler
    | automatically (via Handler::renderable()) so no changes to your own
    | Handler/bootstrap/app.php are required. Set to false if you'd rather
    | call Imran\ApiToolkit\Exceptions\ExceptionRenderer manually.
    |
    */
    'auto_register' => true,

    /*
    |--------------------------------------------------------------------------
    | API paths
    |--------------------------------------------------------------------------
    |
    | Requests matching these path patterns are always treated as API requests
    | (normalized JSON errors), even when they don't send an "Accept: json"
    | header. Requests that neither match here nor expect JSON are left to
    | Laravel's default (HTML) error rendering.
    |
    */
    'paths' => ['api/*'],

    /*
    |--------------------------------------------------------------------------
    | Debug information
    |--------------------------------------------------------------------------
    |
    | Controls whether error responses include exception class/file/line (and
    | optionally a full stack trace) for unhandled/500 errors. Defaults to
    | APP_DEBUG so production stays clean automatically.
    |
    */
    'debug' => env('API_TOOLKIT_DEBUG', env('APP_DEBUG', false)),

    'show_trace' => env('API_TOOLKIT_SHOW_TRACE', false),

    /*
    |--------------------------------------------------------------------------
    | Validation error format
    |--------------------------------------------------------------------------
    |
    | "flat"   => [{"field": "email", "message": "The email field is required."}]
    | "nested" => {"email": ["The email field is required."]}  (Laravel default)
    |
    */
    'errors_format' => 'flat',

    /*
    |--------------------------------------------------------------------------
    | Auto pagination
    |--------------------------------------------------------------------------
    |
    | When ApiResponse::success() is given a paginator (LengthAwarePaginator,
    | simple Paginator, or CursorPaginator -- raw or wrapped in a Resource
    | collection), it's automatically unwrapped into `data` + `meta.pagination`
    | instead of being serialized as-is. Set to false to disable and pass
    | paginators straight through untouched.
    |
    */
    'paginate' => true,

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Laravel already logs every exception through its own reporting (independent
    | of this package's rendering hook), so this is off by default to avoid
    | duplicate log entries. Only enable it if you've suppressed Laravel's own
    | reporting for these exceptions and still want them captured here. Null
    | uses the application's default log channel.
    |
    */
    'log_unhandled' => false,

    'log_channel' => env('API_TOOLKIT_LOG_CHANNEL', null),

    /*
    |--------------------------------------------------------------------------
    | Default messages
    |--------------------------------------------------------------------------
    |
    | Fallback messages used when an exception carries no useful message of
    | its own. Translatable copies live in resources/lang/{locale}/api-toolkit.php.
    |
    */
    'messages' => [
        'success' => 'Request was successful.',
        'bad_request' => 'The request could not be processed.',
        'server_error' => 'Something went wrong. Please try again later.',
        'not_found' => 'The requested resource was not found.',
        'unauthenticated' => 'Authentication is required to access this resource.',
        'forbidden' => 'You are not authorized to perform this action.',
        'validation' => 'The given data was invalid.',
        'throttle' => 'Too many requests. Please slow down.',
        'method_not_allowed' => 'The HTTP method used is not supported for this route.',
        'processing' => 'Your request is being processed.',
        'deleted' => 'Resource deleted successfully.',
        'created' => 'Resource created successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error codes
    |--------------------------------------------------------------------------
    |
    | Machine-readable codes exposed alongside the HTTP status, keyed by
    | status code. Used whenever a more specific error_code isn't supplied.
    |
    */
    'error_codes' => [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'SERVER_ERROR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status default messages
    |--------------------------------------------------------------------------
    |
    | Which `messages` key to fall back to for a given HTTP status when no
    | explicit message was supplied and the exception itself carried none.
    | Statuses not listed here fall back further still, to the HTTP status's
    | own standard reason phrase (e.g. "Unprocessable Entity"), so a response
    | never ships with an empty message.
    |
    */
    'status_messages' => [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        422 => 'validation',
        429 => 'throttle',
        500 => 'server_error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception map
    |--------------------------------------------------------------------------
    |
    | Map any exception class (including your own domain exceptions) to a
    | fixed HTTP status / error code / message, without writing a single
    | line of handler code. Checked before the package's built-in mapping.
    |
    | Example:
    | \App\Exceptions\InsufficientBalanceException::class => [
    |     'status' => 422,
    |     'error_code' => 'INSUFFICIENT_BALANCE',
    |     'message' => 'The account balance is too low for this operation.',
    | ],
    |
    */
    'exception_map' => [
        ValidationException::class => [
            'status' => 422,
            'error_code' => 'VALIDATION_ERROR',
            'message' => null,
        ],
        AuthenticationException::class => [
            'status' => 401,
            'error_code' => 'UNAUTHENTICATED',
            'message' => null,
        ],
        AuthorizationException::class => [
            'status' => 403,
            'error_code' => 'FORBIDDEN',
            'message' => null,
        ],
        ModelNotFoundException::class => [
            'status' => 404,
            'error_code' => 'NOT_FOUND',
            'message' => null,
        ],
        NotFoundHttpException::class => [
            'status' => 404,
            'error_code' => 'NOT_FOUND',
            'message' => null,
        ],
        MethodNotAllowedHttpException::class => [
            'status' => 405,
            'error_code' => 'METHOD_NOT_ALLOWED',
            'message' => null,
        ],
        ThrottleRequestsException::class => [
            'status' => 429,
            'error_code' => 'TOO_MANY_REQUESTS',
            'message' => null,
        ],
    ],

];
