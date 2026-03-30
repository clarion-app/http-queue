<?php

namespace ClarionApp\HttpQueue\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ClarionApp\HttpQueue\HttpQueueServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HttpQueueServiceProvider::class,
        ];
    }
}
