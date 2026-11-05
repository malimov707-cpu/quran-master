@extends('layouts.app')

@section('title', 'Qur\'on')

@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">Qur'on — Suralar</h1>

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
                <span class="ayah-text text-2xl">{{ $s->name_arabic }}</span>
            </a>
        @endforeach
    </div>
@endsection
