@php
    $action = $journey['action'];
    $target = match ($action['type']) {
        'lesson' => route('education.lessons.show', $action['id']),
        'assignment' => route('homework.show', $action['id']),
        'submission' => route('homework.submission', $action['id']),
        'revision' => route('learning.index'),
        default => route('quran.surahs'),
    };
@endphp
<section class="glass-panel mb-6 p-5 sm:p-7" aria-label="{{ __('Bugungi o‘quv yo‘lingiz') }}">
    <div class="welcome-eyebrow">{{ __('Bugungi o‘quv yo‘lingiz') }}</div>
    <ol class="journey-steps" aria-label="{{ __('Ta’lim bosqichlari') }}">@foreach ($journey['steps'] as $step)<li class="journey-step {{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif><span aria-hidden="true">{{ $step['state'] === 'done' ? '✓' : $loop->iteration }}</span><span>{{ __($step['label']) }}<small>{{ __(['done' => 'Belgilangan', 'current' => 'Hozirgi qadam', 'pending' => 'Keyingi bosqich'][$step['state']]) }}</small></span></li>@endforeach</ol>
    <h2 class="mt-6 text-2xl font-bold text-forest">{{ __($journey['title']) }}</h2>
    <p class="mt-3 max-w-2xl text-sm leading-6 text-graysoft">{{ __($journey['description']) }}</p>
    @if ($journey['assignment'])<p class="mt-4 text-sm font-semibold">{{ $journey['assignment']['title'] }}</p>@endif
    <a href="{{ $target }}" class="mt-5 inline-flex items-center gap-4 rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __($action['label']) }} <x-icon name="arrow"/></a>
    <div class="mt-6 grid gap-3 sm:grid-cols-3"><a href="{{ route('homework.index') }}" class="quick-link !mt-0"><x-icon name="book"/><span>{{ __('Mening vazifalarim') }}<strong class="ml-2">{{ $journey['counts']['pending_assignments'] }}</strong></span></a><a href="{{ route('homework.index') }}" class="quick-link !mt-0"><x-icon name="check"/><span>{{ __('Ustoz tekshiruvida') }}<strong class="ml-2">{{ $journey['counts']['waiting_reviews'] }}</strong></span></a><a href="{{ route('learning.index') }}" class="quick-link !mt-0"><x-icon name="calendar"/><span>{{ __('Bugun takrorlash') }}<strong class="ml-2">{{ $journey['counts']['due_revision'] }}</strong></span></a></div>
    @if ($journey['today_lesson'])<p class="mt-5 text-xs text-graysoft">{{ __('Bugungi dars') }}: {{ $journey['today_lesson']['start_time'] }}–{{ $journey['today_lesson']['end_time'] }} · {{ $journey['today_lesson']['group'] }} @if ($journey['today_lesson']['title']) · {{ $journey['today_lesson']['title'] }} @endif</p>@endif
</section>
