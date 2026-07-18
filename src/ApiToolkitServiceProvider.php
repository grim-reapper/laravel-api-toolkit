<?php

namespace Imran\ApiToolkit;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Imran\ApiToolkit\Exceptions\ExceptionRenderer;
use Throwable;

class ApiToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-toolkit.php', 'api-toolkit');

        $this->app->singleton('api-toolkit', fn () => new ApiResponse());
        $this->app->singleton(ApiResponse::class, fn ($app) => $app->make('api-toolkit'));
        $this->app->singleton(ExceptionRenderer::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'api-toolkit');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-toolkit.php' => config_path('api-toolkit.php'),
            ], 'api-toolkit-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/api-toolkit'),
            ], 'api-toolkit-lang');
        }

        if (config('api-toolkit.auto_register', true)) {
            $this->registerExceptionHandler();
        }
    }

    protected function registerExceptionHandler(): void
    {
        $this->app->afterResolving(ExceptionHandler::class, function ($handler) {
            if (! method_exists($handler, 'renderable')) {
                return;
            }

            $handler->renderable(function (Throwable $e, $request) {
                $renderer = $this->app->make(ExceptionRenderer::class);

                if (! $renderer->shouldHandle($request)) {
                    return null;
                }

                return $renderer->render($e, $request);
            });
        });
    }
}
