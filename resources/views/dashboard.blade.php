@extends('schemalens::layout')

@section('title', 'SchemaLens')

@section('content')
<div
    x-data="schemaLensDashboard(@js([
        'compareUrl' => route('schemalens.compare'),
        'exportUrl' => url(config('schemalens.route_prefix', 'schema-lens').'/export'),
        'migrationUrl' => route('schemalens.migration'),
        'defaultFrom' => $defaultFrom,
        'defaultTo' => $defaultTo,
        'csrf' => csrf_token(),
        'connections' => collect($connections)->map(fn ($c) => $c->toArray($showHost))->values()->all(),
        'showHost' => $showHost,
    ]))"
    class="space-y-8"
>
    {{-- Hero compare bar --}}
    <section class="sl-panel overflow-hidden">
        <div class="border-b border-line px-5 py-5 dark:border-line-dark sm:px-7 sm:py-6">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand">Database comparison</p>
            <h1 class="mt-2 font-display text-2xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-[1.75rem]">
                Compare two connections
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-mute dark:text-mute-dark">
                Pick a source schema and a target. SchemaLens shows exact diffs — then gives you reviewable SQL.
            </p>
        </div>

        <div class="grid gap-4 px-5 py-5 sm:px-7 sm:py-6 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="sl-label">From <span class="font-normal text-mute">(desired)</span></label>
                <select x-model="from" class="sl-select w-full">
                    <template x-for="conn in connections" :key="conn.name">
                        <option :value="conn.name" x-text="connLabel(conn)"></option>
                    </template>
                </select>
                <p class="sl-meta" x-text="connMeta(from)"></p>
            </div>

            <div class="hidden h-10 items-center justify-center text-slate-300 dark:text-slate-600 md:flex" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m0 0l-5-5m5 5l-5 5"/></svg>
            </div>

            <div>
                <label class="sl-label">To <span class="font-normal text-mute">(target)</span></label>
                <select x-model="to" class="sl-select w-full">
                    <template x-for="conn in connections" :key="'to-'+conn.name">
                        <option :value="conn.name" x-text="connLabel(conn)"></option>
                    </template>
                </select>
                <p class="sl-meta" x-text="connMeta(to)"></p>
            </div>

            <button type="button"
                    @click="compare()"
                    :disabled="loading || !from || !to || from === to"
                    class="sl-btn-primary h-10 w-full md:w-auto">
                <svg x-show="loading" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span x-text="loading ? 'Comparing…' : 'Compare'"></span>
            </button>
        </div>

        <div x-show="error" x-cloak class="border-t border-red-200 bg-red-50 px-5 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300 sm:px-7" x-text="error"></div>
    </section>

    {{-- Empty state --}}
    <section x-show="!result && !loading" x-cloak class="sl-empty">
        <div class="sl-empty-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h5m-9 8l2-2h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h2 class="font-display text-lg font-semibold tracking-tight">Git diff for database schemas</h2>
        <p class="mt-2 max-w-md text-sm leading-relaxed text-mute dark:text-mute-dark">
            Choose connections above and compare. Missing tables, column drift, indexes, and foreign keys appear here — with SQL you can review.
        </p>
    </section>

    {{-- Results --}}
    <template x-if="result">
        <div class="space-y-6">
            {{-- Compact summary strip --}}
            <section class="sl-summary">
                <div class="sl-summary-item">
                    <span class="sl-summary-value" x-text="(summary.from_table_count ?? 0) + ' / ' + (summary.to_table_count ?? 0)"></span>
                    <span class="sl-summary-label">Tables</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-value sl-tone-danger" x-text="summary.missing_tables ?? 0"></span>
                    <span class="sl-summary-label">Missing</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-value sl-tone-warn" x-text="summary.extra_tables ?? 0"></span>
                    <span class="sl-summary-label">Extra</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-value" x-text="summary.column_changes ?? 0"></span>
                    <span class="sl-summary-label">Columns</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-value" x-text="summary.index_changes ?? 0"></span>
                    <span class="sl-summary-label">Indexes</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-value" x-text="summary.foreign_key_changes ?? 0"></span>
                    <span class="sl-summary-label">Foreign keys</span>
                </div>
            </section>

            <section class="sl-panel">
                {{-- Tabs + actions --}}
                <div class="flex flex-col gap-3 border-b border-line px-4 pt-3 dark:border-line-dark sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <nav class="flex gap-1 overflow-x-auto pb-px" aria-label="Result tabs">
                        <template x-for="tab in tabs" :key="tab.id">
                            <button type="button"
                                    @click="selectTab(tab.id)"
                                    class="sl-tab"
                                    :class="activeTab === tab.id ? 'sl-tab-active' : ''">
                                <span x-text="tab.label"></span>
                                <span x-show="tab.count !== null" class="sl-tab-count" x-text="tab.count"></span>
                            </button>
                        </template>
                    </nav>

                    <div class="relative pb-2 sm:pb-0" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="sl-btn-secondary text-xs">
                            Export
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="sl-menu">
                            <a :href="exportLink('json')" class="sl-menu-item">Download JSON</a>
                            <a :href="exportLink('html')" class="sl-menu-item">Download HTML</a>
                            <a :href="exportLink('sql')" class="sl-menu-item">Download SQL</a>
                            <button type="button" @click="generateMigration(); open = false" class="sl-menu-item w-full text-left" :disabled="migrating">
                                <span x-text="migrating ? 'Generating…' : 'Generate migration'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    {{-- Overview --}}
                    <div x-show="activeTab === 'overview'" class="space-y-5">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-mute dark:text-mute-dark">
                            <span class="font-mono text-slate-800 dark:text-slate-200" x-text="from"></span>
                            <span aria-hidden="true">→</span>
                            <span class="font-mono text-slate-800 dark:text-slate-200" x-text="to"></span>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span><span x-text="Number(summary.duration_ms || 0).toFixed(0)"></span> ms</span>
                        </div>

                        <div x-show="(summary.total_differences || 0) === 0" class="sl-sync">
                            Schemas are in sync — no differences found.
                        </div>

                        <div x-show="(summary.total_differences || 0) > 0" class="grid gap-3 sm:grid-cols-2">
                            <div class="sl-quiet-box">
                                <div class="sl-label mb-2">Source</div>
                                <div class="font-mono text-sm font-medium" x-text="from"></div>
                                <div class="mt-1 text-xs text-mute dark:text-mute-dark" x-text="connMeta(from)"></div>
                            </div>
                            <div class="sl-quiet-box">
                                <div class="sl-label mb-2">Target</div>
                                <div class="font-mono text-sm font-medium" x-text="to"></div>
                                <div class="mt-1 text-xs text-mute dark:text-mute-dark" x-text="connMeta(to)"></div>
                            </div>
                        </div>

                        <p x-show="(summary.total_differences || 0) > 0" class="text-sm text-mute dark:text-mute-dark">
                            <strong class="text-slate-800 dark:text-slate-100" x-text="summary.total_differences"></strong>
                            difference(s) detected. Open a tab above to inspect details or review SQL.
                        </p>
                    </div>

                    {{-- Diff lists --}}
                    <div x-show="['tables','columns','indexes','foreign_keys'].includes(activeTab)">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="f in filters" :key="f.id">
                                    <button type="button"
                                            @click="filter = f.id"
                                            class="sl-chip"
                                            :class="filter === f.id ? 'sl-chip-active' : ''"
                                            x-text="f.label"></button>
                                </template>
                            </div>
                            <div class="relative w-full sm:w-64">
                                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                                <input type="search" x-model="search" placeholder="Search tables…" class="sl-input w-full pl-9">
                            </div>
                        </div>

                        <div class="sl-table-list">
                            <template x-for="table in filteredTables" :key="table.name">
                                <div class="sl-table-row" :class="expanded[table.name] ? 'sl-table-row-open' : ''">
                                    <button type="button" @click="toggleTable(table.name)" class="sl-table-head">
                                        <svg class="sl-chevron" :class="expanded[table.name] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                                        <span class="font-mono text-[13px] font-medium tracking-tight" x-text="table.name"></span>
                                        <span class="sl-badge" :class="'sl-badge-' + table.status" x-text="table.status_label"></span>
                                        <span class="ml-auto text-[11px] text-mute dark:text-mute-dark"
                                              x-text="table.difference_count ? (table.difference_count + (table.difference_count === 1 ? ' change' : ' changes')) : 'No differences'"></span>
                                    </button>

                                    <div x-show="expanded[table.name]" x-cloak class="sl-table-body">
                                        <template x-if="relevantDiffs(table).length === 0">
                                            <p class="px-4 py-4 text-sm text-mute dark:text-mute-dark">No differences in this category.</p>
                                        </template>

                                        <template x-for="diff in relevantDiffs(table)" :key="diff.type + ':' + diff.object">
                                            <article class="sl-diff">
                                                <div class="sl-diff-head">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h3 class="truncate font-mono text-sm font-semibold" x-text="diff.object"></h3>
                                                            <span class="sl-type-pill" x-text="prettyType(diff.type)"></span>
                                                            <span x-show="diff.destructive" class="sl-danger-pill">Destructive</span>
                                                        </div>
                                                        <p class="mt-1 text-xs text-mute dark:text-mute-dark" x-text="diff.message"></p>
                                                    </div>
                                                    <div x-show="diff.changed_attributes && diff.changed_attributes.length" class="flex flex-wrap gap-1">
                                                        <template x-for="attr in (diff.changed_attributes || [])" :key="attr">
                                                            <span class="sl-attr" x-text="attr"></span>
                                                        </template>
                                                    </div>
                                                </div>

                                                <div class="sl-diff-grid">
                                                    <div class="sl-diff-side sl-diff-from">
                                                        <div class="sl-diff-side-label" x-text="from"></div>
                                                        <pre class="sl-diff-code" x-html="formatSide(diff.from, diff.to, 'from')"></pre>
                                                    </div>
                                                    <div class="sl-diff-side sl-diff-to">
                                                        <div class="sl-diff-side-label" x-text="to"></div>
                                                        <pre class="sl-diff-code" x-html="formatSide(diff.to, diff.from, 'to')"></pre>
                                                    </div>
                                                </div>
                                            </article>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <p x-show="filteredTables.length === 0" class="px-4 py-10 text-center text-sm text-mute dark:text-mute-dark">
                                No tables match this filter.
                            </p>
                        </div>
                    </div>

                    {{-- SQL --}}
                    <div x-show="activeTab === 'sql'" class="space-y-4">
                        <div x-show="sql.has_destructive" class="sl-warn">
                            <strong>Destructive operations present.</strong>
                            This SQL may drop tables, columns, or indexes. SchemaLens will never execute it for you.
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="copySql()" class="sl-btn-primary text-sm">
                                <span x-text="copied ? 'Copied' : 'Copy SQL'"></span>
                            </button>
                            <a :href="exportLink('sql')" class="sl-btn-secondary text-sm">Download .sql</a>
                        </div>

                        <pre class="sl-sql" x-text="sql.text || '-- No SQL generated.'"></pre>
                    </div>

                    <p x-show="migrationMessage" x-cloak class="mt-4 rounded-lg bg-brand-mist px-3 py-2 text-sm text-brand dark:bg-blue-950/40 dark:text-blue-300" x-text="migrationMessage"></p>
                </div>
            </section>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script src="{{ route('schemalens.assets.js') }}"></script>
@endpush
