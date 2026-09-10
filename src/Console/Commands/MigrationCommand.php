<?php

declare(strict_types=1);

namespace SchemaLens\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use SchemaLens\Exceptions\SchemaLensException;
use SchemaLens\Migration\MigrationGenerator;
use SchemaLens\Services\SchemaComparisonService;
use SchemaLens\Support\CredentialSanitizer;
use Throwable;

final class MigrationCommand extends Command
{
    protected $signature = 'schema:lens:migration
                            {--from= : Source database connection (desired schema)}
                            {--to= : Target database connection to sync toward}
                            {--ignore= : Comma-separated table names to ignore}
                            {--path= : Custom migrations directory}
                            {--no-cache : Bypass schema metadata cache}';

    protected $description = 'Generate a Laravel migration to make TO match FROM (does not execute)';

    public function handle(SchemaComparisonService $service, MigrationGenerator $generator): int
    {
        $this->newLine();
        $this->components->info('SchemaLens Migration Generator');
        $this->line('  <fg=gray>Generates a migration file only — never executes it.</>');
        $this->newLine();

        try {
            $from = $this->option('from') ?: $this->ask('Source connection', Config::get('schemalens.default_from', 'mysql'));
            $to = $this->option('to') ?: $this->ask('Target connection');

            if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
                $this->components->error('Both --from and --to are required.');

                return self::FAILURE;
            }

            if ($from === $to) {
                $this->components->error('FROM and TO connections must be different.');

                return self::FAILURE;
            }

            $ignore = [];
            if (is_string($this->option('ignore')) && $this->option('ignore') !== '') {
                $ignore = array_values(array_filter(array_map('trim', explode(',', $this->option('ignore')))));
            }

            $ignoreTables = array_values(array_unique(array_merge(
                Config::get('schemalens.ignore_tables', []),
                $ignore
            )));

            $result = $service->compare(
                fromConnection: $from,
                toConnection: $to,
                ignoreTables: $ignoreTables,
                useCache: ! $this->option('no-cache'),
            );

            $path = is_string($this->option('path')) && $this->option('path') !== ''
                ? $this->option('path')
                : null;

            $file = $generator->generate($result, $path);

            $this->components->info('Migration generated:');
            $this->line('  '.$file);
            $this->newLine();
            $this->components->warn('Review this migration carefully before execution.');
            $this->line('  SchemaLens does not automatically execute generated migrations.');

            return self::SUCCESS;
        } catch (SchemaLensException $e) {
            $this->components->error(CredentialSanitizer::sanitize($e->getMessage()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error(CredentialSanitizer::sanitize($e->getMessage()));

            return self::FAILURE;
        }
    }
}
