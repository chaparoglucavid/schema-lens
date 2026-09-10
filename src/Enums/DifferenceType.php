<?php

declare(strict_types=1);

namespace SchemaLens\Enums;

enum DifferenceType: string
{
    case TableAdded = 'TABLE_ADDED';
    case TableRemoved = 'TABLE_REMOVED';
    case TableModified = 'TABLE_MODIFIED';

    case ColumnAdded = 'COLUMN_ADDED';
    case ColumnRemoved = 'COLUMN_REMOVED';
    case ColumnModified = 'COLUMN_MODIFIED';

    case IndexAdded = 'INDEX_ADDED';
    case IndexRemoved = 'INDEX_REMOVED';
    case IndexModified = 'INDEX_MODIFIED';

    case ForeignKeyAdded = 'FOREIGN_KEY_ADDED';
    case ForeignKeyRemoved = 'FOREIGN_KEY_REMOVED';
    case ForeignKeyModified = 'FOREIGN_KEY_MODIFIED';

    public function category(): string
    {
        return match ($this) {
            self::TableAdded, self::TableRemoved, self::TableModified => 'tables',
            self::ColumnAdded, self::ColumnRemoved, self::ColumnModified => 'columns',
            self::IndexAdded, self::IndexRemoved, self::IndexModified => 'indexes',
            self::ForeignKeyAdded, self::ForeignKeyRemoved, self::ForeignKeyModified => 'foreign_keys',
        };
    }

    public function isAdded(): bool
    {
        return str_ends_with($this->value, '_ADDED');
    }

    public function isRemoved(): bool
    {
        return str_ends_with($this->value, '_REMOVED');
    }

    public function isModified(): bool
    {
        return str_ends_with($this->value, '_MODIFIED');
    }

    public function label(): string
    {
        return match ($this) {
            self::TableAdded => 'Table Added',
            self::TableRemoved => 'Table Removed',
            self::TableModified => 'Table Modified',
            self::ColumnAdded => 'Column Added',
            self::ColumnRemoved => 'Column Removed',
            self::ColumnModified => 'Column Modified',
            self::IndexAdded => 'Index Added',
            self::IndexRemoved => 'Index Removed',
            self::IndexModified => 'Index Modified',
            self::ForeignKeyAdded => 'Foreign Key Added',
            self::ForeignKeyRemoved => 'Foreign Key Removed',
            self::ForeignKeyModified => 'Foreign Key Modified',
        };
    }
}
