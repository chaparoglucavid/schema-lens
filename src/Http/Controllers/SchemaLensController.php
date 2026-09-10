<?php

declare(strict_types=1);

namespace SchemaLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use SchemaLens\Exceptions\SchemaLensException;
use SchemaLens\Migration\MigrationGenerator;
use SchemaLens\Reports\HtmlReport;
use SchemaLens\Reports\JsonReport;
use SchemaLens\Reports\SqlReport;
use SchemaLens\Services\SchemaComparisonService;
use SchemaLens\Sql\SqlGenerator;
use SchemaLens\Support\CredentialSanitizer;
use Throwable;

final class SchemaLensController extends Controller
{
    public function __construct(
        private readonly SchemaComparisonService $comparisonService,
        private readonly SqlGenerator $sqlGenerator,
        private readonly MigrationGenerator $migrationGenerator,
    ) {}

    public function index(): View
    {
        $connections = $this->comparisonService->connections();
        $showHost = (bool) Config::get('schemalens.show_host', true);

        return view('schemalens::dashboard', [
            'connections' => $connections,
            'defaultFrom' => Config::get('schemalens.default_from'),
            'defaultTo' => Config::get('schemalens.default_to'),
            'showHost' => $showHost,
            'routePrefix' => Config::get('schemalens.route_prefix', 'schema-lens'),
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string', 'different:from'],
            'ignore' => ['sometimes', 'array'],
            'ignore.*' => ['string'],
            'no_cache' => ['sometimes', 'boolean'],
        ]);

        try {
            $ignoreTables = array_values(array_unique(array_merge(
                Config::get('schemalens.ignore_tables', []),
                $validated['ignore'] ?? []
            )));

            $result = $this->comparisonService->compare(
                fromConnection: $validated['from'],
                toConnection: $validated['to'],
                ignoreTables: $ignoreTables,
                useCache: ! ($validated['no_cache'] ?? false),
            );

            $sqlStatements = $this->sqlGenerator->generate($result);
            $sqlText = $this->sqlGenerator->toSqlString($result);

            $payload = CredentialSanitizer::sanitizeArray([
                'ok' => true,
                'result' => $result->toArray(),
                'summary' => $result->summary()->toArray(),
                'grouped' => $this->groupForUi($result),
                'sql' => [
                    'text' => $sqlText,
                    'statements' => array_map(fn ($s) => $s->toArray(), $sqlStatements),
                    'has_destructive' => collect($sqlStatements)->contains(fn ($s) => $s->destructive),
                ],
            ]);

            return response()->json($payload);
        } catch (SchemaLensException $e) {
            return response()->json([
                'ok' => false,
                'error' => CredentialSanitizer::sanitize($e->getMessage()),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => CredentialSanitizer::sanitize(
                    'An unexpected error occurred while comparing schemas.'
                ),
            ], 500);
        }
    }

    public function export(Request $request, string $format): Response|JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string', 'different:from'],
        ]);

        try {
            $result = $this->comparisonService->compare(
                $validated['from'],
                $validated['to'],
            );

            $exporter = match (strtolower($format)) {
                'json' => new JsonReport,
                'html' => new HtmlReport,
                'sql' => new SqlReport($this->sqlGenerator),
                default => null,
            };

            if ($exporter === null) {
                return response()->json(['ok' => false, 'error' => 'Unsupported export format.'], 422);
            }

            $content = $exporter->export($result);
            $filename = sprintf(
                'schemalens-%s-%s.%s',
                $validated['from'],
                $validated['to'],
                $exporter->extension()
            );

            return response($content, 200, [
                'Content-Type' => $exporter->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (SchemaLensException $e) {
            return response()->json([
                'ok' => false,
                'error' => CredentialSanitizer::sanitize($e->getMessage()),
            ], 422);
        }
    }

    public function migration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string', 'different:from'],
        ]);

        try {
            $result = $this->comparisonService->compare(
                $validated['from'],
                $validated['to'],
            );

            $path = $this->migrationGenerator->generate($result);

            return response()->json([
                'ok' => true,
                'path' => $path,
                'message' => 'Migration generated. Review carefully before running.',
            ]);
        } catch (SchemaLensException $e) {
            return response()->json([
                'ok' => false,
                'error' => CredentialSanitizer::sanitize($e->getMessage()),
            ], 422);
        }
    }

    /**
     * @return array{tables: list<array<string, mixed>>}
     */
    private function groupForUi(\SchemaLens\DTO\ComparisonResult $result): array
    {
        $tables = [];

        foreach ($result->tableStatuses as $table => $status) {
            $diffs = $result->differencesForTable($table);

            $tables[] = [
                'name' => $table,
                'status' => $status->value,
                'status_label' => $status->label(),
                'difference_count' => count($diffs),
                'differences' => array_map(fn ($d) => $d->toArray(), $diffs),
            ];
        }

        usort($tables, function (array $a, array $b) {
            $order = ['removed' => 0, 'added' => 1, 'modified' => 2, 'unchanged' => 3];

            return ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9)
                ?: strcmp($a['name'], $b['name']);
        });

        return ['tables' => $tables];
    }
}
