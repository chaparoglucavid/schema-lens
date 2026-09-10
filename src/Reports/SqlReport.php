<?php

declare(strict_types=1);

namespace SchemaLens\Reports;

use SchemaLens\Contracts\ReportExporterInterface;
use SchemaLens\DTO\ComparisonResult;
use SchemaLens\Sql\SqlGenerator;
use SchemaLens\Support\CredentialSanitizer;

final class SqlReport implements ReportExporterInterface
{
    public function __construct(
        private readonly SqlGenerator $generator = new SqlGenerator,
    ) {}

    public function export(ComparisonResult $result): string
    {
        return CredentialSanitizer::sanitize($this->generator->toSqlString($result));
    }

    public function extension(): string
    {
        return 'sql';
    }

    public function mimeType(): string
    {
        return 'application/sql';
    }
}
