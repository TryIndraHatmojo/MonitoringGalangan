<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'mysql'
            || filled($app['config']->get('database.connections.mysql.url'))
            || ! str_ends_with((string) $app['config']->get('database.connections.mysql.database'), '_test')) {
            throw new RuntimeException('Tes memerlukan konfigurasi MySQL eksplisit dan database berakhiran _test.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
