<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'SIMOD Enterprise') }}</title>

    <!-- Instant Theme Detection Script -->
    <script>
        window.__SIMOD_BASE__ = @json(request()->getBasePath());
        (function() {
            try {
                if (window.location.pathname.replace(/\/+$/, '').endsWith('/login')) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    return;
                }
                const saved = localStorage.getItem('simod_theme');
                if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                } else {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <!-- Font Plus Jakarta Sans di-host sendiri (variable font, dibundel Vite via @fontsource-variable):
         tidak memblokir tampilan menunggu Google Fonts dan tidak membocorkan kunjungan ke pihak ketiga. -->

    <!-- Scripts & Styles -->
    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased min-h-screen selection:bg-amber-400 selection:text-slate-950">
    @inertia
</body>
</html>
