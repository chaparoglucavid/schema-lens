<?php

declare(strict_types=1);

namespace SchemaLens\Tests\Unit;

use SchemaLens\Comparison\SchemaComparator;
use SchemaLens\DTO\ColumnSchema;
use SchemaLens\DTO\DatabaseSchema;
use SchemaLens\DTO\ForeignKeySchema;
use SchemaLens\DTO\IndexSchema;
use SchemaLens\DTO\TableSchema;
use SchemaLens\Enums\DifferenceType;
use SchemaLens\Enums\IndexType;
use SchemaLens\Enums\TableStatus;
use SchemaLens\Tests\TestCase;

final class SchemaComparatorTest extends TestCase
{
    private SchemaComparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comparator = new SchemaComparator;
    }

    public function test_detects_table_added_and_removed(): void
    {
        $from = $this->schema('mysql', [
            'users' => $this->simpleTable('users'),
            'posts' => $this->simpleTable('posts'),
        ]);

        $to = $this->schema('production', [
            'users' => $this->simpleTable('users'),
            'logs' => $this->simpleTable('logs'),
        ]);

        $result = $this->comparator->compare($from, $to);

        $this->assertTrue($result->hasDifferences());
        $this->assertSame(TableStatus::Removed, $result->tableStatuses['posts']);
        $this->assertSame(TableStatus::Added, $result->tableStatuses['logs']);
        $this->assertSame(TableStatus::Unchanged, $result->tableStatuses['users']);
        $this->assertCount(1, $result->differencesOfType(DifferenceType::TableRemoved));
        $this->assertCount(1, $result->differencesOfType(DifferenceType::TableAdded));
    }

    public function test_unchanged_tables_produce_no_differences(): void
    {
        $table = $this->simpleTable('users');
        $from = $this->schema('a', ['users' => $table]);
        $to = $this->schema('b', ['users' => $table]);

        $result = $this->comparator->compare($from, $to);

        $this->assertFalse($result->hasDifferences());
        $this->assertSame(0, $result->summary()->totalDifferences);
    }

    public function test_detects_column_added_removed_and_modified(): void
    {
        $from = $this->schema('a', [
            'users' => new TableSchema(
                name: 'users',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, autoIncrement: true, unsigned: true, ordinalPosition: 1),
                    'email' => new ColumnSchema('email', 'varchar', 'varchar', length: 255, nullable: false, ordinalPosition: 2),
                    'status' => new ColumnSchema('status', 'varchar', 'varchar', length: 255, nullable: true, default: null, ordinalPosition: 3),
                ],
            ),
        ]);

        $to = $this->schema('b', [
            'users' => new TableSchema(
                name: 'users',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, autoIncrement: true, unsigned: true, ordinalPosition: 1),
                    'email' => new ColumnSchema('email', 'varchar', 'varchar', length: 255, nullable: false, ordinalPosition: 2),
                    'status' => new ColumnSchema('status', 'varchar', 'varchar', length: 100, nullable: false, default: null, ordinalPosition: 3),
                    'nickname' => new ColumnSchema('nickname', 'varchar', 'varchar', length: 50, nullable: true, ordinalPosition: 4),
                ],
            ),
        ]);

        // Remove "bio" from from only by not including it in to — add bio to from
        $from = $this->schema('a', [
            'users' => new TableSchema(
                name: 'users',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, autoIncrement: true, unsigned: true, ordinalPosition: 1),
                    'email' => new ColumnSchema('email', 'varchar', 'varchar', length: 255, nullable: false, ordinalPosition: 2),
                    'status' => new ColumnSchema('status', 'varchar', 'varchar', length: 255, nullable: true, default: null, ordinalPosition: 3),
                    'bio' => new ColumnSchema('bio', 'text', 'text', nullable: true, ordinalPosition: 4),
                ],
            ),
        ]);

        $result = $this->comparator->compare($from, $to);

        $this->assertNotEmpty($result->differencesOfType(DifferenceType::ColumnRemoved)); // bio missing on to
        $this->assertNotEmpty($result->differencesOfType(DifferenceType::ColumnAdded)); // nickname only on to
        $this->assertNotEmpty($result->differencesOfType(DifferenceType::ColumnModified)); // status

        $statusDiff = $result->differencesOfType(DifferenceType::ColumnModified)[0];
        $this->assertSame('status', $statusDiff->object);
        $this->assertContains('length', $statusDiff->changedAttributes);
        $this->assertContains('nullable', $statusDiff->changedAttributes);
    }

    public function test_detects_default_unsigned_auto_increment_and_comment_changes(): void
    {
        $fromCol = new ColumnSchema(
            name: 'amount',
            type: 'int',
            nativeType: 'int',
            nullable: false,
            default: 0,
            autoIncrement: false,
            unsigned: true,
            comment: 'money',
            ordinalPosition: 1,
        );

        $toCol = new ColumnSchema(
            name: 'amount',
            type: 'int',
            nativeType: 'int',
            nullable: false,
            default: 1,
            autoIncrement: true,
            unsigned: false,
            comment: 'updated',
            ordinalPosition: 1,
        );

        $from = $this->schema('a', ['t' => new TableSchema('t', columns: ['amount' => $fromCol])]);
        $to = $this->schema('b', ['t' => new TableSchema('t', columns: ['amount' => $toCol])]);

        $result = $this->comparator->compare($from, $to);
        $diff = $result->differencesOfType(DifferenceType::ColumnModified)[0];

        $this->assertContains('default', $diff->changedAttributes);
        $this->assertContains('unsigned', $diff->changedAttributes);
        $this->assertContains('auto_increment', $diff->changedAttributes);
        $this->assertContains('comment', $diff->changedAttributes);
    }

    public function test_detects_index_differences(): void
    {
        $from = $this->schema('a', [
            'users' => new TableSchema(
                name: 'users',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, ordinalPosition: 1),
                    'email' => new ColumnSchema('email', 'varchar', 'varchar', length: 255, nullable: false, ordinalPosition: 2),
                ],
                indexes: [
                    'PRIMARY' => new IndexSchema('PRIMARY', IndexType::Primary, true, ['id']),
                    'users_email_unique' => new IndexSchema('users_email_unique', IndexType::Unique, true, ['email']),
                ],
            ),
        ]);

        $to = $this->schema('b', [
            'users' => new TableSchema(
                name: 'users',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, ordinalPosition: 1),
                    'email' => new ColumnSchema('email', 'varchar', 'varchar', length: 255, nullable: false, ordinalPosition: 2),
                ],
                indexes: [
                    'PRIMARY' => new IndexSchema('PRIMARY', IndexType::Primary, true, ['id']),
                    'users_email_index' => new IndexSchema('users_email_index', IndexType::Index, false, ['email']),
                ],
            ),
        ]);

        $result = $this->comparator->compare($from, $to);

        $this->assertNotEmpty($result->differencesOfType(DifferenceType::IndexRemoved));
        $this->assertNotEmpty($result->differencesOfType(DifferenceType::IndexAdded));
    }

    public function test_detects_primary_key_modification(): void
    {
        $from = $this->schema('a', [
            't' => new TableSchema(
                name: 't',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, ordinalPosition: 1),
                    'uuid' => new ColumnSchema('uuid', 'char', 'char', length: 36, nullable: false, ordinalPosition: 2),
                ],
                indexes: [
                    'PRIMARY' => new IndexSchema('PRIMARY', IndexType::Primary, true, ['id']),
                ],
            ),
        ]);

        $to = $this->schema('b', [
            't' => new TableSchema(
                name: 't',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, ordinalPosition: 1),
                    'uuid' => new ColumnSchema('uuid', 'char', 'char', length: 36, nullable: false, ordinalPosition: 2),
                ],
                indexes: [
                    'PRIMARY' => new IndexSchema('PRIMARY', IndexType::Primary, true, ['uuid']),
                ],
            ),
        ]);

        $result = $this->comparator->compare($from, $to);
        $this->assertNotEmpty($result->differencesOfType(DifferenceType::IndexModified));
    }

    public function test_detects_foreign_key_added_removed_and_on_delete_change(): void
    {
        $fromFk = new ForeignKeySchema('fk_posts_user', ['user_id'], 'users', ['id'], 'CASCADE', 'CASCADE');
        $toFk = new ForeignKeySchema('fk_posts_user', ['user_id'], 'users', ['id'], 'RESTRICT', 'CASCADE');

        $from = $this->schema('a', [
            'posts' => new TableSchema(
                name: 'posts',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, ordinalPosition: 1),
                    'user_id' => new ColumnSchema('user_id', 'bigint', 'bigint', nullable: false, ordinalPosition: 2),
                ],
                foreignKeys: ['fk_posts_user' => $fromFk, 'fk_extra' => new ForeignKeySchema('fk_extra', ['user_id'], 'users', ['id'])],
            ),
        ]);

        $to = $this->schema('b', [
            'posts' => new TableSchema(
                name: 'posts',
                columns: [
                    'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, ordinalPosition: 1),
                    'user_id' => new ColumnSchema('user_id', 'bigint', 'bigint', nullable: false, ordinalPosition: 2),
                ],
                foreignKeys: [
                    'fk_posts_user' => $toFk,
                    'fk_new' => new ForeignKeySchema('fk_new', ['user_id'], 'users', ['id']),
                ],
            ),
        ]);

        $result = $this->comparator->compare($from, $to);

        $this->assertNotEmpty($result->differencesOfType(DifferenceType::ForeignKeyRemoved));
        $this->assertNotEmpty($result->differencesOfType(DifferenceType::ForeignKeyAdded));
        $modified = $result->differencesOfType(DifferenceType::ForeignKeyModified);
        $this->assertNotEmpty($modified);
        $this->assertContains('on_delete', $modified[0]->changedAttributes);
    }

    public function test_ignores_configured_columns(): void
    {
        $from = $this->schema('a', [
            't' => new TableSchema('t', columns: [
                'id' => new ColumnSchema('id', 'int', 'int', nullable: false, ordinalPosition: 1),
                'temporary_column' => new ColumnSchema('temporary_column', 'varchar', 'varchar', length: 10, ordinalPosition: 2),
            ]),
        ]);

        $to = $this->schema('b', [
            't' => new TableSchema('t', columns: [
                'id' => new ColumnSchema('id', 'int', 'int', nullable: false, ordinalPosition: 1),
            ]),
        ]);

        $result = $this->comparator->compare($from, $to, ignoreColumns: ['temporary_column']);
        $this->assertFalse($result->hasDifferences());
    }

    public function test_summary_counts(): void
    {
        $from = $this->schema('a', [
            'keep' => $this->simpleTable('keep'),
            'gone' => $this->simpleTable('gone'),
            'changed' => new TableSchema('changed', columns: [
                'a' => new ColumnSchema('a', 'varchar', 'varchar', length: 10, ordinalPosition: 1),
            ]),
        ]);

        $to = $this->schema('b', [
            'keep' => $this->simpleTable('keep'),
            'extra' => $this->simpleTable('extra'),
            'changed' => new TableSchema('changed', columns: [
                'a' => new ColumnSchema('a', 'varchar', 'varchar', length: 20, ordinalPosition: 1),
            ]),
        ]);

        $summary = $this->comparator->compare($from, $to)->summary();

        $this->assertSame(3, $summary->fromTableCount);
        $this->assertSame(3, $summary->toTableCount);
        $this->assertSame(1, $summary->missingTables);
        $this->assertSame(1, $summary->extraTables);
        $this->assertSame(1, $summary->columnChanges);
    }

    /**
     * @param  array<string, TableSchema>  $tables
     */
    private function schema(string $connection, array $tables): DatabaseSchema
    {
        return new DatabaseSchema($connection, 'mysql', 'db_'.$connection, $tables);
    }

    private function simpleTable(string $name): TableSchema
    {
        return new TableSchema(
            name: $name,
            columns: [
                'id' => new ColumnSchema('id', 'bigint', 'bigint', nullable: false, autoIncrement: true, ordinalPosition: 1),
            ],
            indexes: [
                'PRIMARY' => new IndexSchema('PRIMARY', IndexType::Primary, true, ['id']),
            ],
        );
    }
}
