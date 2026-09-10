<!DOCTYPE html>
<html lang="en" x-data="schemaLensTheme()" x-bind:class="{ 'dark': dark }" class="h-full">
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
                        sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                        display: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        ink: {
                            50: '#f4f7f7',
                            100: '#e3eaeb',
                            200: '#c5d3d5',
                            300: '#9bb3b7',
                            400: '#6d8e94',
                            500: '#527379',
                            600: '#455f65',
                            700: '#3b4f54',
                            800: '#344347',
                            900: '#2e3a3d',
                            950: '#1a2326',
                        },
                        accent: {
                            DEFAULT: '#0d9488',
                            soft: '#14b8a6',
                            dark: '#0f766e',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ route('schemalens.assets.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="h-full min-h-screen bg-ink-50 text-ink-900 antialiased dark:bg-ink-950 dark:text-ink-100">
    <div class="sl-bg-pattern pointer-events-none fixed inset-0 -z-10 opacity-40 dark:opacity-30"></div>

    <header class="sticky top-0 z-40 border-b border-ink-200/80 bg-ink-50/90 backdrop-blur-md dark:border-ink-800 dark:bg-ink-950/90">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent text-white shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h12M3 17h8" />
                    </svg>
                </div>
                <div>
                    <div class="font-display text-lg font-semibold tracking-tight">SchemaLens</div>
                    <div class="hidden text-xs text-ink-500 dark:text-ink-400 sm:block">See exactly what changed in your database.</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="toggle()"
                        class="inline-flex items-center gap-2 rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 transition hover:bg-ink-100 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:bg-ink-800"
                        :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'">
                    <span x-text="dark ? 'Light' : 'Dark'"></span>
                </button>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-7xl px-4 pb-8 text-center text-xs text-ink-400 sm:px-6 lg:px-8">
        SchemaLens is read-only by default. It never modifies your databases automatically.
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
