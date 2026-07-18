<?php

if (! function_exists('api_success')) {
    function api_success($data = null, ?string $message = null, int $code = 200, array $meta = [])
    {
        return app('api-toolkit')->success($data, $message, $code, $meta);
    }
}

if (! function_exists('api_error')) {
    function api_error(?string $message = null, int $code = 400, $errors = null, ?string $errorCode = null)
    {
        return app('api-toolkit')->error($message, $code, $errors, $errorCode);
    }
}
