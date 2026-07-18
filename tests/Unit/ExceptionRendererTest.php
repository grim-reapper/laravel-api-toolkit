<?php

namespace Imran\ApiToolkit\Tests\Unit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Imran\ApiToolkit\Exceptions\ExceptionRenderer;
use Imran\ApiToolkit\Tests\TestCase;

class ExceptionRendererTest extends TestCase
{
    protected ExceptionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = app(ExceptionRenderer::class);
    }

    public function test_should_handle_when_request_expects_json(): void
    {
        $request = Request::create('/anything', 'GET');
        $request->headers->set('Accept', 'application/json');

        $this->assertTrue($this->renderer->shouldHandle($request));
    }

    public function test_should_handle_when_path_matches_configured_api_paths(): void
    {
        $request = Request::create('/api/users', 'GET');

        $this->assertTrue($this->renderer->shouldHandle($request));
    }

    public function test_should_not_handle_plain_web_request(): void
    {
        $request = Request::create('/dashboard', 'GET');

        $this->assertFalse($this->renderer->shouldHandle($request));
    }

    public function test_renders_validation_exception_as_422_with_flat_errors(): void
    {
        config()->set('api-toolkit.errors_format', 'flat');

        $validator = validator(['email' => ''], ['email' => 'required']);

        try {
            $validator->validate();
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $response = $this->renderer->render($e, Request::create('/api/x', 'POST'));
        }

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', $data['error_code']);
        $this->assertSame('email', $data['errors'][0]['field']);
    }

    public function test_renders_model_not_found_as_404(): void
    {
        $e = new ModelNotFoundException();
        $e->setModel(\stdClass::class);

        $response = $this->renderer->render($e, Request::create('/api/x', 'GET'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('NOT_FOUND', $response->getData(true)['error_code']);
    }

    public function test_renders_authentication_exception_as_401(): void
    {
        $response = $this->renderer->render(new AuthenticationException(), Request::create('/api/x', 'GET'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('UNAUTHENTICATED', $response->getData(true)['error_code']);
    }

    public function test_generic_exception_hides_message_in_production(): void
    {
        config()->set('api-toolkit.debug', false);

        $response = $this->renderer->render(new \RuntimeException('db password leaked'), Request::create('/api/x', 'GET'));

        $data = $response->getData(true);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('SERVER_ERROR', $data['error_code']);
        $this->assertNotSame('db password leaked', $data['message']);
        $this->assertArrayNotHasKey('debug', $data);
    }

    public function test_unmapped_http_exception_falls_back_to_reason_phrase_instead_of_empty_message(): void
    {
        $e = new \Symfony\Component\HttpKernel\Exception\HttpException(418);

        $response = $this->renderer->render($e, Request::create('/api/x', 'GET'));

        $this->assertSame(418, $response->getStatusCode());
        $message = $response->getData(true)['message'];
        $this->assertNotSame('', $message);
        $this->assertSame(\Symfony\Component\HttpFoundation\Response::$statusTexts[418], $message);
    }

    public function test_generic_exception_shows_debug_info_in_local_debug_mode(): void
    {
        config()->set('api-toolkit.debug', true);

        $response = $this->renderer->render(new \RuntimeException('boom'), Request::create('/api/x', 'GET'));

        $data = $response->getData(true);
        $this->assertSame('boom', $data['message']);
        $this->assertArrayHasKey('debug', $data);
        $this->assertSame(\RuntimeException::class, $data['debug']['exception']);
    }

    public function test_throttle_exception_preserves_retry_after_header(): void
    {
        $e = new \Illuminate\Http\Exceptions\ThrottleRequestsException(
            headers: ['Retry-After' => '42']
        );

        $response = $this->renderer->render($e, Request::create('/api/x', 'GET'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('42', $response->headers->get('Retry-After'));
    }

    public function test_method_not_allowed_exception_preserves_allow_header(): void
    {
        $e = new \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException(['GET', 'POST']);

        $response = $this->renderer->render($e, Request::create('/api/x', 'PUT'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, POST', $response->headers->get('Allow'));
    }

    public function test_mapped_custom_exception_carries_its_own_structured_errors(): void
    {
        $exceptionClass = new class('Balance too low.') extends \Exception {
            public function errors(): array
            {
                return ['balance' => ['Insufficient funds for this operation.']];
            }
        };

        // Anonymous class names embed a file path (which may itself contain
        // dots), so the map is rebuilt wholesale rather than dot-set with
        // the class name as a nested key.
        $map = config('api-toolkit.exception_map');
        $map[get_class($exceptionClass)] = [
            'status' => 422,
            'error_code' => 'INSUFFICIENT_BALANCE',
        ];
        config(['api-toolkit.exception_map' => $map]);

        $response = $this->renderer->render($exceptionClass, Request::create('/api/x', 'POST'));

        $data = $response->getData(true);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('INSUFFICIENT_BALANCE', $data['error_code']);
        $this->assertSame('balance', $data['errors'][0]['field']);
    }

    public function test_api_exception_is_rendered_via_its_own_status_and_error_code(): void
    {
        $e = \Imran\ApiToolkit\Exceptions\ApiException::conflict('Already exists.');

        $response = $this->renderer->render($e, Request::create('/api/x', 'POST'));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('CONFLICT', $response->getData(true)['error_code']);
    }

    public function test_a_private_errors_method_unrelated_to_validation_is_ignored_not_crashed_on(): void
    {
        $exceptionClass = new class('Oops.') extends \Exception {
            // Same method name, unrelated purpose, and not public -- must
            // not be duck-typed as a validation-errors provider.
            private function errors(): array
            {
                return ['should' => ['never surface']];
            }
        };

        $map = config('api-toolkit.exception_map');
        $map[get_class($exceptionClass)] = ['status' => 400, 'error_code' => 'BAD_REQUEST'];
        config(['api-toolkit.exception_map' => $map]);

        $response = $this->renderer->render($exceptionClass, Request::create('/api/x', 'POST'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayNotHasKey('errors', $response->getData(true));
    }

    public function test_an_errors_method_requiring_arguments_does_not_crash_the_renderer(): void
    {
        $exceptionClass = new class('Oops.') extends \Exception {
            // A same-named method with an incompatible signature -- calling
            // it with no arguments must not blow up the renderer itself.
            public function errors(string $required): array
            {
                return [$required];
            }
        };

        $map = config('api-toolkit.exception_map');
        $map[get_class($exceptionClass)] = ['status' => 400, 'error_code' => 'BAD_REQUEST'];
        config(['api-toolkit.exception_map' => $map]);

        $response = $this->renderer->render($exceptionClass, Request::create('/api/x', 'POST'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayNotHasKey('errors', $response->getData(true));
    }
}
