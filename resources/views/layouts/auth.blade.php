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
            <p class="mt-8 text-center text-[10px] text-graysoft"><a href="https://commons.wikimedia.org/wiki/File:Center_of_Islamic_Civilization_of_Uzbekistan_Tashkent_2026_001.jpg" target="_blank" rel="noopener">{{ __('Fon surati') }}: Зинҳор Насрӣ</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC BY-SA 4.0</a> · {{ __('o‘lchami moslashtirilgan') }}</p>
        </div>
    </main>
</body>
</html>
