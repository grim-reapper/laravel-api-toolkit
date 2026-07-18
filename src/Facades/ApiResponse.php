<?php

namespace Imran\ApiToolkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\JsonResponse success(mixed $data = null, ?string $message = null, int $code = 200, array $meta = [])
 * @method static \Illuminate\Http\JsonResponse created(mixed $data = null, ?string $message = null, array $meta = [])
 * @method static \Illuminate\Http\JsonResponse deleted(?string $message = null)
 * @method static \Illuminate\Http\Response noContent()
 * @method static \Illuminate\Http\JsonResponse loading(?string $message = null, array $data = [])
 * @method static \Illuminate\Http\JsonResponse error(?string $message = null, int $code = 400, mixed $errors = null, ?string $errorCode = null)
 * @method static \Illuminate\Http\JsonResponse unauthorized(?string $message = null)
 * @method static \Illuminate\Http\JsonResponse forbidden(?string $message = null)
 * @method static \Illuminate\Http\JsonResponse notFound(?string $message = null)
 * @method static \Illuminate\Http\JsonResponse validationError(mixed $errors, ?string $message = null)
 *
 * @see \Imran\ApiToolkit\ApiResponse
 */
class ApiResponse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'api-toolkit';
    }
}
