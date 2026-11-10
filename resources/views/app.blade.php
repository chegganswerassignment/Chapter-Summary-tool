<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Chapter Summary Tool') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|tajawal:400,500,700,800&display=swap" rel="stylesheet" />

        <!-- Prevent flash of wrong theme/direction before React hydrates -->
        <script>
            (function () {
                try {
                    var theme = localStorage.getItem('theme');
                    var isDark = theme ? theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle('dark', isDark);

                    var locale = localStorage.getItem('locale') || document.documentElement.lang || 'en';
                    document.documentElement.dir = locale.startsWith('ar') ? 'rtl' : 'ltr';
                } catch (e) {}
            })();
        </script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        @inertia
    </body>
</html>
