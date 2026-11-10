@extends('layouts.app')

@section('title', __('Qur\'on'))

@section('content')
    <div class="flex items-center gap-3">
        <x-icon3d variant="navy" :size="44" class="icon3d-float"><x-art3d name="moon" class="w-8 h-8"/></x-icon3d>
        <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Qur\'on — Suralar') }}</h1>
    </div>

    <p class="mt-2 text-sm text-graysoft">{{ __('Ishonchli matn. Qulay o‘qish. Har kuni yangi qadam.') }}</p>
    <form method="GET" class="mt-5 flex gap-3"><label class="min-w-0 flex-1"><span class="sr-only">{{ __('Sura nomi yoki raqami') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('Sura nomi yoki raqami') }}" class="w-full"></label><button type="submit" class="rounded-xl bg-forest px-5 text-white">{{ __('Qidirish') }}</button></form>
    @if ($surahs->isEmpty())<div class="glass-panel mt-6 p-8 text-center"><p>{{ __('Sura topilmadi.') }}</p><a href="{{ route('quran.surahs') }}" class="mt-3 inline-block text-sm text-forest">{{ __('Barcha suralar →') }}</a></div>@endif
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($surahs as $s)
            <a href="{{ route('quran.surah', $s) }}"
               class="flex items-center justify-between rounded-xl bg-ivory p-4 shadow-sm ring-1 ring-black/5 transition hover:shadow-md">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-deep text-xs font-bold text-gold">{{ $s->number }}</span>
                    <div>
                        <p class="font-semibold text-emerald-deep">{{ $s->name_uz }}</p>
                        <p class="text-xs text-graysoft">{{ $s->ayah_count }} oyat · {{ $s->revelation_type === 'meccan' ? 'Makka' : 'Madina' }}</p>
                    </div>
                </div>
                <span lang="ar" dir="rtl" class="ayah-text text-2xl">{{ $s->name_arabic }}</span>
            </a>
        @endforeach
    </div>
    @include('quran.source-notice')
@endsection
