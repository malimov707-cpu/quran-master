@extends('layouts.app')

@section('title', $course->name)

@section('content')
    <a href="{{ route('courses.index') }}" class="text-sm text-forest hover:underline">← Kurslar</a>
    <h1 class="mt-2 text-2xl font-bold text-emerald-deep">{{ $course->name }}</h1>
    <p class="mt-1 text-graysoft">{{ $course->description }}</p>
    <div class="mt-4 flex gap-6 text-sm text-graysoft">
        <span>Daraja: {{ ['beginner' => 'Boshlang\'ich', 'intermediate' => 'O\'rta', 'advanced' => 'Yuqori'][$course->level] }}</span>
        <span>Davomiyligi: {{ $course->duration_weeks ?? '—' }} hafta</span>
        <span>Bosh ustoz: {{ $course->headTeacher?->name ?? '—' }}</span>
    </div>

    <h2 class="mt-8 font-semibold text-emerald-deep">Darslar</h2>
    <div class="mt-3 space-y-2">
        @forelse ($course->lessons as $lesson)
            <div class="flex items-center justify-between rounded-xl bg-ivory px-4 py-3 shadow-sm ring-1 ring-black/5">
                <div>
                    <p class="font-medium text-emerald-deep">{{ $lesson->title }}</p>
                    @if ($lesson->homework)
                        <p class="text-xs text-graysoft">🏠 {{ \Illuminate\Support\Str::limit($lesson->homework, 80) }}</p>
                    @endif
                </div>
                <span class="text-xs text-graysoft">{{ $lesson->duration_minutes ?? '—' }} daq</span>
            </div>
        @empty
            <p class="text-sm text-graysoft">Darslar hali qo'shilmagan.</p>
        @endforelse
    </div>
@endsection
