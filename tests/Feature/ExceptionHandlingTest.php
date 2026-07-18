<?php

namespace Imran\ApiToolkit\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Imran\ApiToolkit\Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/api/boom', function () {
            throw new \RuntimeException('kaboom');
        });

        $router->get('/api/missing-model', function () {
            throw (new ModelNotFoundException())->setModel(\stdClass::class);
        });

        $router->get('/api/unauthenticated', function () {
            throw new AuthenticationException();
        });

        $router->get('/web/boom', function () {
            throw new \RuntimeException('kaboom-web');
        });
    }

    public function test_unhandled_exception_on_api_route_is_normalized(): void
    {
        $response = $this->getJson('/api/boom');

        $response->assertStatus(500);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error_code', 'SERVER_ERROR');
    }

    public function test_model_not_found_on_api_route_is_normalized_to_404(): void
    {
        $response = $this->getJson('/api/missing-model');

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 'NOT_FOUND');
    }

    public function test_authentication_exception_on_api_route_is_normalized_to_401(): void
    {
        $response = $this->getJson('/api/unauthenticated');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_plain_web_request_is_left_to_default_rendering(): void
    {
        $response = $this->get('/web/boom');

        $response->assertStatus(500);
        $this->assertStringNotContainsString(
            'application/json',
            $response->headers->get('Content-Type') ?? ''
        );
    }
}
