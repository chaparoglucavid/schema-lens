<?php

declare(strict_types=1);

namespace SchemaLens\Reports;

use SchemaLens\Contracts\ReportExporterInterface;
use SchemaLens\DTO\ComparisonResult;
use SchemaLens\Support\CredentialSanitizer;

final class JsonReport implements ReportExporterInterface
{
    public function export(ComparisonResult $result): string
    {
        $data = CredentialSanitizer::sanitizeArray($result->toArray());

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    public function extension(): string
    {
        return 'json';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }
}
