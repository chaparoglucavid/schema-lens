<?php

declare(strict_types=1);

namespace SchemaLens\Reports;

use SchemaLens\Contracts\ReportExporterInterface;
use SchemaLens\DTO\ComparisonResult;
use SchemaLens\Support\CredentialSanitizer;

final class HtmlReport implements ReportExporterInterface
{
    public function export(ComparisonResult $result): string
    {
        $summary = $result->summary();
        $from = $this->esc($result->from->connection);
        $to = $this->esc($result->to->connection);
        $generatedAt = date('Y-m-d H:i:s');

        $rows = '';
        foreach ($result->differences as $diff) {
            $type = $this->esc($diff->type->value);
            $table = $this->esc($diff->table);
            $object = $this->esc($diff->object);
            $message = $this->esc($diff->message);
            $severity = $this->esc($diff->severity->value);
            $badge = $diff->destructive ? '<span class="destructive">DESTRUCTIVE</span>' : '';

            $fromVal = $this->esc($this->formatSide($diff->from));
            $toVal = $this->esc($this->formatSide($diff->to));

            $rows .= <<<HTML
            <tr>
                <td><code>{$type}</code> {$badge}</td>
                <td>{$table}</td>
                <td>{$object}</td>
                <td class="sev-{$severity}">{$severity}</td>
                <td>{$message}</td>
                <td><pre>{$fromVal}</pre></td>
                <td><pre>{$toVal}</pre></td>
            </tr>
            HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No differences found. Schemas are in sync.</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SchemaLens Report — {$from} → {$to}</title>
<style>
body{font-family:ui-sans-serif,system-ui,sans-serif;margin:2rem;background:#0f172a;color:#e2e8f0}
h1{font-size:1.5rem;margin:0}
.tagline{color:#94a3b8;margin-bottom:1.5rem}
.cards{display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:2rem}
.card{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1rem 1.25rem;min-width:120px}
.card .n{font-size:1.5rem;font-weight:700}
.card .l{font-size:.75rem;color:#94a3b8;text-transform:uppercase}
table{width:100%;border-collapse:collapse;font-size:.875rem}
th,td{border:1px solid #334155;padding:.5rem .75rem;vertical-align:top;text-align:left}
th{background:#1e293b}
pre{margin:0;white-space:pre-wrap;font-family:ui-monospace,monospace;font-size:.75rem}
.destructive{background:#7f1d1d;color:#fecaca;padding:.1rem .4rem;border-radius:4px;font-size:.65rem}
.sev-critical{color:#f87171}.sev-warning{color:#fbbf24}.sev-destructive{color:#f87171}.sev-info{color:#94a3b8}
.meta{color:#64748b;font-size:.8rem;margin-bottom:1rem}
</style>
</head>
<body>
<h1>SchemaLens</h1>
<p class="tagline">See exactly what changed in your database.</p>
<p class="meta">FROM: <strong>{$from}</strong> &rarr; TO: <strong>{$to}</strong> &middot; Generated at {$generatedAt}</p>
<div class="cards">
  <div class="card"><div class="n">{$summary->fromTableCount} / {$summary->toTableCount}</div><div class="l">Tables</div></div>
  <div class="card"><div class="n">{$summary->missingTables}</div><div class="l">Missing Tables</div></div>
  <div class="card"><div class="n">{$summary->extraTables}</div><div class="l">Extra Tables</div></div>
  <div class="card"><div class="n">{$summary->columnChanges}</div><div class="l">Column Changes</div></div>
  <div class="card"><div class="n">{$summary->indexChanges}</div><div class="l">Index Changes</div></div>
  <div class="card"><div class="n">{$summary->foreignKeyChanges}</div><div class="l">Foreign Key Changes</div></div>
</div>
<table>
<thead>
<tr>
  <th>Type</th><th>Table</th><th>Object</th><th>Severity</th><th>Message</th><th>From</th><th>To</th>
</tr>
</thead>
<tbody>
{$rows}
</tbody>
</table>
</body>
</html>
HTML;

        return CredentialSanitizer::sanitize($html);
    }

    public function extension(): string
    {
        return 'html';
    }

    public function mimeType(): string
    {
        return 'text/html';
    }

    /**
     * @param  array<string, mixed>|null  $side
     */
    private function formatSide(?array $side): string
    {
        if ($side === null) {
            return 'MISSING';
        }

        if (isset($side['type_definition'])) {
            $parts = [
                $side['type_definition'] ?? '',
                $side['nullable'] ?? '',
                $side['default'] ?? '',
            ];

            return implode("\n", array_filter($parts, fn ($p) => $p !== '' && $p !== null));
        }

        if (isset($side['definition'])) {
            return (string) $side['definition'];
        }

        return json_encode($side, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
