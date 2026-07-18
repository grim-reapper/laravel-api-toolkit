<?php

namespace Imran\ApiToolkit\Tests\Feature;

use Imran\ApiToolkit\Exceptions\ApiException;
use Imran\ApiToolkit\Tests\TestCase;

class AutoRegisterDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Must be set before the service provider boots, so the
        // renderable() hook registration is genuinely skipped -- not just
        // toggled after the fact.
        $app['config']->set('api-toolkit.auto_register', false);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/api/boom', function () {
            throw new \RuntimeException('kaboom');
        });

        $router->get('/api/conflict', function () {
            throw ApiException::conflict('Already exists.');
        });
    }

    public function test_uncaught_exception_is_not_normalized_when_auto_register_is_disabled(): void
    {
        $response = $this->getJson('/api/boom');

        // Falls through to Laravel's own default rendering instead of the
        // package's JSON envelope: no success/error_code keys at all.
        $body = $response->json();

        $this->assertArrayNotHasKey('success', $body ?? []);
        $this->assertArrayNotHasKey('error_code', $body ?? []);
    }

    public function test_api_exception_still_self_renders_even_when_auto_register_is_disabled(): void
    {
        // ApiException self-renders via its own render() method (Laravel
        // calls it directly, independent of the renderable() hook), so this
        // must keep working regardless of auto_register.
        $response = $this->getJson('/api/conflict');

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error_code', 'CONFLICT');
    }
}
