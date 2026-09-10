@extends('schemalens::layout')

@section('title', 'SchemaLens')

@section('content')
<div    x-data="schemaLensDashboard(@js([
        'compareUrl' => route('schemalens.compare'),
        'exportUrl' => url(config('schemalens.route_prefix', 'schema-lens').'/export'),
        'migrationUrl' => route('schemalens.migration'),
        'defaultFrom' => $defaultFrom,
        'defaultTo' => $defaultTo,
        'csrf' => csrf_token(),
        'connections' => collect($connections)->map(fn ($c) => $c->toArray($showHost))->values()->all(),
        'showHost' => $showHost,
    ]))"
    class="space-y-6"
>
    {{-- Connection selectors --}}
    <section class="rounded-2xl border border-ink-200 bg-white/80 p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900/60 sm:p-6">
        <div class="mb-5">
            <h1 class="font-display text-2xl font-semibold tracking-tight sm:text-3xl">Compare databases</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Select any two configured Laravel connections. Comparison is read-only.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-[1fr_auto_1fr_auto] md:items-end">
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink-500">From</label>
                <select x-model="from"
                        class="sl-select w-full">
                    <template x-for="conn in connections" :key="conn.name">
                        <option :value="conn.name" x-text="connLabel(conn)"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-ink-400" x-text="connMeta(from)"></p>
            </div>

            <div class="hidden items-center justify-center pb-2 text-ink-400 md:flex" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink-500">To</label>
                <select x-model="to"
                        class="sl-select w-full">
                    <template x-for="conn in connections" :key="'to-'+conn.name">
                        <option :value="conn.name" x-text="connLabel(conn)"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-ink-400" x-text="connMeta(to)"></p>
            </div>

            <button type="button"
                    @click="compare()"
                    :disabled="loading || !from || !to || from === to"
                    class="sl-btn-primary inline-flex items-center justify-center gap-2 whitespace-nowrap">
                <svg x-show="loading" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span x-text="loading ? 'Comparing…' : 'Compare Databases'"></span>
            </button>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300" x-text="error"></p>
    </section>

    {{-- Empty state --}}
    <section x-show="!result && !loading" x-cloak class="rounded-2xl border border-dashed border-ink-300 bg-white/40 px-6 py-16 text-center dark:border-ink-700 dark:bg-ink-900/30">
        <div class="mx-auto max-w-md">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-accent/10 text-accent">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h2 class="font-display text-lg font-semibold">Git diff for your database schemas</h2>
            <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">Choose a source and target connection, then compare. SchemaLens highlights missing tables, column drifts, indexes, and foreign keys — and generates SQL you can review.</p>
        </div>
    </section>

    {{-- Results --}}
    <template x-if="result">
        <div class="space-y-6">
            {{-- Summary cards --}}
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="sl-card">
                    <div class="sl-card-value" x-text="(summary.from_table_count ?? 0) + ' / ' + (summary.to_table_count ?? 0)"></div>
                    <div class="sl-card-label">Tables</div>
                </div>
                <div class="sl-card">
                    <div class="sl-card-value text-red-600 dark:text-red-400" x-text="summary.missing_tables ?? 0"></div>
                    <div class="sl-card-label">Missing Tables</div>
                </div>
                <div class="sl-card">
                    <div class="sl-card-value text-amber-600 dark:text-amber-400" x-text="summary.extra_tables ?? 0"></div>
                    <div class="sl-card-label">Extra Tables</div>
                </div>
                <div class="sl-card">
                    <div class="sl-card-value" x-text="summary.column_changes ?? 0"></div>
                    <div class="sl-card-label">Column Changes</div>
                </div>
                <div class="sl-card">
                    <div class="sl-card-value" x-text="summary.index_changes ?? 0"></div>
                    <div class="sl-card-label">Index Changes</div>
                </div>
                <div class="sl-card">
                    <div class="sl-card-value" x-text="summary.foreign_key_changes ?? 0"></div>
                    <div class="sl-card-label">Foreign Key Changes</div>
                </div>
            </section>

            {{-- Tabs --}}
            <section class="rounded-2xl border border-ink-200 bg-white/80 dark:border-ink-800 dark:bg-ink-900/60">
                <div class="flex flex-wrap items-center gap-1 border-b border-ink-200 px-3 pt-3 dark:border-ink-800 sm:px-4">
                    <template x-for="tab in tabs" :key="tab.id">
                        <button type="button"
                                @click="activeTab = tab.id"
                                class="rounded-t-lg px-3 py-2 text-sm font-medium transition"
                                :class="activeTab === tab.id
                                    ? 'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-white'
                                    : 'text-ink-500 hover:text-ink-800 dark:hover:text-ink-200'"
                                x-text="tab.label + (tab.count !== null ? ' (' + tab.count + ')' : '')">
                        </button>
                    </template>

                    <div class="ml-auto flex flex-wrap gap-2 pb-2">
                        <a :href="exportLink('json')" class="sl-btn-ghost text-xs">JSON</a>
                        <a :href="exportLink('html')" class="sl-btn-ghost text-xs">HTML</a>
                        <a :href="exportLink('sql')" class="sl-btn-ghost text-xs">SQL</a>
                        <button type="button" @click="generateMigration()" class="sl-btn-ghost text-xs" :disabled="migrating">
                            <span x-text="migrating ? 'Generating…' : 'Migration'"></span>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    {{-- Overview --}}
                    <div x-show="activeTab === 'overview'" class="space-y-4">
                        <p class="text-sm text-ink-600 dark:text-ink-300">
                            Compared <strong x-text="from"></strong> → <strong x-text="to"></strong>
                            in <span x-text="Number(summary.duration_ms || 0).toFixed(1)"></span> ms.
                            <span x-show="(summary.total_differences || 0) === 0" class="text-accent">Schemas are in sync.</span>
                            <span x-show="(summary.total_differences || 0) > 0">
                                Found <strong x-text="summary.total_differences"></strong> difference(s).
                            </span>
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
                                <div class="text-xs font-semibold uppercase text-ink-400">Source (desired)</div>
                                <div class="mt-1 font-mono text-sm" x-text="from"></div>
                                <div class="mt-1 text-xs text-ink-500" x-text="connMeta(from)"></div>
                            </div>
                            <div class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
                                <div class="text-xs font-semibold uppercase text-ink-400">Target</div>
                                <div class="mt-1 font-mono text-sm" x-text="to"></div>
                                <div class="mt-1 text-xs text-ink-500" x-text="connMeta(to)"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Tables / Columns / Indexes / FKs share table list --}}
                    <div x-show="['tables','columns','indexes','foreign_keys'].includes(activeTab)">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-1">
                                <template x-for="f in filters" :key="f">
                                    <button type="button"
                                            @click="filter = f"
                                            class="rounded-md px-2.5 py-1 text-xs font-medium capitalize"
                                            :class="filter === f ? 'bg-accent text-white' : 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300'"
                                            x-text="f"></button>
                                </template>
                            </div>
                            <input type="search"
                                   x-model="search"
                                   placeholder="Search tables…"
                                   class="sl-input w-full sm:w-64">
                        </div>

                        <div class="space-y-2">
                            <template x-for="table in filteredTables" :key="table.name">
                                <div class="overflow-hidden rounded-xl border border-ink-200 dark:border-ink-700">
                                    <button type="button"
                                            @click="toggleTable(table.name)"
                                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-ink-50 dark:hover:bg-ink-800/60">
                                        <span class="text-ink-400" x-text="expanded[table.name] ? '▼' : '▶'"></span>
                                        <span class="font-mono text-sm font-medium" x-text="table.name"></span>
                                        <span class="sl-badge" :class="'sl-badge-' + table.status" x-text="table.status_label"></span>
                                        <span class="ml-auto text-xs text-ink-400"
                                              x-text="table.difference_count ? (table.difference_count + ' change' + (table.difference_count === 1 ? '' : 's')) : 'No differences'"></span>
                                    </button>

                                    <div x-show="expanded[table.name]" x-cloak class="border-t border-ink-200 bg-ink-50/50 dark:border-ink-700 dark:bg-ink-950/40">
                                        <template x-if="relevantDiffs(table).length === 0">
                                            <p class="px-4 py-3 text-sm text-ink-500">No differences in this category.</p>
                                        </template>
                                        <template x-for="diff in relevantDiffs(table)" :key="diff.type + diff.object">
                                            <div class="border-b border-ink-200/70 px-4 py-3 last:border-0 dark:border-ink-800">
                                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                                    <span class="font-mono text-sm font-semibold" x-text="diff.object"></span>
                                                    <span class="rounded bg-ink-200 px-1.5 py-0.5 font-mono text-[10px] uppercase dark:bg-ink-700" x-text="diff.type"></span>
                                                    <span x-show="diff.destructive" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-700 dark:bg-red-950 dark:text-red-300">Destructive</span>
                                                </div>
                                                <p class="mb-3 text-xs text-ink-500" x-text="diff.message"></p>

                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div class="rounded-lg border border-ink-200 bg-white p-3 dark:border-ink-700 dark:bg-ink-900">
                                                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-accent" x-text="from"></div>
                                                        <pre class="whitespace-pre-wrap font-mono text-xs leading-relaxed" x-html="formatSide(diff.from, diff.to, 'from')"></pre>
                                                    </div>
                                                    <div class="rounded-lg border border-ink-200 bg-white p-3 dark:border-ink-700 dark:bg-ink-900">
                                                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-ink-400" x-text="to"></div>
                                                        <pre class="whitespace-pre-wrap font-mono text-xs leading-relaxed" x-html="formatSide(diff.to, diff.from, 'to')"></pre>
                                                    </div>
                                                </div>

                                                <div x-show="diff.changed_attributes && diff.changed_attributes.length" class="mt-2 flex flex-wrap gap-1">
                                                    <template x-for="attr in (diff.changed_attributes || [])" :key="attr">
                                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-amber-800 dark:bg-amber-950 dark:text-amber-300" x-text="attr"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <p x-show="filteredTables.length === 0" class="py-8 text-center text-sm text-ink-500">No tables match this filter.</p>
                        </div>
                    </div>

                    {{-- SQL Preview --}}
                    <div x-show="activeTab === 'sql'" class="space-y-4">
                        <div x-show="sql.has_destructive" class="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <span class="text-lg leading-none">⚠</span>
                            <div>
                                <div class="font-semibold">Destructive operations present</div>
                                <p class="mt-0.5 text-xs opacity-90">This SQL may remove data or database objects (DROP TABLE / DROP COLUMN / DROP INDEX). Review before executing. SchemaLens will never run this automatically.</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="copySql()" class="sl-btn-primary text-sm">
                                <span x-text="copied ? 'Copied!' : 'Copy SQL'"></span>
                            </button>
                            <a :href="exportLink('sql')" class="sl-btn-ghost text-sm">Download SQL</a>
                        </div>

                        <pre class="max-h-[32rem] overflow-auto rounded-xl border border-ink-800 bg-ink-950 p-4 font-mono text-xs leading-relaxed text-ink-100" x-text="sql.text || '-- No SQL generated.'"></pre>
                    </div>

                    <p x-show="migrationMessage" x-cloak class="mt-4 rounded-lg border border-accent/30 bg-accent/5 px-3 py-2 text-sm text-accent-dark dark:text-accent-soft" x-text="migrationMessage"></p>
                </div>
            </section>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script src="{{ route('schemalens.assets.js') }}"></script>
@endpush
