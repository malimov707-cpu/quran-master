@extends('layouts.app')

@section('title', __('Bosh sahifa'))

@section('content')
    @include('components.prayer-times')

    {{-- ====== PREMIUM HERO BANNER ====== --}}
    <section class="hero-banner" aria-label="{{ __('Xush kelibsiz') }}">
        <div class="hero-body">
            <span class="hero-badge"><span class="hero-badge-dot" aria-hidden="true"></span> {{ __("Qur'on AI Ustoz") }}</span>
            <h1 class="hero-title">{{ __('Assalomu alaykum!') }} {{ $user->name }}</h1>
            <p class="hero-subtitle">{{ $isAdministration
                ? __('Ta’lim markazingizning bugungi holati — bir joyda, real ma’lumotlar bilan.')
                : __('Zamonaviy texnologiyalar yordamida Qur’on o‘qishni o‘rganing va takomillashtiring.') }}</p>
            <a href="{{ route('quran.surahs') }}" class="hero-cta">{{ __('Boshlash') }} <span aria-hidden="true">→</span></a>
        </div>
        <aside class="hero-goal glass-panel" aria-label="{{ __('Kunlik maqsad') }}">
            <div class="hero-goal-head">
                <span class="hero-goal-icon" aria-hidden="true">◎</span>
                <strong>{{ __('Kunlik maqsad') }}</strong>
            </div>
            @if ($user->hasRole('STUDENT'))
                @php $goalMinutes = 20; $done = min($studentKpis['minutes'] ?? 0, $goalMinutes); @endphp
                <p class="hero-goal-value">{{ __(':min daqiqa', ['min' => $goalMinutes]) }}</p>
                <div class="hero-goal-bar" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemin="0" aria-valuemax="{{ $goalMinutes }}">
                    <span style="width: {{ (int) round($done / $goalMinutes * 100) }}%"></span>
                </div>
                <p class="hero-goal-note">{{ $done }} / {{ $goalMinutes }} {{ __('daqiqa') }}</p>
            @else
                <p class="hero-goal-value">{{ __('Faol guruhlar') }}</p>
                <div class="hero-goal-bar" role="progressbar" aria-valuenow="{{ $cards[0]['value'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"><span style="width: 100%"></span></div>
                <p class="hero-goal-note">{{ __('Real vaqtda kuzatiladi') }}</p>
            @endif
            <a href="{{ $user->hasRole('STUDENT') ? route('homework.index') : route('education.groups') }}" class="hero-goal-btn">{{ __('Davom ettirish') }} <span aria-hidden="true">→</span></a>
        </aside>
    </section>

    {{-- ====== KPI CARDS (real DB) ====== --}}
    @if ($user->hasRole('STUDENT') && $studentKpis)
        <section class="kpi-grid" aria-label="{{ __('Mening ko‘rsatkichlarim') }}">
            <article class="kpi-card glass-panel">
                <span class="kpi-icon kpi-gold" aria-hidden="true">📖</span>
                <div><p class="kpi-label">{{ __("O'qilgan oyatlar") }}</p><p class="kpi-value">{{ number_format($studentKpis['ayahs']) }}</p></div>
            </article>
            <article class="kpi-card glass-panel">
                <span class="kpi-icon kpi-green" aria-hidden="true">🕐</span>
                <div><p class="kpi-label">{{ __('Bugungi vaqt') }}</p><p class="kpi-value">{{ number_format($studentKpis['minutes'], 0) }} {{ __('daq') }}</p></div>
            </article>
            <article class="kpi-card glass-panel">
                <span class="kpi-icon kpi-fire" aria-hidden="true">🔥</span>
                <div><p class="kpi-label">{{ __('Ketma-ket kun') }}</p><p class="kpi-value">{{ $studentKpis['streak'] }} {{ __('kun') }}</p></div>
            </article>
            <article class="kpi-card glass-panel">
                <span class="kpi-icon kpi-trophy" aria-hidden="true">🏆</span>
                <div><p class="kpi-label">{{ __('Yutuqlar') }}</p><p class="kpi-value">{{ $studentKpis['achievements'] }} ta</p></div>
            </article>
        </section>
    @else
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

    {{-- ====== MAIN GRID ====== --}}
    <div class="mt-6 grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <div class="space-y-4 min-w-0">
            @if ($user->hasRole('STUDENT') && $studentKpis)
                {{-- Rivojlanish grafigi --}}
                <section class="glass-panel p-6" aria-labelledby="progress-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="progress-heading" class="flex items-center gap-2 font-bold text-emerald-deep"><x-icon name="chart" class="w-5"/> {{ __('Mening rivojlanishim') }}</h2>
                        <span class="period-link selected">{{ __("So'nggi 30 kun") }}</span>
                    </div>
                    <svg viewBox="0 0 320 130" role="img" aria-labelledby="progress-chart-title" class="mt-5 w-full">
                        <title id="progress-chart-title">{{ __('Ball: 0 dan 100 foizgacha') }}</title>
                        @foreach ([0, 25, 50, 75, 100] as $p)
                            @php $gy = 108 - $p / 100 * 96; @endphp
                            <text x="0" y="{{ $gy + 3 }}" font-size="8" fill="#9ca3af">{{ $p }}</text>
                            <line x1="26" x2="314" y1="{{ $gy }}" y2="{{ $gy }}" stroke="var(--line)" stroke-width="1"/>
                        @endforeach
                        @php
                            $pts = collect($studentKpis['progressSeries'])->values();
                            $n = max($pts->count() - 1, 1);
                            $poly = $pts->map(fn ($pt, $i) => (30 + $i * (270 / $n)).','.($pt['pct'] === null ? null : 108 - $pt['pct'] / 100 * 96))->filter(fn ($c) => ! str_contains($c, ',null'))->implode(' ');
                        @endphp
                        @if ($poly)
                            <polygon points="30,108 {{ $poly }} {{ 30 + 270 }},108" fill="#0b624622" stroke="none"/>
                            <polyline points="{{ $poly }}" fill="none" stroke="#0b6246" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                            @foreach ($pts as $i => $pt)
                                @if ($pt['pct'] !== null)
                                    <circle cx="{{ 30 + $i * (270 / max($pts->count() - 1, 1)) }}" cy="{{ 108 - $pt['pct'] / 100 * 96 }}" r="3.5" fill="white" stroke="#0b6246" stroke-width="2"><title>{{ $pt['label'] }}: {{ $pt['pct'] }}%</title></circle>
                                @endif
                            @endforeach
                        @endif
                        @foreach ($pts as $i => $pt)
                            @if ($i === 0 || $loop->last)
                                <text x="{{ 30 + $i * (270 / max($pts->count() - 1, 1)) }}" y="126" text-anchor="middle" font-size="9" fill="#9ca3af">{{ $pt['label'] }}</text>
                            @endif
                        @endforeach
                    </svg>
                </section>

                {{-- Kurslarim --}}
                @if ($studentCourses->isNotEmpty())
                    <section class="glass-panel p-6" aria-labelledby="courses-heading">
                        <div class="flex items-center justify-between">
                            <h2 id="courses-heading" class="flex items-center gap-2 font-bold text-emerald-deep"><x-icon name="book" class="w-5"/> {{ __('Mening kurslarim') }}</h2>
                            <a href="{{ route('education.courses') }}" class="text-sm font-medium text-forest hover:underline">{{ __('Barchasi →') }}</a>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach ($studentCourses as $course)
                                <a href="{{ route('education.lessons') }}" class="course-progress-row">
                                    <span class="avatar avatar-sq" aria-hidden="true">📖</span>
                                    <span class="min-w-0 flex-1">
                                        <strong class="block truncate text-sm">{{ $course->name }}</strong>
                                        <span class="course-progress-bar"><span style="width: {{ $course->progress_pct }}%"></span></span>
                                    </span>
                                    <span class="text-right"><small class="block text-xs text-graysoft">{{ $course->progress_pct }}%</small><small class="text-[10px] text-graysoft">{{ $course->completed_lessons }}/{{ $course->lessons_count }} {{ __('dars') }}</small></span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif

            @if ($studentJourney) @include('learning.journey', ['journey' => $studentJourney]) @endif
            @if ($videoProgress) @include('education.video-progress-summary', ['videoProgress' => $videoProgress]) @endif
            @if ($quizProgress) @include('education.quiz-progress', ['quizProgress' => $quizProgress]) @endif

            {{-- Davomat statistikasi --}}
            <section class="glass-panel p-6" aria-labelledby="attendance-heading">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="attendance-heading" class="font-bold text-emerald-deep">{{ __('Davomat statistikasi') }}</h2><div class="flex gap-2 text-xs"><a href="{{ route('dashboard', ['period' => 'week']) }}" @if ($chartDays === 7) aria-current="true" @endif class="period-link {{ $chartDays === 7 ? 'selected' : '' }}">{{ __('Hafta') }}</a><a href="{{ route('dashboard', ['period' => 'month']) }}" @if ($chartDays === 30) aria-current="true" @endif class="period-link {{ $chartDays === 30 ? 'selected' : '' }}">{{ __('Oy') }}</a></div></div>
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
                        <line x1="28" x2="290" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--line)" stroke-width="1"/>
                    @endforeach
                    @foreach ($segments as $segment)
                        <polyline points="{{ implode(' ', $segment) }}" fill="none" stroke="#0b6246" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                    @endforeach
                    @foreach ($points as $point)
                        @if ($point['y'] !== null)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="white" stroke="#0b6246" stroke-width="2"><title>{{ $point['label'] }}: {{ $point['pct'] }}%</title></circle>
                        @else
                            <text x="{{ $point['x'] }}" y="110" text-anchor="middle" font-size="10" fill="#9ca3af">—</text>
                        @endif
                        @if ($chartDays === 7 || $loop->index % 5 === 0 || $loop->last)<text x="{{ $point['x'] }}" y="136" text-anchor="middle" font-size="9" fill="#9ca3af">{{ $point['label'] }}</text>@endif
                    @endforeach
                </svg>
                <p class="mt-2 text-[11px] text-gray-400">{{ __('Kelgan va kechikkan / jami qayd etilgan davomat. — yozuv kiritilmagan.') }}</p>
            </section>

            @if ($registrationChart)
                <section class="glass-panel p-6"><h2 class="font-bold text-forest">{{ __('Ro‘yxatdan o‘tishlar') }}</h2><p class="mt-1 text-xs text-graysoft">{{ __(':count kun · yangi foydalanuvchilar', ['count' => $chartDays]) }}</p>
                    @php $peak = max(collect($registrationChart)->max('total'), 1); @endphp
                    <div class="registration-chart mt-6" role="img" aria-label="{{ __('Kunlik ro‘yxatdan o‘tishlar') }}">@foreach ($registrationChart as $day)<div class="registration-column"><div class="registration-bar" style="height: {{ max($day['total'] / $peak * 120, 2) }}px" title="{{ $day['label'] }}: {{ $day['total'] }}"><span class="sr-only">{{ $day['label'] }}: {{ $day['total'] }}</span></div>@if ($chartDays === 7 || $loop->index % 5 === 0)<small>{{ $day['label'] }}</small>@endif</div>@endforeach</div>
                </section>
            @endif
        </div>

        {{-- ====== SIDEBAR COLUMN ====== --}}
        <div class="space-y-4 min-w-0">
            {{-- AI Ustoz kartasi --}}
            <section class="ai-mentor-card" aria-labelledby="ai-mentor-heading">
                <h2 id="ai-mentor-heading">AI Ustoz<br><small>— {{ __('shaxsiy murabbiy') }}</small></h2>
                <ul class="ai-mentor-list">
                    <li>{{ __('Ovozingizni tahlil qiladi') }}</li>
                    <li>{{ __('Xatolarni aniqlaydi') }}</li>
                    <li>{{ __('Tavsiya beradi') }}</li>
                </ul>
                <a href="{{ route('ai.voice') }}" class="ai-mentor-btn">{{ __('Boshlash') }} <span aria-hidden="true">→</span></a>
            </section>

            {{-- Bugungi dars --}}
            <section class="glass-panel p-6" aria-labelledby="today-heading">
                <div class="flex items-center justify-between">
                    <h2 id="today-heading" class="font-bold text-emerald-deep">{{ __('Bugungi dars') }}</h2>
                    @if ($todaySchedules->isNotEmpty())<span class="pill-active">{{ __('Faol') }}</span>@endif
                </div>
                <p class="text-xs text-gray-400">{{ now(config('app.display_timezone', 'Asia/Tashkent'))->format('d.m.Y') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse ($todaySchedules as $i => $lesson)
                        <div class="flex items-center gap-3 rounded-xl p-3 {{ $i === 0 ? 'bg-forest text-white' : 'bg-cream' }}">
                            <div class="{{ $i === 0 ? 'text-gold' : 'text-forest' }} text-center"><p class="text-lg font-extrabold leading-none">{{ substr($lesson->start_time, 0, 5) }}</p></div>
                            <div class="h-8 w-px {{ $i === 0 ? 'bg-white/20' : 'bg-gray-200' }}"></div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold {{ $i === 0 ? 'text-white' : 'text-emerald-deep' }}">{{ $lesson->group?->name ?? 'Dars' }}</p>
                                <p class="text-[11px] {{ $i === 0 ? 'text-white/60' : 'text-gray-400' }}">{{ substr($lesson->start_time, 0, 5) }}–{{ substr($lesson->end_time, 0, 5) }} · {{ $lesson->room ?? __('Xona belgilanmagan') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-400">{{ __('Bugun sizga tegishli dars yo‘q.') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- Ayah of the day --}}
            <section class="ayah-card" aria-labelledby="ayah-heading">
                <p class="ayah-arabic" dir="rtl" lang="ar">إِنَّ مَعَ ٱلْعُسْرِ يُسْرًا</p>
                <p class="ayah-translation">«Албатта, қийинчилик билан бирга осонлик бор.»</p>
                <p class="ayah-ref">({{ __('Ash-Sharx surasi, 6-oyat') }})</p>
            </section>

            {{-- Obuna holati (student) --}}
            @if ($user->hasRole('STUDENT'))
                @php $subStatus = app(\App\Services\Payments\PaymentService::class)->activeSubscription($user) ? 'active' : 'free'; @endphp
                <section class="glass-panel p-6" aria-labelledby="sub-heading">
                    <div class="flex items-center justify-between">
                        <h2 id="sub-heading" class="font-bold text-emerald-deep">{{ __('Obuna holati') }}</h2>
                        <span class="pill-active">{{ $subStatus === 'active' ? __('Premium') : __('Bepul') }}</span>
                    </div>
                    @if ($subStatus === 'active')
                        @php $sub = app(\App\Services\Payments\PaymentService::class)->activeSubscription($user); @endphp
                        <p class="mt-3 text-sm text-graysoft">{{ __('Reja') }}: <strong>{{ $sub->plan->name }}</strong></p>
                        <p class="text-xs text-graysoft">{{ __('Muddat tugashiga :days kun qoldi', ['days' => now()->diffInDays($sub->expires_at, false)]) }}</p>
                    @else
                        <p class="mt-3 text-sm text-graysoft">{{ __('AI cheklovsiz va qo‘shimcha imkoniyatlar uchun obuna bo‘ling.') }}</p>
                    @endif
                    <a href="{{ route('subscriptions.index') }}" class="quick-link mt-4"><x-icon name="grid"/><span>{{ __('Rejalarni ko‘rish') }}</span><x-icon name="arrow"/></a>
                </section>
            @endif

            {{-- So'nggi faoliyat --}}
            <section class="glass-panel p-6"><h2 class="font-bold text-forest">{{ __('So‘nggi faoliyat') }}</h2><div class="mt-4 space-y-3">@forelse ($recentActivity as $activity)<a href="{{ route('notifications.index') }}" class="flex items-center gap-3 border-b border-gray-200 pb-3"><span class="avatar"><x-icon name="bell"/></span><span class="min-w-0"><strong class="block text-sm">{{ $activity->title }}</strong><time class="text-xs text-graysoft">{{ $activity->created_at->format('d.m H:i') }}</time></span></a>@empty<p class="py-6 text-sm text-graysoft">{{ __('Sizga tegishli yangi faoliyat yo‘q.') }}</p>@endforelse</div></section>

            @if ($newUsers->isNotEmpty())<section class="glass-panel p-6"><div class="flex items-center justify-between gap-3"><h2 class="font-bold text-forest">{{ __('Yangi foydalanuvchilar') }}</h2>@if ($isAdministration)<a href="{{ route('administration', 'users') }}" class="text-xs text-forest">{{ __('Barchasi →') }}</a>@endif</div><div class="mt-4 space-y-3">@foreach ($newUsers as $member)<div class="flex items-center gap-3"><span class="avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span><span><strong class="block text-sm">{{ $member->name }}</strong><small class="text-graysoft">{{ $member->roles->pluck('label')->join(', ') }} · {{ $member->created_at->format('d.m.Y') }}</small></span></div>@endforeach</div></section>@endif

            {{-- Teacher/Mentor tezkor paneli — o'z funksiyalari bir joyda --}}
            @if ($user->hasRole('HEAD_TEACHER'))
                <section class="glass-panel p-6" aria-label="{{ __('Ustoz paneli') }}">
                    <h2 class="font-bold text-forest">{{ __('Ustoz paneli') }}</h2>
                    <div class="mt-4 grid gap-3">
                        <a href="{{ route('education.lessons') }}" class="quick-link"><x-icon name="book"/><span>{{ __('Darslarni boshqarish') }}</span><x-icon name="arrow"/></a>
                        <a href="{{ route('assignments.manage') }}" class="quick-link"><x-icon name="check"/><span>{{ __('Topshiriqlar') }}</span><x-icon name="arrow"/></a>
                        <a href="{{ route('education.schedules') }}" class="quick-link"><x-icon name="calendar"/><span>{{ __('Jadval va davomat') }}</span><x-icon name="arrow"/></a>
                    </div>
                </section>
            @elseif ($user->hasRole('MENTOR'))
                <section class="glass-panel p-6" aria-label="{{ __('Mentor paneli') }}">
                    <h2 class="font-bold text-forest">{{ __('Mentor paneli') }}</h2>
                    <div class="mt-4 grid gap-3">
                        <a href="{{ route('mentor.submissions') }}" class="quick-link"><x-icon name="check"/><span>{{ __('Tekshirish kutayotganlar') }}</span><x-icon name="arrow"/></a>
                        <a href="{{ route('education.groups') }}" class="quick-link"><x-icon name="users"/><span>{{ __('Biriktirilgan guruhlar') }}</span><x-icon name="arrow"/></a>
                        <a href="{{ route('tajweed.index') }}" class="quick-link"><x-icon name="book"/><span>{{ __('Tajvid qo‘llanmasi') }}</span><x-icon name="arrow"/></a>
                    </div>
                </section>
            @endif

            @if ($teachingGroups->isNotEmpty())
                <section class="glass-panel p-6"><h2 class="font-bold text-forest">{{ __('Guruhlar yuklamasi') }}</h2><div class="mt-4 space-y-3">@foreach ($teachingGroups as $group)<article class="rounded-xl bg-cream p-4"><h3 class="font-semibold text-forest">{{ $group->name }}</h3><div class="mt-2 flex items-center justify-between text-xs"><span>{{ __('Faol o‘quvchilar') }}</span><strong>{{ $group->students_count }} / {{ $group->capacity }}</strong></div><meter min="0" max="{{ max($group->capacity, $group->students_count, 1) }}" value="{{ $group->students_count }}" class="mt-2 w-full" aria-label="{{ __('Guruh sig‘imi') }}"></meter></article>@endforeach</div></section>
            @endif
        </div>
    </div>

    {{-- Tezkor amallar --}}
    <section class="mt-6 grid gap-4 lg:grid-cols-3" aria-label="{{ __('Tezkor amallar') }}">
        <a href="{{ route('quran.surahs') }}" class="feature-link"><x-icon name="book"/><span><strong>{{ __("Qur'on o‘qishni boshlang") }}</strong><small>{{ __('Suralar va oyatlar kutubxonasi') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
        @if ($user->hasPermission('lessons.view'))
            <a href="{{ route('education.lessons') }}" class="feature-link"><x-icon name="book"/><span><strong>{{ __('Darslarni ko‘rish') }}</strong><small>{{ __('Bilimingizni boyiting') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
        @endif
        <a href="{{ route('notifications.index') }}" class="feature-link"><x-icon name="bell"/><span><strong>{{ __('Bildirishnomalar') }}</strong><small>{{ __('Sizga tegishli yangiliklar') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
    </section>
@endsection
