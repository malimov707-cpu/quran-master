<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#064E3B">
    <title>@yield('title', 'Qur\'on AI Ustoz')</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('quran-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    <a href="#main-content" class="skip-link">{{ __('Asosiy tarkibga o‘tish') }}</a>
    <button class="drawer-scrim" data-close-menu aria-label="{{ __('Menyuni yopish') }}" tabindex="-1"></button>
    <aside id="sidebar" class="app-sidebar" aria-label="{{ __('Asosiy menyu') }}">
        <a href="{{ route('dashboard') }}" class="brand"><x-brand /><span class="brand-copy"><strong>Qur’on AI Ustoz</strong><small>{{ __('Ilm va amal sari') }}</small></span></a>
        <nav class="sidebar-nav" aria-label="{{ __('Asosiy navigatsiya') }}">@include('layouts.navigation')</nav>
        <div class="sidebar-footer">
            <div class="sidebar-note"><span class="ornament">◇ ── ✧ ── ◇</span><p>{{ __('Qur’on bilan har kun') }}<br>{{ __('yangi qadam.') }}</p></div>
            <form method="POST" action="{{ route('logout') }}" data-confirm="{{ __('Tizimdan chiqasizmi?') }}">@csrf<button type="submit" class="logout-button"><x-icon name="logout"/><span class="nav-label">{{ __('Tizimdan chiqish') }}</span></button></form>
        </div>
    </aside>
    <div class="app-workspace">
        <header class="app-topbar">
            <button class="icon-button" data-menu-toggle aria-controls="sidebar" aria-expanded="false" aria-label="{{ __('Menyuni ochish yoki yig‘ish') }}"><x-icon name="menu"/></button>
            <form action="{{ route('quran.surahs') }}" method="GET" role="search" class="top-search"><x-icon name="search"/><input name="q" aria-label="{{ __('Sura qidirish') }}" placeholder="{{ __('Sura qidiring…') }}" autocomplete="off"></form>
            <div class="topbar-actions">
                <form method="POST" action="{{ route('interface.locale') }}" class="locale-form">@csrf<label class="sr-only" for="interface-locale">{{ __('Til') }}</label><select id="interface-locale" name="locale" aria-label="{{ __('Til') }}" data-locale-select><option value="uz" @selected(app()->getLocale() === 'uz')>UZ</option><option value="uz_Cyrl" @selected(app()->getLocale() === 'uz_Cyrl')>ЎЗ</option><option value="ru" @selected(app()->getLocale() === 'ru')>RU</option></select><button type="submit" class="sr-only">{{ __('Tilni almashtirish') }}</button></form>
                <button class="icon-button" data-theme-toggle aria-label="{{ __('Rang rejimini almashtirish') }}" title="{{ __('Kunduzgi / tungi rejim') }}"><x-icon name="moon"/></button>
                <a href="{{ route('notifications.index') }}" class="icon-button notification-button" aria-label="{{ __('Bildirishnomalar: :count ta o‘qilmagan', ['count' => $unreadNotificationCount]) }}"><x-icon name="bell"/>@if ($unreadNotificationCount > 0)<span class="notification-count">{{ min($unreadNotificationCount, 99) }}</span>@endif</a>
                <details class="profile-menu"><summary><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roles->pluck('label')->first() }}</small></span><span aria-hidden="true">⌄</span></summary><div class="profile-popover"><strong>{{ auth()->user()->name }}</strong><p>{{ auth()->user()->email }}</p><p>{{ auth()->user()->roles->pluck('label')->join(', ') }}</p><form method="POST" action="{{ route('logout') }}" data-confirm="{{ __('Tizimdan chiqasizmi?') }}">@csrf<button type="submit">{{ __('Tizimdan chiqish →') }}</button></form></div></details>
            </div>
        </header>
        <main id="main-content" class="app-content" tabindex="-1">
            <div class="page-context"><span>Qur’on AI Ustoz <span aria-hidden="true">/</span> @yield('title', 'Bosh sahifa')</span><time datetime="{{ now()->toDateString() }}">{{ now(config('app.display_timezone', 'Asia/Tashkent'))->format('d.m.Y') }}</time></div>
            @if ($errors && $errors->any())<div role="alert" class="feedback feedback-error"><strong>{{ __('Ma’lumotlarni tekshiring') }}</strong><p>{{ $errors->first() }}</p></div>@endif
            @if (session('status'))<div role="status" class="feedback feedback-success">{{ session('status') }}<button type="button" data-dismiss-alert aria-label="{{ __('Xabarni yopish') }}">×</button></div>@endif
            @yield('content')
            <footer class="page-footer"><span>Qur’on AI Ustoz</span><span>{{ __('Ilm. E’tibor. Taraqqiyot.') }}</span></footer>
        </main>
    </div>
    <dialog id="confirmation-dialog" class="confirmation-dialog"><form method="dialog"><h2 data-confirm-message class="text-lg font-bold"></h2><div class="mt-6 flex justify-end gap-3"><button value="cancel" class="bookmark-button">{{ __('Bekor qilish') }}</button><button value="confirm" class="confirm-accept">{{ __('Tasdiqlash') }}</button></div></form></dialog>
    @stack('scripts')
</body>
</html>
