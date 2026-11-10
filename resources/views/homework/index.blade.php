@extends('layouts.app')
@section('title', __('Uy vazifalarim'))
@section('content')
    <div class="flex items-center gap-3">
        <x-icon3d variant="emerald" :size="44" class="icon3d-float"><x-art3d name="mushaf" class="w-8 h-8"/></x-icon3d>
        <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Uy vazifalarim') }}</h1>
    </div>
    <p class="mt-2 text-sm text-graysoft">{{ __('Topshiriqni oching, audio yozuvingizni yuboring va mentor natijasini kuzating.') }}</p>
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse ($assignments as $assignment)
            <a href="{{ route('homework.show', $assignment) }}" class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5 hover:ring-gold">
                <h2 class="font-semibold text-emerald-deep">{{ $assignment->title }}</h2>
                <p class="mt-2 text-sm text-graysoft">{{ $assignment->group->name }}</p>
                <p class="mt-2 text-sm">Muddat: {{ $assignment->due_at?->format('d.m.Y H:i') ?? 'Belgilanmagan' }}</p>
                <p class="mt-4 text-sm font-semibold text-emerald-deep">{{ __('Topshiriqni ochish →') }}</p>
            </a>
        @empty
            <p class="rounded-xl bg-ivory p-6 text-graysoft">{{ __('Hozircha berilgan vazifa yo‘q.') }}</p>
        @endforelse
    </div>
    <h2 class="mt-10 text-xl font-semibold text-emerald-deep">{{ __('Topshirishlar tarixi') }}</h2>
    <div class="mt-4 space-y-3">
        @forelse ($submissions as $submission)
            <a href="{{ route('homework.submission', $submission) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ivory p-4 ring-1 ring-black/5">
                <div>
                    <p class="font-medium">{{ $submission->assignment->title }}</p>
                    <p class="mt-1 text-xs text-graysoft">{{ $submission->attempt }}-urinish · {{ $submission->submitted_at->format('d.m.Y H:i') }}</p>
                </div>
                <p class="text-sm text-emerald-deep">{{ $statusLabels[$submission->status] ?? $submission->status }}{{ $submission->final_score !== null ? ' · '.$submission->final_score.' ball' : '' }}</p>
            </a>
        @empty
            <p class="text-sm text-graysoft">{{ __('Hali topshirish yo‘q.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $submissions->links() }}</div>
@endsection
