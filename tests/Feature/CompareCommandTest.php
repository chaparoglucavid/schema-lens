<?php

declare(strict_types=1);

namespace SchemaLens\Tests\Feature;

use SchemaLens\Tests\TestCase;

final class CompareCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->artisan('schema:lens --help')
            ->assertSuccessful();
    }

    public function test_migration_command_is_registered(): void
    {
        $this->artisan('schema:lens:migration --help')
            ->assertSuccessful();
    }

    public function test_invalid_connection_fails_gracefully(): void
    {
        $this->artisan('schema:lens', [
            '--from' => 'does_not_exist',
            '--to' => 'production',
        ])->assertFailed();
    }

    public function test_unsupported_sqlite_driver_fails_clearly(): void
    {
        $this->artisan('schema:lens', [
            '--from' => 'testing',
            '--to' => 'production',
            '--json' => true,
        ])->assertFailed();
    }
}
