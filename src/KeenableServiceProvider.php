<?php

namespace SubscopeAi\Keenable;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use SubscopeAi\Keenable\Tools\KeenableFetchTool;
use SubscopeAi\Keenable\Tools\KeenableSearchTool;

class KeenableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/keenable.php', 'keenable');

        $this->app->singleton(KeenableClient::class, function ($app) {
            return new KeenableClient(
                $app->make(HttpFactory::class),
                $app['config']->get('keenable', []),
            );
        });

        $this->app->alias(KeenableClient::class, 'keenable');

        $wrapToolExceptions = fn ($app): bool => (bool) $app['config']->get('keenable.wrap_tool_exceptions', true);

        $this->app->bind(KeenableSearchTool::class, fn ($app) => new KeenableSearchTool(
            $app->make(KeenableClient::class),
            $wrapToolExceptions($app),
        ));

        $this->app->bind(KeenableFetchTool::class, fn ($app) => new KeenableFetchTool(
            $app->make(KeenableClient::class),
            $wrapToolExceptions($app),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/keenable.php' => config_path('keenable.php'),
        ], 'keenable-config');
    }
}
