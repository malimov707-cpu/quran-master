@extends('layouts.app')

@section('title', __('Obuna rejalar'))

@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Obuna rejalar') }}</h1>
    <p class="mt-1 text-sm text-graysoft">
        {{ $isParent
            ? __('Farzandingiz uchun rejani tanlab, kassada to‘lovni amalga oshiring.')
            : __('Qur’on AI Ustoz imkoniyatlarini kengaytiring — AI cheklovsiz, chuqur tahlil va sertifikatlar.') }}
    </p>

    @if (session('status'))
        <div class="glass-panel mt-4 p-4 text-sm text-forest">{{ session('status') }}</div>
    @endif

    {{-- Kim uchun obuna holati --}}
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach ($targets as $target)
            @php $state = $states[$target->id]; @endphp
            <div class="glass-panel flex items-center justify-between p-5">
                <div class="flex items-center gap-3">
                    <span class="avatar avatar-sq">{{ mb_strtoupper(mb_substr($state['name'], 0, 1)) }}</span>
                    <div>
                        <p class="font-semibold text-forest">{{ $state['name'] }}</p>
                        <p class="text-xs text-graysoft">
                            @if ($state['active'])
                                {{ $state['active']->plan->name }} · {{ $state['active']->expires_at->format('d.m.Y') }}
                            @elseif ($state['pending'])
                                {{ __('Ariza ko‘rib chiqilmoqda') }}
                            @else
                                {{ __('Bepul rejada') }}
                            @endif
                        </p>
                    </div>
                </div>
                <span class="pill-active {{ $state['active'] ? 'bg-emerald-100 !text-emerald-800' : '' }}">
                    {{ $state['active'] ? __('Premium') : ($state['pending'] ? __('Kutmoqda') : __('Bepul')) }}
                </span>
            </div>
        @endforeach
    </div>

    {{-- Reja kartalari --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        @foreach ($plans as $plan)
            <article class="glass-panel relative flex flex-col p-6 pt-8 {{ $plan->months === 3 ? 'ring-2 ring-gold' : '' }}">
                @if ($plan->months === 3)
                    <span class="absolute -top-3 right-4 rounded-full bg-gold px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-forest shadow-sm">{{ __('Eng foydali') }}</span>
                @endif
                <h2 class="text-lg font-extrabold text-emerald-deep">{{ $plan->name }}</h2>
                <p class="mt-2 text-3xl font-extrabold text-forest">
                    @if ($plan->price_som > 0) {{ number_format($plan->price_som) }} {{ __('so’m') }}<span class="text-sm font-normal text-graysoft">/{{ __('oy') }}</span>
                    @else {{ __('Bepul') }}
                    @endif
                </p>
                <ul class="mt-4 flex-1 space-y-2 text-sm text-gray-600">
                    @foreach (array_filter(explode("\n", (string) $plan->benefits_uz)) as $benefit)
                        <li class="flex items-start gap-2"><span class="mt-0.5 text-gold" aria-hidden="true">✓</span>{{ $benefit }}</li>
                    @endforeach
                </ul>

                @if ($plan->price_som > 0)
                    <form method="POST" action="{{ route('subscriptions.cash') }}" class="mt-5 space-y-2">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        @if ($isParent)
                            <label class="sr-only" for="student-{{ $plan->id }}">{{ __('Farzand') }}</label>
                            <select id="student-{{ $plan->id }}" name="student_id" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
                                @foreach ($targets as $target)
                                    <option value="{{ $target->id }}" @if ($states[$target->id]['active']) disabled @endif>
                                        {{ $states[$target->id]['name'] }}{{ $states[$target->id]['active'] ? ' ✓' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <button type="submit" class="w-full rounded-xl bg-emerald-deep py-3 text-sm font-bold text-cream transition hover:bg-forest">
                            {{ __('Kassa orqali olish') }}
                        </button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>

    <p class="mt-6 rounded-xl bg-cream p-4 text-xs text-graysoft">{{ __('Online to‘lov (Payme/Click) integratsiyasi sozlangach shu yerda avtomatik paydo bo‘ladi. Hozircha markaz kassasi orqali obuna bo‘lish mumkin.') }}</p>
@endsection
