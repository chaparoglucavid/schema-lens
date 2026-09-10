<?php

declare(strict_types=1);

namespace SchemaLens\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SchemaLens\Providers\SchemaLensServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SchemaLensServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('schemalens.enabled', true);
        $app['config']->set('schemalens.middleware', ['web']);
        $app['config']->set('schemalens.ignore_tables', ['migrations', 'cache', 'sessions']);
        $app['config']->set('schemalens.ignore_columns', []);
        $app['config']->set('schemalens.cache.enabled', false);
        $app['config']->set('schemalens.show_host', true);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('database.connections.mysql_a', [
            'driver' => 'mysql',
            'host' => env('SCHEMA_LENS_MYSQL_HOST', '127.0.0.1'),
            'port' => env('SCHEMA_LENS_MYSQL_PORT', '3306'),
            'database' => env('SCHEMA_LENS_MYSQL_DB_A', 'schemalens_a'),
            'username' => env('SCHEMA_LENS_MYSQL_USER', 'root'),
            'password' => env('SCHEMA_LENS_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $app['config']->set('database.connections.mysql_b', [
            'driver' => 'mysql',
            'host' => env('SCHEMA_LENS_MYSQL_HOST', '127.0.0.1'),
            'port' => env('SCHEMA_LENS_MYSQL_PORT', '3306'),
            'database' => env('SCHEMA_LENS_MYSQL_DB_B', 'schemalens_b'),
            'username' => env('SCHEMA_LENS_MYSQL_USER', 'root'),
            'password' => env('SCHEMA_LENS_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $app['config']->set('database.connections.production', [
            'driver' => 'mysql',
            'host' => '10.0.0.5',
            'port' => 3306,
            'database' => 'app_production',
            'username' => 'app',
            'password' => 'super-secret-password-do-not-leak',
        ]);
    }
}
