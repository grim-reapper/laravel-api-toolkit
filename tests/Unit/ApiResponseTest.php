<?php

namespace Imran\ApiToolkit\Tests\Unit;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Imran\ApiToolkit\ApiResponse;
use Imran\ApiToolkit\Tests\TestCase;

class ApiResponseTest extends TestCase
{
    protected ApiResponse $response;

    protected function setUp(): void
    {
        parent::setUp();

        $this->response = app('api-toolkit');
    }

    protected function wrap(JsonResponse $response): TestResponse
    {
        return TestResponse::fromBaseResponse($response);
    }

    public function test_success_response_shape(): void
    {
        $response = $this->wrap($this->response->success(['id' => 1], 'Fetched.', 200, ['total' => 1]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Fetched.',
            'data' => ['id' => 1],
            'meta' => ['total' => 1],
        ]);
    }

    public function test_success_response_omits_meta_when_empty(): void
    {
        $response = $this->response->success(['id' => 1]);

        $this->assertArrayNotHasKey('meta', $response->getData(true));
    }

    public function test_created_response_uses_201(): void
    {
        $response = $this->response->created(['id' => 1]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Resource created successfully.', $response->getData(true)['message']);
    }

    public function test_no_content_response(): void
    {
        $response = $this->response->noContent();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
    }

    public function test_loading_response_shape(): void
    {
        $response = $this->wrap($this->response->loading('Processing your export', ['job_id' => 'abc']));

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'status' => 'processing',
            'message' => 'Processing your export',
            'data' => ['job_id' => 'abc'],
        ]);
    }

    public function test_error_response_shape(): void
    {
        $response = $this->wrap($this->response->error('Something failed', 400));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Something failed',
            'error_code' => 'BAD_REQUEST',
        ]);
        $this->assertArrayNotHasKey('errors', $response->json());
    }

    public function test_unauthorized_forbidden_not_found_shortcuts(): void
    {
        $this->wrap($this->response->unauthorized())->assertStatus(401)->assertJsonPath('error_code', 'UNAUTHENTICATED');
        $this->wrap($this->response->forbidden())->assertStatus(403)->assertJsonPath('error_code', 'FORBIDDEN');
        $this->wrap($this->response->notFound())->assertStatus(404)->assertJsonPath('error_code', 'NOT_FOUND');
    }

    public function test_validation_error_flat_format(): void
    {
        config()->set('api-toolkit.errors_format', 'flat');

        $response = $this->wrap($this->response->validationError([
            'email' => ['The email field is required.'],
            'name' => ['The name field is required.', 'The name must be a string.'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $errors = $response->json('errors');

        $this->assertCount(3, $errors);
        $this->assertContains(['field' => 'email', 'message' => 'The email field is required.'], $errors);
        $this->assertContains(['field' => 'name', 'message' => 'The name must be a string.'], $errors);
    }

    public function test_validation_error_accepts_a_raw_validator_instance(): void
    {
        $validator = validator(['email' => ''], ['email' => 'required']);

        $response = $this->response->validationError($validator);

        $this->assertSame(422, $response->getStatusCode());
        $errors = $response->getData(true)['errors'];
        $this->assertSame('email', $errors[0]['field']);
    }

    public function test_validation_error_accepts_a_validation_exception_instance(): void
    {
        $validator = validator(['email' => ''], ['email' => 'required']);

        try {
            $validator->validate();
            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response = $this->response->validationError($e);
        }

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('email', $response->getData(true)['errors'][0]['field']);
    }

    public function test_validation_error_with_an_unsupported_object_does_not_leak_internal_state(): void
    {
        $response = $this->response->validationError(new class {
            private $secretToken = 'super-secret';
        });

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getContent();
        $this->assertStringNotContainsString('super-secret', $body);
    }

    public function test_error_with_unmapped_status_never_ships_an_empty_message(): void
    {
        $response = $this->response->error(null, 418);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertNotSame('', $response->getData(true)['message']);
    }

    public function test_validation_error_nested_format(): void
    {
        config()->set('api-toolkit.errors_format', 'nested');

        $response = $this->response->validationError([
            'email' => ['The email field is required.'],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            ['email' => ['The email field is required.']],
            $response->getData(true)['errors']
        );
    }
}
