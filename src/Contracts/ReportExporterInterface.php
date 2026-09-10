<?php

declare(strict_types=1);

namespace SchemaLens\Contracts;

use SchemaLens\DTO\ComparisonResult;

interface ReportExporterInterface
{
    public function export(ComparisonResult $result): string;

    public function extension(): string;

    public function mimeType(): string;
}
