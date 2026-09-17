/**
 * SchemaLens dashboard client logic (Alpine.js component factory).
 */
function schemaLensDashboard(config) {
    return {
        compareUrl: config.compareUrl,
        exportUrl: config.exportUrl,
        migrationUrl: config.migrationUrl,
        csrf: config.csrf,
        connections: config.connections || [],
        showHost: config.showHost !== false,

        from: config.defaultFrom || (config.connections[0] && config.connections[0].name) || '',
        to: config.defaultTo || (config.connections[1] && config.connections[1].name) || '',

        loading: false,
        migrating: false,
        copied: false,
        error: null,
        migrationMessage: null,

        result: null,
        summary: {},
        tables: [],
        sql: { text: '', statements: [], has_destructive: false },

        activeTab: 'overview',
        filter: 'changes',
        search: '',
        expanded: {},
        filters: [
            { id: 'changes', label: 'Changes' },
            { id: 'added', label: 'Added' },
            { id: 'removed', label: 'Removed' },
            { id: 'modified', label: 'Modified' },
            { id: 'unchanged', label: 'Unchanged' },
            { id: 'all', label: 'All' },
        ],

        get tabs() {
            const s = this.summary || {};
            return [
                { id: 'overview', label: 'Overview', count: null },
                { id: 'tables', label: 'Tables', count: (s.missing_tables || 0) + (s.extra_tables || 0) + (s.modified_tables || 0) },
                { id: 'columns', label: 'Columns', count: s.column_changes || 0 },
                { id: 'indexes', label: 'Indexes', count: s.index_changes || 0 },
                { id: 'foreign_keys', label: 'FKs', count: s.foreign_key_changes || 0 },
                { id: 'sql', label: 'SQL', count: null },
            ];
        },

        get filteredTables() {
            const q = (this.search || '').toLowerCase().trim();
            return (this.tables || []).filter((t) => {
                if (this.filter === 'changes') {
                    if (t.status === 'unchanged') return false;
                } else if (this.filter !== 'all' && t.status !== this.filter) {
                    return false;
                }
                if (this.activeTab === 'tables') {
                    // show all matching filter
                } else if (this.activeTab === 'columns') {
                    if (!t.differences.some((d) => d.category === 'columns' || (d.type || '').startsWith('COLUMN_'))) {
                        if (t.status === 'unchanged') return this.filter === 'unchanged' || this.filter === 'all';
                        // still show added/removed tables
                        if (!['added', 'removed'].includes(t.status)) return false;
                    }
                } else if (this.activeTab === 'indexes') {
                    if (!t.differences.some((d) => d.category === 'indexes' || (d.type || '').startsWith('INDEX_'))) {
                        if (!['added', 'removed'].includes(t.status) && t.status !== 'unchanged') return false;
                        if (t.status === 'unchanged' && this.filter !== 'unchanged' && this.filter !== 'all') return false;
                        if (!['added', 'removed', 'unchanged'].includes(t.status) && this.filter === 'all') return false;
                    }
                } else if (this.activeTab === 'foreign_keys') {
                    if (!t.differences.some((d) => d.category === 'foreign_keys' || (d.type || '').startsWith('FOREIGN_'))) {
                        if (!['added', 'removed'].includes(t.status)) return false;
                    }
                }
                if (q && !t.name.toLowerCase().includes(q)) return false;
                return true;
            });
        },

        prettyType(type) {
            return String(type || '')
                .replace(/_/g, ' ')
                .toLowerCase()
                .replace(/\b\w/g, (c) => c.toUpperCase());
        },

        connLabel(conn) {
            return conn.name + (conn.driver ? ' · ' + conn.driver : '');
        },

        connMeta(name) {
            const conn = this.connections.find((c) => c.name === name);
            if (!conn) return '';
            const parts = [];
            if (conn.driver) parts.push(conn.driver);
            if (conn.database) parts.push(conn.database);
            if (this.showHost && conn.host) parts.push(conn.host + (conn.port ? ':' + conn.port : ''));
            return parts.join(' · ');
        },

        async compare() {
            this.error = null;
            this.migrationMessage = null;
            this.loading = true;
            this.result = null;

            try {
                const res = await fetch(this.compareUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        from: this.from,
                        to: this.to,
                    }),
                });

                const data = await res.json();

                if (!data.ok) {
                    this.error = data.error || 'Comparison failed.';
                    return;
                }

                this.result = data.result;
                this.summary = data.summary || {};
                this.tables = (data.grouped && data.grouped.tables) || [];
                this.sql = data.sql || { text: '', statements: [], has_destructive: false };
                this.activeTab = 'overview';
                // Accordions are intentionally collapsed by default.
                this.expanded = {};
            } catch (e) {
                this.error = 'Unable to reach SchemaLens. Check that you are authenticated and the route is available.';
            } finally {
                this.loading = false;
            }
        },

        toggleTable(name) {
            this.expanded[name] = !this.expanded[name];
        },

        selectTab(tab) {
            this.activeTab = tab;
            // Start every result tab with its table accordions closed.
            this.expanded = {};
        },

        relevantDiffs(table) {
            const diffs = table.differences || [];
            if (this.activeTab === 'tables') {
                return diffs.filter((d) =>
                    (d.type || '').startsWith('TABLE_') || diffs.length <= 20
                ).length
                    ? diffs.filter((d) => (d.type || '').startsWith('TABLE_')).concat(
                        diffs.filter((d) => !(d.type || '').startsWith('TABLE_')).slice(0, 50)
                    )
                    : diffs;
            }
            if (this.activeTab === 'columns') {
                return diffs.filter((d) => (d.category === 'columns') || (d.type || '').startsWith('COLUMN_'));
            }
            if (this.activeTab === 'indexes') {
                return diffs.filter((d) => (d.category === 'indexes') || (d.type || '').startsWith('INDEX_'));
            }
            if (this.activeTab === 'foreign_keys') {
                return diffs.filter((d) => (d.category === 'foreign_keys') || (d.type || '').startsWith('FOREIGN_'));
            }
            return diffs;
        },

        formatSide(side, other, which) {
            if (side === null || side === undefined) {
                return '<span class="sl-diff-missing">MISSING</span>';
            }

            const escape = (v) => String(v)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            if (side.type_definition !== undefined) {
                const lines = [
                    { key: 'type_definition', label: side.type_definition },
                    { key: 'nullable', label: side.nullable },
                    { key: 'default', label: side.default },
                ];
                if (side.auto_increment) lines.push({ key: 'auto_increment', label: 'AUTO_INCREMENT' });
                if (side.comment) lines.push({ key: 'comment', label: 'COMMENT: ' + side.comment });

                return lines.map((line) => {
                    const changed = other && other[line.key] !== side[line.key];
                    const cls = changed ? 'sl-diff-changed' : 'sl-diff-dim';
                    return '<span class="' + cls + '">' + escape(line.label) + '</span>';
                }).join('\n');
            }

            if (side.definition) {
                return escape(side.definition);
            }

            return escape(JSON.stringify(side, null, 2));
        },

        exportLink(format) {
            const url = new URL(this.exportUrl + '/' + format, window.location.origin);
            url.searchParams.set('from', this.from);
            url.searchParams.set('to', this.to);
            return url.toString();
        },

        async copySql() {
            try {
                await navigator.clipboard.writeText(this.sql.text || '');
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1500);
            } catch (e) {
                this.error = 'Unable to copy to clipboard.';
            }
        },

        async generateMigration() {
            this.migrating = true;
            this.migrationMessage = null;
            this.error = null;

            try {
                const res = await fetch(this.migrationUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ from: this.from, to: this.to }),
                });
                const data = await res.json();
                if (!data.ok) {
                    this.error = data.error || 'Migration generation failed.';
                    return;
                }
                this.migrationMessage = data.message + (data.path ? ' → ' + data.path : '');
            } catch (e) {
                this.error = 'Unable to generate migration.';
            } finally {
                this.migrating = false;
            }
        },
    };
}
