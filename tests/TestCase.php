<?php

namespace SubscopeAi\Keenable\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SubscopeAi\Keenable\KeenableServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [KeenableServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('keenable.api_key', 'keen_test_key');
        $app['config']->set('keenable.base_url', 'https://api.keenable.ai');
        $app['config']->set('keenable.app_title', 'Test App');
    }
}
