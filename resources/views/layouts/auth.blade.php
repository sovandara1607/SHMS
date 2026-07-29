<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (JSON.parse(localStorage.getItem('sh_dark_mode') ?? 'false')) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
</head>
<body class="texture-paper min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
<x-loading-overlay />
@yield('content')
</body>
</html>
