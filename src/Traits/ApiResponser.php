<?php

namespace Imran\ApiToolkit\Traits;

use Imran\ApiToolkit\ApiResponse;

/**
 * Adds ApiResponse methods directly on the controller (e.g. $this->success($data))
 * as an alternative to calling the ApiResponse facade.
 */
trait ApiResponser
{
    protected function success($data = null, ?string $message = null, int $code = 200, array $meta = [])
    {
        return $this->apiResponse()->success($data, $message, $code, $meta);
    }

    protected function created($data = null, ?string $message = null, array $meta = [])
    {
        return $this->apiResponse()->created($data, $message, $meta);
    }

    protected function deleted(?string $message = null)
    {
        return $this->apiResponse()->deleted($message);
    }

    protected function noContent()
    {
        return $this->apiResponse()->noContent();
    }

    protected function loading(?string $message = null, array $data = [])
    {
        return $this->apiResponse()->loading($message, $data);
    }

    protected function error(?string $message = null, int $code = 400, $errors = null, ?string $errorCode = null)
    {
        return $this->apiResponse()->error($message, $code, $errors, $errorCode);
    }

    protected function unauthorized(?string $message = null)
    {
        return $this->apiResponse()->unauthorized($message);
    }

    protected function forbidden(?string $message = null)
    {
        return $this->apiResponse()->forbidden($message);
    }

    protected function notFound(?string $message = null)
    {
        return $this->apiResponse()->notFound($message);
    }

    protected function validationError($errors, ?string $message = null)
    {
        return $this->apiResponse()->validationError($errors, $message);
    }

    protected function apiResponse(): ApiResponse
    {
        return app('api-toolkit');
    }
}
