<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Qur\'on AI Ustoz')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen islamic-pattern">
    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md rounded-2xl bg-ivory p-8 shadow-lg ring-1 ring-black/5">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-deep text-3xl text-gold">☾</div>
                <h1 class="text-2xl font-bold text-emerald-deep">Qur'on AI Ustoz</h1>
                <p class="mt-1 text-sm text-graysoft">Ta'lim markazi platformasi</p>
            </div>
            @yield('content')
        </div>
    </main>
</body>
</html>
