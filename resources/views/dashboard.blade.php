@extends('layouts.app')

@section('title', __('Bosh sahifa'))

@section('content')
    <section class="welcome-panel glass-panel">
        <div class="welcome-eyebrow">{{ __($isAdministration ? 'Boshqaruv markazi' : 'Shaxsiy ta’lim maydoni') }}</div>
        <h1>{{ __('Assalomu alaykum!') }}</h1>
        <p>{{ $user->name }}, {{ __('Qur’on AI Ustoz platformasiga xush kelibsiz.') }}</p>
        <div class="welcome-divider"></div>
        <p class="!text-xs text-graysoft">{{ __($isAdministration ? 'Ta’lim markazingizning bugungi holati — bir joyda.' : 'Ilm sari har bir kichik qadam qadrlidir.') }}</p>
    </section>
    @include('components.prayer-times')
    @if ($quizProgress) @include('education.quiz-progress', ['quizProgress' => $quizProgress]) @endif
    @if ($studentJourney) @include('learning.journey', ['journey' => $studentJourney]) @endif
    @if (! $studentJourney)
    <div class="grid gap-4 grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $i => $card)
            <article class="metric-card glass-panel">
                <x-icon :name="['users', 'book', 'chart', 'calendar'][$i % 4]" class="metric-icon"/>
                @if (isset($card['trend']))<p class="metric-trend">↗ {{ $card['trend'] }}</p>@endif
                <p class="metric-value">{{ $card['value'] }}</p>
                <h2 class="metric-label">{{ __($card['title']) }}</h2>
                <p class="metric-note">{{ __($card['description']) }}</p>
            </article>
        @endforeach
    </div>

    @endif
    <div class="mt-6 grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold text-emerald-deep">{{ __('Davomat statistikasi') }}</h2><div class="flex gap-2 text-xs"><a href="{{ route('dashboard', ['period' => 'week']) }}" @if ($chartDays === 7) aria-current="true" @endif class="period-link {{ $chartDays === 7 ? 'selected' : '' }}">{{ __('Hafta') }}</a><a href="{{ route('dashboard', ['period' => 'month']) }}" @if ($chartDays === 30) aria-current="true" @endif class="period-link {{ $chartDays === 30 ? 'selected' : '' }}">{{ __('Oy') }}</a></div></div>
            <p class="text-xs text-gray-400">{{ __('Dars sanasi bo‘yicha · :count kun', ['count' => $chartDays]) }}</p>
            @php
                $points = collect($attendanceChart)->map(fn ($day, $index) => [
                    'x' => 36 + $index * (240 / max(count($attendanceChart) - 1, 1)),
                    'y' => $day['pct'] === null ? null : 114 - $day['pct'] / 100 * 108,
                    'label' => $day['label'], 'pct' => $day['pct'],
                ]);
                $segments = []; $segment = [];
                foreach ($points as $point) {
                    if ($point['y'] === null) {
                        if ($segment) { $segments[] = $segment; $segment = []; }
                    } else { $segment[] = $point['x'].','.$point['y']; }
                }
                if ($segment) { $segments[] = $segment; }
            @endphp
            <svg viewBox="0 0 300 142" role="img" aria-labelledby="attendance-chart-title" class="mt-5 w-full">
                <title id="attendance-chart-title">{{ __('Davomat: 0 dan 100 foizgacha') }}</title>
                @foreach ([0, 25, 50, 75, 100] as $percentage)
                    @php $y = 114 - $percentage / 100 * 108; @endphp
                    <text x="0" y="{{ $y + 3 }}" font-size="8" fill="#9ca3af">{{ $percentage }}</text>
                    <line x1="28" x2="290" y1="{{ $y }}" y2="{{ $y }}" stroke="#eef2ec" stroke-width="1"/>
                @endforeach
                @foreach ($segments as $segment)
                    <polyline points="{{ implode(' ', $segment) }}" fill="none" stroke="#14532d" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                @endforeach
                @foreach ($points as $point)
                    @if ($point['y'] !== null)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="white" stroke="#14532d" stroke-width="2"><title>{{ $point['label'] }}: {{ $point['pct'] }}%</title></circle>
                    @else
                        <text x="{{ $point['x'] }}" y="110" text-anchor="middle" font-size="10" fill="#9ca3af">—</text>
                    @endif
                    @if ($chartDays === 7 || $loop->index % 5 === 0 || $loop->last)<text x="{{ $point['x'] }}" y="136" text-anchor="middle" font-size="9" fill="#9ca3af">{{ $point['label'] }}</text>@endif
                @endforeach
            </svg>
            <p class="mt-2 text-[11px] text-gray-400">{{ __('Kelgan va kechikkan / jami qayd etilgan davomat. — yozuv kiritilmagan.') }}</p>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-emerald-deep">{{ __('Bugungi jadval') }}</h2>
                @if ($canViewSchedules)<a href="{{ route('education.schedules') }}" class="text-sm font-medium text-forest hover:underline">{{ __('Barchasi →') }}</a>@endif
            </div>
            <div class="mt-4 space-y-2">
                @forelse ($todaySchedules as $i => $lesson)
                    <div class="flex items-center gap-3 rounded-xl p-3 {{ $i === 0 ? 'bg-forest text-white' : 'bg-cream' }}">
                        <div class="{{ $i === 0 ? 'text-gold' : 'text-forest' }} text-center"><p class="text-lg font-extrabold leading-none">{{ substr($lesson->start_time, 0, 5) }}</p></div>
                        <div class="h-8 w-px {{ $i === 0 ? 'bg-white/20' : 'bg-gray-200' }}"></div>
                        <div>
                            <p class="text-sm font-semibold {{ $i === 0 ? 'text-white' : 'text-emerald-deep' }}">{{ $lesson->group?->name ?? 'Dars' }}</p>
                            <p class="text-[11px] {{ $i === 0 ? 'text-white/60' : 'text-gray-400' }}">{{ substr($lesson->start_time, 0, 5) }}–{{ substr($lesson->end_time, 0, 5) }} · {{ $lesson->room ?? 'Xona belgilanmagan' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-gray-400">{{ __('Bugun sizga tegishli dars yo‘q.') }}</p>
                @endforelse
            </div>
        </section>
    </div>
    @if ($teachingGroups->isNotEmpty())
        <section class="glass-panel mt-6 p-6"><h2 class="font-bold text-forest">{{ __('Guruhlar va mentorlar yuklamasi') }}</h2><div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach ($teachingGroups as $group)<article class="rounded-xl bg-cream p-4"><h3 class="font-semibold text-forest">{{ $group->name }}</h3><p class="mt-2 text-xs text-graysoft">{{ $group->mentor?->name ?? '—' }}</p><div class="mt-4 flex items-center justify-between text-xs"><span>{{ __('Faol o‘quvchilar') }}</span><strong>{{ $group->students_count }} / {{ $group->capacity }}</strong></div><meter min="0" max="{{ max($group->capacity, $group->students_count, 1) }}" value="{{ $group->students_count }}" class="mt-2 w-full" aria-label="{{ __('Guruh sig‘imi') }}"></meter></article>@endforeach</div></section>
    @endif
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @if ($registrationChart)
            <section class="glass-panel p-6"><h2 class="font-bold text-forest">{{ __('Ro‘yxatdan o‘tishlar') }}</h2><p class="mt-1 text-xs text-graysoft">{{ __(':count kun · yangi foydalanuvchilar', ['count' => $chartDays]) }}</p>
                @php $peak = max(collect($registrationChart)->max('total'), 1); @endphp
                <div class="registration-chart mt-6" role="img" aria-label="{{ __('Kunlik ro‘yxatdan o‘tishlar') }}">@foreach ($registrationChart as $day)<div class="registration-column"><div class="registration-bar" style="height: {{ max($day['total'] / $peak * 120, 2) }}px" title="{{ $day['label'] }}: {{ $day['total'] }}"><span class="sr-only">{{ $day['label'] }}: {{ $day['total'] }}</span></div>@if ($chartDays === 7 || $loop->index % 5 === 0)<small>{{ $day['label'] }}</small>@endif</div>@endforeach</div>
            </section>
        @endif
        <section class="glass-panel p-6"><h2 class="font-bold text-forest">{{ __('So‘nggi faoliyat') }}</h2><div class="mt-4 space-y-3">@forelse ($recentActivity as $activity)<a href="{{ route('notifications.index') }}" class="flex items-center gap-3 border-b border-gray-200 pb-3"><span class="avatar"><x-icon name="bell"/></span><span class="min-w-0"><strong class="block text-sm">{{ $activity->title }}</strong><time class="text-xs text-graysoft">{{ $activity->created_at->format('d.m H:i') }}</time></span></a>@empty<p class="py-6 text-sm text-graysoft">{{ __('Sizga tegishli yangi faoliyat yo‘q.') }}</p>@endforelse</div></section>
        @if ($newUsers->isNotEmpty())<section class="glass-panel p-6"><div class="flex items-center justify-between gap-3"><h2 class="font-bold text-forest">{{ __('Yangi foydalanuvchilar') }}</h2>@if ($isAdministration)<a href="{{ route('administration', 'users') }}" class="text-xs text-forest">{{ __('Barchasi →') }}</a>@endif</div><div class="mt-4 space-y-3">@foreach ($newUsers as $member)<div class="flex items-center gap-3"><span class="avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span><span><strong class="block text-sm">{{ $member->name }}</strong><small class="text-graysoft">{{ $member->roles->pluck('label')->join(', ') }} · {{ $member->created_at->format('d.m.Y') }}</small></span></div>@endforeach</div></section>@endif
        <section class="glass-panel p-6"><h2 class="font-bold text-forest">{{ __('Tezkor amallar') }}</h2>@foreach ([['route' => 'quran.surahs', 'label' => 'Suralar'], ['route' => 'notifications.index', 'label' => 'Bildirishnomalar']] as $item)@if ($item['route'] !== 'dashboard')<a href="{{ route($item['route']) }}" class="quick-link"><x-icon name="arrow"/><span>{{ __($item['label']) }}</span><x-icon name="arrow"/></a>@endif @if ($loop->iteration >= 5) @break @endif @endforeach</section>
    </div>
    <section class="mt-6 grid gap-4 lg:grid-cols-3" aria-label="{{ __('Tezkor amallar') }}">
        <a href="{{ route('quran.surahs') }}" class="feature-link"><x-icon name="book"/><span><strong>{{ __('Qur’on o‘qishni boshlang') }}</strong><small>{{ __('Suralar va oyatlar kutubxonasi') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
        @if ($user->hasPermission('lessons.view'))
            <a href="{{ route('education.lessons') }}" class="feature-link"><x-icon name="book"/><span><strong>{{ __('Darslarni ko‘rish') }}</strong><small>{{ __('Bilimingizni boyiting') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
        @endif
        <a href="{{ route('notifications.index') }}" class="feature-link"><x-icon name="bell"/><span><strong>{{ __('Bildirishnomalar') }}</strong><small>{{ __('Sizga tegishli yangiliklar') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
    </section>
@endsection
