@extends('layouts.app')

@section('title', __('Farzandlarim'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Farzandlarim') }}</h1>
            <p class="mt-1 text-sm text-graysoft">{{ __('Farzandingizning baholari, davomati va haftalik natijalari.') }}</p>
        </div>
        @if ($children->isNotEmpty())
            <form method="GET" action="{{ route('parents.dashboard') }}" class="flex flex-wrap items-center gap-3">
                <label for="student" class="text-sm font-medium">{{ __('Farzand') }}</label>
                <select id="student" name="student" class="rounded-lg border border-gray-200 bg-ivory px-3 py-2">
                    @foreach ($children as $child)
                        <option value="{{ $child->id }}" @selected($selectedChild?->id === $child->id)>{{ $child->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-emerald-deep px-4 py-2 text-sm font-semibold text-cream">{{ __('Ko‘rsatish') }}</button>
            </form>
        @endif
    </div>

    @if ($selectedChild && $progress)
        @include('education.quiz-progress', ['quizProgress' => $progress['quizzes']])
        <h2 class="mb-4 text-xl font-semibold text-emerald-deep">{{ $selectedChild->name }}</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-graysoft">{{ __('O‘rtacha ball') }}</p>
                <p class="mt-2 text-3xl font-bold text-gold">{{ $progress['average_score'] ?? '—' }}</p>
            </div>
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-graysoft">{{ __('Baholangan topshirishlar') }}</p>
                <p class="mt-2 text-3xl font-bold text-gold">{{ $progress['submissions_graded'] }}</p>
                <p class="mt-1 text-xs text-graysoft">Jami: {{ $progress['submissions_total'] }}</p>
            </div>
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <p class="text-sm text-graysoft">{{ __('Davomat') }}</p>
                <p class="mt-2 text-3xl font-bold text-gold">{{ $progress['attendance']['attendance_rate'] !== null ? $progress['attendance']['attendance_rate'].'%' : '—' }}</p>
                <p class="mt-1 text-xs text-graysoft">{{ $progress['attendance']['present'] }} kelgan · {{ $progress['attendance']['late'] }} kechikkan · {{ $progress['attendance']['absent'] }} kelmagan</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="mb-4 font-semibold text-emerald-deep">{{ __('Suralar bo‘yicha') }}</h2>
                @forelse ($progress['surahs'] as $surah)
                    <div class="flex justify-between gap-3 border-b border-gray-100 py-3 text-sm">
                        <span>Sura {{ $surah['surah_number'] }}</span>
                        <span>{{ $surah['average_score'] }} ball · {{ $surah['submissions_count'] }} ta</span>
                    </div>
                @empty
                    <p class="text-sm text-graysoft">{{ __('Hali baholangan topshirish yo‘q.') }}</p>
                @endforelse
            </section>
            <section class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h2 class="mb-4 font-semibold text-emerald-deep">{{ __('Haftalik natijalar') }}</h2>
                @forelse ($progress['weekly'] as $week)
                    <div class="flex justify-between gap-3 border-b border-gray-100 py-3 text-sm">
                        <span>{{ $week['week'] }}</span>
                        <span>{{ round($week['avg_score'], 1) }} ball · {{ $week['count'] }} ta</span>
                    </div>
                @empty
                    <p class="text-sm text-graysoft">{{ __('Oxirgi 4 haftada baholangan topshirish yo‘q.') }}</p>
                @endforelse
            </section>
        </div>
    @else
        <p class="rounded-2xl bg-ivory p-6 text-graysoft shadow-sm ring-1 ring-black/5">{{ __('Akkauntingizga farzand biriktirilmagan. Markaz administratoriga murojaat qiling.') }}</p>
    @endif
@endsection
