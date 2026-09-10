<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable SchemaLens
    |--------------------------------------------------------------------------
    |
    | When disabled, routes and the dashboard will not be registered.
    |
    */

    'enabled' => env('SCHEMA_LENS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URI prefix for the SchemaLens dashboard.
    | Example: /schema-lens
    |
    */

    'route_prefix' => env('SCHEMA_LENS_ROUTE_PREFIX', 'schema-lens'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all SchemaLens routes. Defaults to web + auth
    | so schema information is not publicly exposed.
    |
    */

    'middleware' => [
        'web',
        'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Connections
    |--------------------------------------------------------------------------
    |
    | Pre-selected connection names on the dashboard and CLI prompts.
    |
    */

    'default_from' => env('SCHEMA_LENS_DEFAULT_FROM', 'mysql'),

    'default_to' => env('SCHEMA_LENS_DEFAULT_TO', null),

    /*
    |--------------------------------------------------------------------------
    | Ignore Tables
    |--------------------------------------------------------------------------
    |
    | Tables matching these names are excluded from comparison.
    |
    */

    'ignore_tables' => [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'failed_jobs',
        'job_batches',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'horizon_jobs',
        'horizon_job_histories',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Columns
    |--------------------------------------------------------------------------
    |
    | Column names excluded from comparison across all tables.
    | Disabled by default (empty array).
    |
    */

    'ignore_columns' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache inspected schema metadata via Laravel's cache abstraction.
    | No permanent SchemaLens database tables are created.
    |
    */

    'cache' => [
        'enabled' => env('SCHEMA_LENS_CACHE_ENABLED', true),
        'ttl' => (int) env('SCHEMA_LENS_CACHE_TTL', 300),
        'store' => env('SCHEMA_LENS_CACHE_STORE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Host
    |--------------------------------------------------------------------------
    |
    | Whether to show database host information in the UI.
    | Hostnames can be considered sensitive in some environments.
    |
    */

    'show_host' => env('SCHEMA_LENS_SHOW_HOST', true),

];
