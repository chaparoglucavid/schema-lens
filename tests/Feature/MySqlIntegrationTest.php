<?php

declare(strict_types=1);

namespace SchemaLens\Tests\Feature;

use Illuminate\Support\Facades\DB;
use SchemaLens\Database\Inspectors\MySqlSchemaInspector;
use SchemaLens\Services\SchemaComparisonService;
use SchemaLens\Sql\SqlGenerator;
use SchemaLens\Tests\TestCase;

/**
 * Real MySQL/MariaDB integration tests.
 *
 * Enable with:
 *   SCHEMA_LENS_TEST_MYSQL=true
 *   SCHEMA_LENS_MYSQL_HOST=127.0.0.1
 *   SCHEMA_LENS_MYSQL_USER=root
 *   SCHEMA_LENS_MYSQL_PASSWORD=
 *   SCHEMA_LENS_MYSQL_DB_A=schemalens_a
 *   SCHEMA_LENS_MYSQL_DB_B=schemalens_b
 */
final class MySqlIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('SCHEMA_LENS_TEST_MYSQL', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Set SCHEMA_LENS_TEST_MYSQL=true to run MySQL integration tests.');
        }

        try {
            DB::connection('mysql_a')->getPdo();
            DB::connection('mysql_b')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL not available: '.$e->getMessage());
        }

        $this->resetSchemas();
    }

    public function test_inspects_and_compares_real_mysql_schemas(): void
    {
        DB::connection('mysql_a')->statement('CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(255) NULL,
            UNIQUE KEY users_email_unique (email)
        ) ENGINE=InnoDB');

        DB::connection('mysql_a')->statement('CREATE TABLE special_permission_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_uid CHAR(36) NOT NULL,
            CONSTRAINT fk_spi_company FOREIGN KEY (company_uid) REFERENCES users (email) ON DELETE CASCADE
        ) ENGINE=InnoDB');

        // Intentionally broken FK target for demo? Better use a companies table
        DB::connection('mysql_a')->statement('DROP TABLE IF EXISTS special_permission_items');
        DB::connection('mysql_a')->statement('CREATE TABLE companies (
            uid CHAR(36) PRIMARY KEY
        ) ENGINE=InnoDB');
        DB::connection('mysql_a')->statement('CREATE TABLE special_permission_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_uid CHAR(36) NOT NULL,
            CONSTRAINT fk_spi_company FOREIGN KEY (company_uid) REFERENCES companies (uid) ON DELETE CASCADE
        ) ENGINE=InnoDB');

        DB::connection('mysql_b')->statement('CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(100) NOT NULL,
            KEY users_email_index (email)
        ) ENGINE=InnoDB');

        DB::connection('mysql_b')->statement('CREATE TABLE companies (
            uid CHAR(36) PRIMARY KEY
        ) ENGINE=InnoDB');

        DB::connection('mysql_b')->statement('CREATE TABLE special_permission_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_uid CHAR(36) NOT NULL,
            CONSTRAINT fk_spi_company FOREIGN KEY (company_uid) REFERENCES companies (uid) ON DELETE RESTRICT
        ) ENGINE=InnoDB');

        DB::connection('mysql_b')->statement('CREATE TABLE orphan_logs (
            id INT PRIMARY KEY
        ) ENGINE=InnoDB');

        $service = $this->app->make(SchemaComparisonService::class);
        $result = $service->compare('mysql_a', 'mysql_b', ignoreTables: [], useCache: false);

        $summary = $result->summary();
        $this->assertGreaterThanOrEqual(1, $summary->extraTables);
        $this->assertGreaterThanOrEqual(1, $summary->columnChanges);
        $this->assertGreaterThanOrEqual(1, $summary->indexChanges);
        $this->assertGreaterThanOrEqual(1, $summary->foreignKeyChanges);

        $sql = (new SqlGenerator)->toSqlString($result);
        $this->assertStringContainsString('MODIFY COLUMN', $sql);
        $this->assertStringNotContainsString('password', strtolower($sql));

        $inspector = new MySqlSchemaInspector;
        $schema = $inspector->inspect('mysql_a', []);
        $this->assertArrayHasKey('users', $schema->tables);
        $this->assertArrayHasKey('email', $schema->tables['users']->columns);
    }

    public function test_ignore_tables_excludes_from_comparison(): void
    {
        DB::connection('mysql_a')->statement('CREATE TABLE keep_me (id INT PRIMARY KEY)');
        DB::connection('mysql_a')->statement('CREATE TABLE skip_me (id INT PRIMARY KEY)');
        DB::connection('mysql_b')->statement('CREATE TABLE keep_me (id INT PRIMARY KEY)');

        $service = $this->app->make(SchemaComparisonService::class);
        $result = $service->compare('mysql_a', 'mysql_b', ignoreTables: ['skip_me'], useCache: false);

        $this->assertArrayNotHasKey('skip_me', $result->tableStatuses);
        $this->assertFalse($result->hasDifferences());
    }

    private function resetSchemas(): void
    {
        foreach (['mysql_a', 'mysql_b'] as $connection) {
            $db = DB::connection($connection);
            $database = $db->getDatabaseName();
            $tables = $db->select(
                'SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'',
                [$database]
            );

            $db->statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                $db->statement('DROP TABLE IF EXISTS `'.$table->name.'`');
            }
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
