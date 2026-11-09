@php
    $icons = [
        'prayer-times' => 'calendar', 'dashboard' => 'home', 'education.courses' => 'book', 'education.lessons' => 'book',
        'education.groups' => 'users', 'education.schedules' => 'calendar', 'quran.surahs' => 'book',
        'assignments.manage' => 'check', 'homework.index' => 'book', 'mentor.submissions' => 'check',
        'parents.dashboard' => 'users', 'notifications.index' => 'bell', 'ai.voice' => 'mic', 'payments.index' => 'credit-card',
    ];
    $groups = [
        'Asosiy' => ['dashboard', 'quran.surahs', 'prayer-times', 'ai.voice'],
        'Boshqaruv' => ['education.courses', 'education.lessons', 'education.groups', 'education.schedules'],
        'O\'quv jarayoni' => ['assignments.manage', 'homework.index', 'mentor.submissions', 'parents.dashboard', 'payments.index'],
        'Tizim' => ['notifications.index'],
    ];
@endphp
@foreach ($groups as $groupLabel => $routes)
    @php $groupItems = array_filter($navigation, fn ($i) => in_array($i['route'], $routes, true)); @endphp
    @if (count($groupItems) > 0)
        <p class="nav-group-label">{{ __($groupLabel) }}</p>
        @foreach ($groupItems as $item)
            <a href="{{ route($item['route']) }}"
               @if (request()->routeIs($item['route'])) aria-current="page" @endif
               class="nav-link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}" title="{{ __($item['label']) }}">
                <x-icon :name="$icons[$item['route']] ?? 'grid'"/>
                <span class="nav-label">{{ __($item['label']) }}</span>
            </a>
        @endforeach
    @endif
@endforeach

@if (auth()->user()->hasRole('SUPER_ADMIN', 'CENTER_ADMIN'))
    <p class="nav-group-label">{{ __('Markaz') }}</p>
    @foreach (['users' => ['Foydalanuvchilar', 'users'], 'roles' => ['Rollar va ruxsatlar', 'check'], 'payments' => ['To‘lovlar', 'grid'], 'ai' => ['AI xizmatlari', 'chart'], 'audit' => ['Faoliyat jurnali', 'calendar'], 'settings' => ['Tizim sozlamalari', 'grid']] as $section => $item)
        @if (($section === 'users' && auth()->user()->hasPermission('users.view')) || $section === 'payments' || auth()->user()->hasRole('SUPER_ADMIN'))
            <a href="{{ route('administration', $section) }}" title="{{ __($item[0]) }}" @if (request()->route('section') === $section) aria-current="page" @endif class="nav-link {{ request()->route('section') === $section ? 'is-active' : '' }}"><x-icon :name="$item[1]"/><span class="nav-label">{{ __($item[0]) }}</span></a>
        @endif
    @endforeach
@endif

@if (auth()->user()->hasRole('STUDENT'))<a href="{{ route('learning.index') }}" class="nav-link {{ request()->routeIs('learning.*') ? 'is-active' : '' }}"><x-icon name="calendar"/><span class="nav-label">{{ __('Shaxsiy o‘quv rejam') }}</span></a>@endif

<a href="{{ route('quran.audio') }}" class="nav-link {{ request()->routeIs('quran.audio') ? 'is-active' : '' }}"><x-icon name="book"/><span class="nav-label">{{ __('Audio kutubxona') }}</span></a>

<a href="{{ route('certificates.index') }}" class="nav-link {{ request()->routeIs('certificates.*') ? 'is-active' : '' }}"><x-icon name="check"/><span class="nav-label">{{ __('Sertifikatlar') }}</span></a>
