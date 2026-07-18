<?php

namespace Imran\ApiToolkit\Tests\Unit;

use Imran\ApiToolkit\Facades\ApiResponse;
use Imran\ApiToolkit\Tests\TestCase;
use Imran\ApiToolkit\Traits\ApiResponser;

class ApiResponserTraitTest extends TestCase
{
    public function test_trait_methods_match_facade_output(): void
    {
        $controller = new class {
            use ApiResponser;

            public function show()
            {
                return $this->success(['id' => 1], 'Fetched.');
            }

            public function fail()
            {
                return $this->validationError(['email' => ['The email field is required.']]);
            }
        };

        $traitResponse = $controller->show();
        $facadeResponse = ApiResponse::success(['id' => 1], 'Fetched.');

        $this->assertSame($traitResponse->getStatusCode(), $facadeResponse->getStatusCode());
        $this->assertSame($traitResponse->getData(true), $facadeResponse->getData(true));

        $traitError = $controller->fail();
        $facadeError = ApiResponse::validationError(['email' => ['The email field is required.']]);

        $this->assertSame($traitError->getStatusCode(), $facadeError->getStatusCode());
        $this->assertSame($traitError->getData(true), $facadeError->getData(true));
    }
}
