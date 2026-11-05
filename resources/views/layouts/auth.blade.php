<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Qur\'on AI Ustoz')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen auth-shell">
    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="auth-card glass-panel">
            <div class="mb-8 text-center">
                <x-brand class="mx-auto mb-4 h-24 w-24 text-gold"/>
                <h1 class="text-2xl font-bold text-emerald-deep">Qur'on AI Ustoz</h1>
                <p class="mt-1 text-sm text-graysoft">{{ __('Ta\'lim markazi platformasi') }}</p>
            </div>
            @yield('content')
        </div>
    </main>
</body>
</html>
