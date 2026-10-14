<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'RUDIMAH') }}</title>

    <!-- Inisialisasi Tema (Dark / Light Mode) Tanpa Flash -->
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('rudimah_theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (theme === 'dark' || (!theme && prefersDark)) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.documentElement.style.colorScheme = 'light';
                }
            } catch (e) {}
        })();
    </script>

    <!-- Font Family Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @php
        $manifestPath = public_path('build/.vite/manifest.json');
        if (!file_exists($manifestPath)) {
            $manifestPath = public_path('build/manifest.json');
        }
        $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
        $appEntry = $manifest['resources/js/app.ts'] ?? $manifest['resources/js/app.js'] ?? null;
        if (!$appEntry && !empty($manifest)) {
            foreach ($manifest as $entry) {
                if (!empty($entry['isEntry'])) {
                    $appEntry = $entry;
                    break;
                }
            }
        }
    @endphp

    @if ($appEntry)
        @if (!empty($appEntry['css']))
            @foreach ($appEntry['css'] as $cssFile)
                <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}">
            @endforeach
        @endif
        <script type="module" src="{{ asset('build/' . $appEntry['file']) }}"></script>
    @else
        <!-- Fallback aman tanpa throw exception bila manifest sedang dimuat -->
        <link rel="stylesheet" href="{{ asset('resources/css/app.css') }}">
        <script type="module" src="{{ asset('resources/js/app.ts') }}"></script>
    @endif

    @inertiaHead
</head>
<body class="h-full font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    @inertia
</body>
</html>
