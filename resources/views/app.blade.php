<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Exigence 8 — thème sombre appliqué AVANT le premier rendu (pas de flash). --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    var sombre = t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', sombre);
                    document.documentElement.style.colorScheme = sombre ? 'dark' : 'light';
                } catch (e) {}
            })();
        </script>
        <style>
            html { background-color: #f3f4f6; }
            html.dark { background-color: #0f172a; }
        </style>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased">
        @inertia
    </body>
</html>
