@extends('layouts.app')

@section('title', $rule->name_uz.' — '.__('Tajvid'))

@section('content')
    <a href="{{ route('tajweed.index') }}" class="text-sm text-graysoft hover:text-forest">← {{ __('Tajvid akademiyasi') }}</a>

    <section class="glass-panel mt-4 p-7">
        <p class="text-[10px] font-bold uppercase tracking-widest text-gold">{{ $rule->code }}</p>
        <h1 class="mt-1 text-2xl font-extrabold text-emerald-deep">{{ $rule->name_uz }}</h1>
        <div class="mt-4 rounded-xl bg-cream p-5">
            <p class="text-[15px] leading-relaxed text-gray-700">{{ $rule->description }}</p>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <a href="{{ route('quran.surahs') }}" class="feature-link"><x-icon name="book"/><span><strong>{{ __("Qur'on'da qidirib ko'rish") }}</strong><small>{{ __('Misollar bilan mashq qiling') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
            <a href="{{ route('ai.voice') }}" class="feature-link"><x-icon name="mic"/><span><strong>{{ __('AI Ustoz bilan mashq') }}</strong><small>{{ __('Talaffuzni tekshirtiring') }}</small></span><x-icon name="arrow" class="feature-arrow"/></a>
        </div>
    </section>
@endsection
