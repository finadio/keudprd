<!DOCTYPE html>
<html lang="id" class="min-h-full bg-slate-50 print:bg-white print:h-auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'KeuDPRD Banyumas') }}</title>
    <link rel="icon" type="image/png" href="/images/logo-banyumas-sm.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="min-h-full text-slate-800 antialiased font-sans print:h-auto print:bg-white selection:bg-blue-600 selection:text-white">
    @inertia
</body>
</html>
