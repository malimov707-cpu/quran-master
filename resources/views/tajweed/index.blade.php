@extends('layouts.app')

@section('title', __('Tajvid akademiyasi'))

@section('content')
    {{-- Premium hero --}}
    <section class="hero-banner" style="grid-template-columns: 1fr;">
        <div class="hero-body">
            <span class="hero-badge"><span class="hero-badge-dot" aria-hidden="true"></span> {{ __('Tajvid akademiyasi') }}</span>
            <h1 class="hero-title">{{ __("To'g'ri qiroat sirlari") }}</h1>
            <p class="hero-subtitle">{{ __('Har bir qoida maxraj, madd va sifatlar bo‘yicha tushuntirilgan. Qoidani o‘rganib, mashq qilib ustoz bilan mustahkamlang.') }}</p>
        </div>
    </section>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($rules as $rule)
            <a href="{{ route('tajweed.show', $rule) }}" class="glass-panel block p-6 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gold">{{ $rule->code }}</p>
                        <h2 class="mt-1 text-lg font-extrabold text-emerald-deep">{{ $rule->name_uz }}</h2>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cream text-lg">◎</span>
                </div>
                <p class="mt-3 line-clamp-3 text-sm text-graysoft">{{ $rule->description }}</p>
                <span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-forest">{{ __('Batafsil o‘rganish') }} <span aria-hidden="true">→</span></span>
            </a>
        @endforeach
    </div>
@endsection
