<?php

namespace Imran\ApiToolkit\Tests\Unit;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Imran\ApiToolkit\ApiResponse;
use Imran\ApiToolkit\Tests\TestCase;

class PaginationResponseTest extends TestCase
{
    protected ApiResponse $response;

    protected function setUp(): void
    {
        parent::setUp();

        $this->response = app('api-toolkit');
    }

    public function test_length_aware_paginator_is_unwrapped_into_data_and_meta(): void
    {
        $paginator = new LengthAwarePaginator(
            items: ['a', 'b'],
            total: 5,
            perPage: 2,
            currentPage: 1,
            options: ['path' => '/api/items']
        );

        $response = $this->response->success($paginator);
        $body = $response->getData(true);

        $this->assertSame(['a', 'b'], $body['data']);
        $this->assertArrayHasKey('pagination', $body['meta']);
        $this->assertSame(1, $body['meta']['pagination']['current_page']);
        $this->assertSame(5, $body['meta']['pagination']['total']);
        $this->assertSame(3, $body['meta']['pagination']['last_page']);
        $this->assertArrayHasKey('nav', $body['meta']['pagination']);
        $this->assertArrayHasKey('next', $body['meta']['pagination']['nav']);
    }

    public function test_simple_paginator_is_unwrapped_without_total(): void
    {
        $paginator = new Paginator(
            items: ['a', 'b'],
            perPage: 2,
            currentPage: 1,
            options: ['path' => '/api/items']
        );

        $response = $this->response->success($paginator);
        $body = $response->getData(true);

        $this->assertSame(['a', 'b'], $body['data']);
        $this->assertArrayNotHasKey('total', $body['meta']['pagination']);
    }

    public function test_cursor_paginator_is_unwrapped(): void
    {
        $paginator = new CursorPaginator(
            items: ['a', 'b'],
            perPage: 2,
            cursor: null,
            options: ['path' => '/api/items']
        );

        $response = $this->response->success($paginator);
        $body = $response->getData(true);

        $this->assertSame(['a', 'b'], $body['data']);
        $this->assertArrayHasKey('pagination', $body['meta']);
    }

    public function test_resource_collection_wrapping_a_paginator_is_unwrapped(): void
    {
        // Mirrors real usage of UserResource::collection($paginator): a raw
        // ResourceCollection can only serialize JsonResource-shaped items,
        // same as in a real Laravel app.
        $paginator = new LengthAwarePaginator(
            items: [new JsonResource(['id' => 1]), new JsonResource(['id' => 2])],
            total: 2,
            perPage: 2,
            currentPage: 1,
            options: ['path' => '/api/items']
        );

        $collection = new ResourceCollection($paginator);

        $response = $this->response->success($collection);
        $body = $response->getData(true);

        $this->assertCount(2, $body['data']);
        $this->assertSame(2, $body['meta']['pagination']['total']);
    }

    public function test_plain_array_is_left_untouched(): void
    {
        $response = $this->response->success(['id' => 1]);
        $body = $response->getData(true);

        $this->assertSame(['id' => 1], $body['data']);
        $this->assertArrayNotHasKey('meta', $body);
    }

    public function test_pagination_can_be_disabled_via_config(): void
    {
        config()->set('api-toolkit.paginate', false);

        $paginator = new LengthAwarePaginator(
            items: ['a', 'b'],
            total: 5,
            perPage: 2,
            currentPage: 1,
            options: ['path' => '/api/items']
        );

        $response = $this->response->success($paginator);
        $body = $response->getData(true);

        // Passed through untouched: serialized as the paginator's own array shape.
        $this->assertArrayNotHasKey('pagination', $body['meta'] ?? []);
        $this->assertArrayHasKey('current_page', $body['data']);
    }
}
