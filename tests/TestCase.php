<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile\Tests;

use HansDeBoeck\Turnstile\TurnstileServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TurnstileServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('turnstile.key', 'test-sitekey');
        $app['config']->set('turnstile.secret', 'test-secret');
    }
}
