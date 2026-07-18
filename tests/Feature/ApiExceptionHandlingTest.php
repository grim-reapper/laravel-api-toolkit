<?php

namespace Imran\ApiToolkit\Tests\Feature;

use Imran\ApiToolkit\Exceptions\ApiException;
use Imran\ApiToolkit\Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('/api/orders', function () {
            throw ApiException::conflict('Order already placed.');
        });

        $router->get('/api/throttled', function () {
            throw ApiException::tooManyRequests('Slow down.', 15);
        });
    }

    public function test_api_exception_thrown_from_a_route_is_normalized_end_to_end(): void
    {
        $response = $this->postJson('/api/orders');

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error_code', 'CONFLICT');
        $response->assertJsonPath('message', 'Order already placed.');
    }

    public function test_api_exception_headers_survive_the_full_http_pipeline(): void
    {
        $response = $this->getJson('/api/throttled');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After', '15');
    }
}
