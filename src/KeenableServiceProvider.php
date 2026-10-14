<?php

namespace SubscopeAi\Keenable;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

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
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/keenable.php' => config_path('keenable.php'),
        ], 'keenable-config');
    }
}
