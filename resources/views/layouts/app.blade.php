<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Qur\'on AI Ustoz')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-cream">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="hidden w-64 shrink-0 flex-col bg-emerald-deep p-6 text-cream md:flex">
            <div class="mb-10 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-forest text-xl text-gold">☾</div>
                <div>
                    <p class="font-bold">Qur'on AI Ustoz</p>
                    <p class="text-xs text-cream/60">Ta'lim platformasi</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1" aria-label="Asosiy navigatsiya">
                @include('layouts.navigation')
            </nav>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-cream/80 transition hover:bg-forest/50">
                    ⏻ Chiqish
                </button>
            </form>
        </aside>

        <!-- Content -->
        <main class="min-w-0 flex-1 overflow-y-auto p-6 md:p-10">
            <details class="mb-6 rounded-xl bg-emerald-deep p-4 text-cream md:hidden">
                <summary class="cursor-pointer font-semibold">☾ Qur'on AI Ustoz — Menyu</summary>
                <nav class="mt-4 space-y-1" aria-label="Mobil navigatsiya">@include('layouts.navigation')</nav>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="rounded-lg px-3 py-2 text-sm" type="submit">Chiqish</button>
                </form>
            </details>
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif
            @if (session('status'))
                <div class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
