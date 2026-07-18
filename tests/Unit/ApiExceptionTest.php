<?php

namespace Imran\ApiToolkit\Tests\Unit;

use Illuminate\Http\Request;
use Imran\ApiToolkit\Exceptions\ApiException;
use Imran\ApiToolkit\Exceptions\ExceptionRenderer;
use Imran\ApiToolkit\Tests\TestCase;

class ApiExceptionTest extends TestCase
{
    protected ExceptionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = app(ExceptionRenderer::class);
    }

    public function test_conflict_factory_self_renders_with_correct_status_and_error_code(): void
    {
        $e = ApiException::conflict('Email already registered.');

        $response = $this->renderer->render($e, Request::create('/api/x', 'POST'));

        $this->assertSame(409, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('CONFLICT', $data['error_code']);
        $this->assertSame('Email already registered.', $data['message']);
    }

    public function test_validation_factory_carries_structured_errors(): void
    {
        $e = ApiException::validation(['email' => ['Already taken.']]);

        $response = $this->renderer->render($e, Request::create('/api/x', 'POST'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('email', $response->getData(true)['errors'][0]['field']);
    }

    public function test_too_many_requests_factory_sets_retry_after_header(): void
    {
        $e = ApiException::tooManyRequests('Slow down.', 30);

        $response = $this->renderer->render($e, Request::create('/api/x', 'GET'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function test_render_works_directly_without_the_renderer_when_auto_register_is_disabled(): void
    {
        config()->set('api-toolkit.auto_register', false);

        $e = ApiException::notFound('Order not found.');
        $response = $e->render(Request::create('/api/x', 'GET'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Order not found.', $response->getData(true)['message']);
    }

    public function test_report_returns_true_to_suppress_logging_for_expected_client_errors(): void
    {
        $this->assertTrue(ApiException::notFound()->report());
        $this->assertTrue(ApiException::validation(['x' => ['y']])->report());
    }

    public function test_report_returns_false_for_server_errors_so_laravel_still_logs_them(): void
    {
        $e = ApiException::make('DB down', 503, 'SERVICE_UNAVAILABLE');

        $this->assertFalse($e->report());
    }

    public function test_context_exposes_error_code_and_status_for_log_correlation(): void
    {
        $e = ApiException::forbidden('Nope.');

        $this->assertSame(['error_code' => 'FORBIDDEN', 'status_code' => 403], $e->context());
    }
}
