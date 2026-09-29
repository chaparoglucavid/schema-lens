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
            { id: 'changes', label: 'Fərqlər' },
            { id: 'added', label: 'Hədəfdə artıqdır' },
            { id: 'removed', label: 'Hədəfdə yoxdur' },
            { id: 'modified', label: 'Dəyişdirilib' },
            { id: 'unchanged', label: 'Eynidir' },
            { id: 'all', label: 'Hamısı' },
        ],

        get tabs() {
            const s = this.summary || {};
            return [
                { id: 'overview', label: 'İcmal', count: null },
                { id: 'tables', label: 'Cədvəllər', count: (s.missing_tables || 0) + (s.extra_tables || 0) + (s.modified_tables || 0) },
                { id: 'columns', label: 'Sütunlar', count: s.column_changes || 0 },
                { id: 'indexes', label: 'İndekslər', count: s.index_changes || 0 },
                { id: 'foreign_keys', label: 'Xarici açarlar', count: s.foreign_key_changes || 0 },
                { id: 'sql', label: 'SQL', count: null },
            ];
        },

        get filteredTables() {
            const q = (this.search || '').toLowerCase().trim();
            return (this.tables || []).filter((t) => {
                const differences = t.differences || [];
                const hasCategoryDifference = (category, prefix) => differences.some((d) =>
                    d.category === category || (d.type || '').startsWith(prefix)
                );

                if (this.activeTab === 'tables') {
                    // Table status is applied below, after category-specific filtering.
                } else if (this.activeTab === 'columns') {
                    if (!hasCategoryDifference('columns', 'COLUMN_')) {
                        if (t.status === 'unchanged') return this.filter === 'unchanged' || this.filter === 'all';
                        if (!['added', 'removed'].includes(t.status)) return false;
                    }
                } else if (this.activeTab === 'indexes') {
                    if (!hasCategoryDifference('indexes', 'INDEX_')) {
                        if (!['added', 'removed'].includes(t.status) && t.status !== 'unchanged') return false;
                        if (t.status === 'unchanged' && this.filter !== 'unchanged' && this.filter !== 'all') return false;
                        if (!['added', 'removed', 'unchanged'].includes(t.status) && this.filter === 'all') return false;
                    }
                } else if (this.activeTab === 'foreign_keys') {
                    if (!hasCategoryDifference('foreign_keys', 'FOREIGN_')) {
                        if (!['added', 'removed'].includes(t.status)) return false;
                    }
                }

                if (this.filter === 'changes' && t.status === 'unchanged') return false;
                if (this.filter !== 'changes' && this.filter !== 'all' && t.status !== this.filter) return false;
                if (q && !t.name.toLowerCase().includes(q)) return false;
                return true;
            });
        },

        prettyType(type) {
            const labels = {
                TABLE_ADDED: 'Cədvəl hədəfdə artıqdır',
                TABLE_REMOVED: 'Cədvəl hədəfdə yoxdur',
                TABLE_MODIFIED: 'Cədvəl dəyişdirilib',
                COLUMN_ADDED: 'Sütun hədəfdə artıqdır',
                COLUMN_REMOVED: 'Sütun hədəfdə yoxdur',
                COLUMN_MODIFIED: 'Sütun dəyişdirilib',
                INDEX_ADDED: 'İndeks hədəfdə artıqdır',
                INDEX_REMOVED: 'İndeks hədəfdə yoxdur',
                INDEX_MODIFIED: 'İndeks dəyişdirilib',
                FOREIGN_KEY_ADDED: 'Xarici açar hədəfdə artıqdır',
                FOREIGN_KEY_REMOVED: 'Xarici açar hədəfdə yoxdur',
                FOREIGN_KEY_MODIFIED: 'Xarici açar dəyişdirilib',
            };
            return labels[type] || String(type || '').replace(/_/g, ' ');
        },

        statusLabel(status) {
            const labels = {
                added: 'Hədəfdə artıqdır',
                removed: 'Hədəfdə yoxdur',
                modified: 'Dəyişdirilib',
                unchanged: 'Eynidir',
            };
            return labels[status] || status;
        },

        differenceMessage(diff) {
            const object = '`' + (diff.object || '') + '`';
            const table = '`' + (diff.table || '') + '`';
            const messages = {
                TABLE_ADDED: object + ' cədvəli yalnız hədəf bazada mövcuddur.',
                TABLE_REMOVED: object + ' cədvəli hədəf bazada mövcud deyil.',
                TABLE_MODIFIED: object + ' cədvəlinin quruluşu fərqlənir.',
                COLUMN_ADDED: object + ' sütunu ' + table + ' cədvəlində yalnız hədəf bazada mövcuddur.',
                COLUMN_REMOVED: object + ' sütunu ' + table + ' cədvəlində hədəf bazada yoxdur.',
                COLUMN_MODIFIED: object + ' sütununun parametrləri fərqlənir.',
                INDEX_ADDED: object + ' indeksi ' + table + ' cədvəlində yalnız hədəf bazada mövcuddur.',
                INDEX_REMOVED: object + ' indeksi ' + table + ' cədvəlində hədəf bazada yoxdur.',
                INDEX_MODIFIED: object + ' indeksinin parametrləri fərqlənir.',
                FOREIGN_KEY_ADDED: object + ' xarici açarı ' + table + ' cədvəlində yalnız hədəf bazada mövcuddur.',
                FOREIGN_KEY_REMOVED: object + ' xarici açarı ' + table + ' cədvəlində hədəf bazada yoxdur.',
                FOREIGN_KEY_MODIFIED: object + ' xarici açarının parametrləri fərqlənir.',
            };
            return messages[diff.type] || diff.message || '';
        },

        prettyAttribute(attribute) {
            const labels = {
                type_definition: 'Məlumat tipi',
                type: 'Məlumat tipi',
                length: 'Uzunluq',
                precision: 'Dəqiqlik',
                scale: 'Miqyas',
                nullable: 'Boş ola bilər',
                default: 'Standart dəyər',
                auto_increment: 'Avtomatik artım',
                unsigned: 'İşarəsiz',
                comment: 'Şərh',
                generated: 'Hesablanan sütun',
                columns: 'Sütunlar',
                unique: 'Unikallıq',
                primary: 'Əsas açar',
                referenced_table: 'Əlaqəli cədvəl',
                referenced_columns: 'Əlaqəli sütunlar',
                on_delete: 'Silmə davranışı',
                on_update: 'Yeniləmə davranışı',
                engine: 'Mühərrik',
                charset: 'Simvol dəsti',
                collation: 'Çeşidləmə qaydası',
            };
            return labels[attribute] || String(attribute || '').replace(/_/g, ' ');
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
                    this.error = data.error || 'Müqayisə zamanı xəta baş verdi.';
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
                this.error = 'SchemaLens-ə qoşulmaq mümkün olmadı. Giriş etdiyinizi və marşrutun əlçatan olduğunu yoxlayın.';
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
                return '<span class="sl-diff-missing">MÖVCUD DEYİL</span>';
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
                if (side.auto_increment) lines.push({ key: 'auto_increment', label: 'Avtomatik artım' });
                if (side.comment) lines.push({ key: 'comment', label: 'Şərh: ' + side.comment });

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
                this.error = 'SQL mətnini mübadilə buferinə köçürmək mümkün olmadı.';
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
                    this.error = data.error || 'Migrasiya faylını yaratmaq mümkün olmadı.';
                    return;
                }
                this.migrationMessage = 'Migrasiya faylı yaradıldı. İşə salmazdan əvvəl diqqətlə yoxlayın.' + (data.path ? ' → ' + data.path : '');
            } catch (e) {
                this.error = 'Migrasiya faylını yaratmaq mümkün olmadı.';
            } finally {
                this.migrating = false;
            }
        },
    };
}
