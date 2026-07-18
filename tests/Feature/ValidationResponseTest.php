<?php

namespace Imran\ApiToolkit\Tests\Feature;

use Illuminate\Http\Request;
use Imran\ApiToolkit\Tests\TestCase;

class ValidationResponseTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('/api/register', function (Request $request) {
            $request->validate([
                'email' => 'required|email',
                'name' => 'required|string',
            ]);

            return response()->json(['success' => true, 'message' => 'ok', 'data' => null]);
        });
    }

    public function test_validation_failure_returns_flat_errors_by_default(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $errors = $response->json('errors');

        $this->assertIsList($errors);
        $this->assertContains(['field' => 'email', 'message' => 'The email field is required.'], $errors);
        $this->assertContains(['field' => 'name', 'message' => 'The name field is required.'], $errors);
    }

    public function test_validation_failure_returns_nested_errors_when_configured(): void
    {
        config()->set('api-toolkit.errors_format', 'nested');

        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422);

        $errors = $response->json('errors');

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertIsArray($errors['email']);
    }

    public function test_successful_validation_passes_through_untouched(): void
    {
        $response = $this->postJson('/api/register', [
            'email' => 'user@example.com',
            'name' => 'Jane Doe',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
