<!DOCTYPE html>
<html lang="en" x-data="schemaLensTheme()" :class="{ 'dark': dark }" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SchemaLens')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"DM Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                        display: ['"Outfit"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        canvas: {
                            DEFAULT: '#f7f8fa',
                            dark: '#0c0f14',
                        },
                        panel: {
                            DEFAULT: '#ffffff',
                            dark: '#12161e',
                        },
                        line: {
                            DEFAULT: '#e6e9ef',
                            dark: '#232a36',
                        },
                        mute: {
                            DEFAULT: '#6b7285',
                            dark: '#8b93a7',
                        },
                        brand: {
                            DEFAULT: '#2563eb',
                            soft: '#3b82f6',
                            mist: '#eff4ff',
                        },
                    },
                    boxShadow: {
                        soft: '0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px rgba(15, 23, 42, 0.04)',
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ route('schemalens.assets.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="min-h-full bg-canvas text-slate-900 antialiased dark:bg-canvas-dark dark:text-slate-100">
    <div class="sl-atmosphere" aria-hidden="true"></div>

    <header class="sticky top-0 z-40 border-b border-line/80 bg-canvas/80 backdrop-blur-xl dark:border-line-dark dark:bg-canvas-dark/80">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="sl-logo" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <div class="leading-none">
                    <div class="font-display text-[1.05rem] font-semibold tracking-tight">SchemaLens</div>
                    <div class="mt-1 hidden text-[11px] text-mute dark:text-mute-dark sm:block">See exactly what changed in your database.</div>
                </div>
            </div>

            <button type="button"
                    @click="toggle()"
                    class="sl-icon-btn"
                    :title="dark ? 'Light mode' : 'Dark mode'"
                    :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'">
                <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36l-1.42-1.42M7.05 7.05L5.64 5.64m12.72 0l-1.41 1.41M7.05 16.95l-1.41 1.41M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
            </button>
        </div>
    </header>

    <main class="relative mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-center text-[11px] text-mute dark:text-mute-dark sm:px-6">
        Read-only by default · SchemaLens never modifies databases automatically
    </footer>

    <script>
        function schemaLensTheme() {
            return {
                dark: localStorage.getItem('schemalens-theme') === 'dark'
                    || (!localStorage.getItem('schemalens-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                toggle() {
                    this.dark = !this.dark;
                    localStorage.setItem('schemalens-theme', this.dark ? 'dark' : 'light');
                }
            }
        }
    </script>
    @stack('scripts')
</body>
</html>
