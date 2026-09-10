<?php

declare(strict_types=1);

namespace SchemaLens\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use SchemaLens\Exceptions\SchemaLensException;
use SchemaLens\Reports\HtmlReport;
use SchemaLens\Reports\JsonReport;
use SchemaLens\Reports\SqlReport;
use SchemaLens\Services\SchemaComparisonService;
use SchemaLens\Sql\SqlGenerator;
use SchemaLens\Support\CredentialSanitizer;
use Symfony\Component\Console\Helper\Table;
use Throwable;

final class CompareCommand extends Command
{
    protected $signature = 'schema:lens
                            {--from= : Source database connection (desired schema)}
                            {--to= : Target database connection to compare against}
                            {--ignore= : Comma-separated table names to ignore}
                            {--json : Output comparison as JSON}
                            {--html : Output comparison as HTML}
                            {--sql : Output generated SQL}
                            {--no-cache : Bypass schema metadata cache}';

    protected $description = 'Compare two Laravel database schemas (read-only)';

    public function handle(SchemaComparisonService $service, SqlGenerator $sqlGenerator): int
    {
        $this->newLine();
        $this->components->info('SchemaLens');
        $this->line('  <fg=gray>See exactly what changed in your database.</>');
        $this->newLine();

        try {
            $from = $this->option('from') ?: $this->askConnection($service, 'Select source connection', Config::get('schemalens.default_from'));
            $to = $this->option('to') ?: $this->askConnection($service, 'Select target connection', Config::get('schemalens.default_to'));

            if ($from === $to) {
                $this->components->error('FROM and TO connections must be different.');

                return self::FAILURE;
            }

            $ignore = $this->parseIgnore();
            $ignoreTables = array_values(array_unique(array_merge(
                Config::get('schemalens.ignore_tables', []),
                $ignore
            )));

            $this->components->twoColumnDetail('FROM', $from);
            $this->components->twoColumnDetail('TO', $to);
            $this->newLine();
            $this->components->task('Comparing schemas', function () use ($service, $from, $to, $ignoreTables, &$result) {
                $result = $service->compare(
                    fromConnection: $from,
                    toConnection: $to,
                    ignoreTables: $ignoreTables,
                    useCache: ! $this->option('no-cache'),
                );
            });

            /** @var \SchemaLens\DTO\ComparisonResult $result */
            if ($this->option('json')) {
                $this->line((new JsonReport)->export($result));

                return $result->hasDifferences() ? self::FAILURE : self::SUCCESS;
            }

            if ($this->option('html')) {
                $this->line((new HtmlReport)->export($result));

                return $result->hasDifferences() ? self::FAILURE : self::SUCCESS;
            }

            if ($this->option('sql')) {
                $this->line((new SqlReport($sqlGenerator))->export($result));

                return $result->hasDifferences() ? self::FAILURE : self::SUCCESS;
            }

            $this->renderSummary($result);
            $this->renderDifferences($result);

            return $result->hasDifferences() ? self::FAILURE : self::SUCCESS;
        } catch (SchemaLensException $e) {
            $this->components->error(CredentialSanitizer::sanitize($e->getMessage()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error(CredentialSanitizer::sanitize(
                'Unable to complete comparison: '.$e->getMessage()
            ));

            return self::FAILURE;
        }
    }

    private function askConnection(SchemaComparisonService $service, string $question, ?string $default): string
    {
        $connections = array_map(fn ($c) => $c->name, $service->connections());

        if ($connections === []) {
            throw new SchemaLensException('No database connections are configured.');
        }

        if ($default !== null && in_array($default, $connections, true)) {
            return (string) $this->choice($question, $connections, array_search($default, $connections, true));
        }

        return (string) $this->choice($question, $connections);
    }

    /**
     * @return list<string>
     */
    private function parseIgnore(): array
    {
        $raw = $this->option('ignore');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function renderSummary(\SchemaLens\DTO\ComparisonResult $result): void
    {
        $summary = $result->summary();

        $this->line('  <fg=cyan;options=bold>SUMMARY</>');
        $this->newLine();

        $table = new Table($this->output);
        $table->setStyle('box');
        $table->setRows([
            ['Tables', "{$summary->fromTableCount} / {$summary->toTableCount}"],
            ['Missing tables', (string) $summary->missingTables],
            ['Extra tables', (string) $summary->extraTables],
            ['Column differences', (string) $summary->columnChanges],
            ['Index differences', (string) $summary->indexChanges],
            ['Foreign key changes', (string) $summary->foreignKeyChanges],
            ['Duration (ms)', number_format($summary->durationMs, 2)],
        ]);
        $table->render();
        $this->newLine();

        if (! $result->hasDifferences()) {
            $this->components->info('No differences found. Schemas are in sync.');
        }
    }

    private function renderDifferences(\SchemaLens\DTO\ComparisonResult $result): void
    {
        if (! $result->hasDifferences()) {
            return;
        }

        $this->line('  <fg=yellow;options=bold>DIFFERENCES</>');
        $this->newLine();

        foreach ($result->differences as $diff) {
            $tag = match (true) {
                $diff->type->isRemoved() => '<fg=red;options=bold>[REMOVED]</>',
                $diff->type->isAdded() => '<fg=green;options=bold>[ADDED]</>',
                default => '<fg=yellow;options=bold>[MODIFIED]</>',
            };

            $this->line("  {$tag} <options=bold>{$diff->table}</>.{$diff->object}");
            $this->line('  <fg=gray>'.$diff->message.'</>');

            if ($diff->destructive) {
                $this->line('  <fg=red>⚠ Destructive if applied toward source</>');
            }

            if ($diff->from !== null) {
                $this->line('  <fg=cyan>FROM:</> '.$this->sideLabel($diff->from));
            }
            if ($diff->to !== null) {
                $this->line('  <fg=magenta>TO:</>   '.$this->sideLabel($diff->to));
            } elseif ($diff->type->isRemoved()) {
                $this->line('  <fg=magenta>TO:</>   <fg=red>MISSING</>');
            }

            $this->line('  <fg=gray>────────────────────────────────────</>');
        }
    }

    /**
     * @param  array<string, mixed>  $side
     */
    private function sideLabel(array $side): string
    {
        if (isset($side['type_definition'])) {
            return trim(($side['type_definition'] ?? '').' '.($side['nullable'] ?? ''));
        }

        if (isset($side['definition'])) {
            return (string) $side['definition'];
        }

        if (isset($side['name']) && isset($side['columns'])) {
            return (string) ($side['definition'] ?? json_encode($side));
        }

        return (string) json_encode($side, JSON_UNESCAPED_SLASHES);
    }
}
