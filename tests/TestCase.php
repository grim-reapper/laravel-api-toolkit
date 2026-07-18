<?php

namespace Imran\ApiToolkit\Tests;

use Imran\ApiToolkit\ApiToolkitServiceProvider;
use Imran\ApiToolkit\Facades\ApiResponse;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiToolkitServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ApiResponse' => ApiResponse::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('api-toolkit.debug', false);
        $app['config']->set('api-toolkit.errors_format', 'flat');
    }
}
