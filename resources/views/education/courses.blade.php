@extends('layouts.app')

@section('title', __('Kurslar'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Kurslar') }}</h1>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($courses as $course)
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <div class="flex items-start justify-between">
                    <h3 class="font-semibold text-emerald-deep">{{ $course->name }}</h3>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium
                        {{ $course->status === 'published' ? 'bg-emerald-100 text-emerald-800' : ($course->status === 'archived' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-800') }}">
                        {{ $course->status }}
                    </span>
                </div>
                @if ($canViewLessons)<a href="{{ route('education.lessons', ['course_id' => $course->id]) }}" class="mt-3 inline-block text-sm font-semibold text-forest">Darslar: {{ $course->lessons_count }} ta →</a>@endif
                <p class="mt-2 line-clamp-2 text-sm text-graysoft">{{ $course->description }}</p>
                <div class="mt-4 flex items-center justify-between text-xs text-graysoft">
                    <span>Daraja: {{ $course->level }}</span>
                    <span>{{ $course->groups_count }} guruh</span>
                </div>
            </div>
        @empty
            <p class="text-graysoft">{{ __('Hozircha kurs yo\'q.') }}</p>
        @endforelse
    </div>

    @if (auth()->user()->hasPermission('courses.create'))
    <div class="mt-8 rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
        <h3 class="font-semibold text-emerald-deep">{{ __('Yangi kurs') }}</h3>
        <form method="POST" action="{{ route('education.courses.store') }}" class="mt-4 grid gap-3 sm:grid-cols-4">
            @csrf
            <input aria-label="{{ __('Kurs nomi') }}" name="name" required placeholder="{{ __('Kurs nomi') }}"
                   class="rounded-lg border border-gray-200 px-3 py-2 sm:col-span-2">
            <select aria-label="{{ __('Daraja') }}" name="level" required class="rounded-lg border border-gray-200 px-3 py-2">
                <option value="beginner">{{ __('Boshlang\'ich') }}</option>
                <option value="intermediate">{{ __('O\'rta') }}</option>
                <option value="advanced">{{ __('Yuqori') }}</option>
            </select>
            <input aria-label="{{ __('Davomiylik (hafta)') }}" name="duration_weeks" type="number" min="1" placeholder="{{ __('Hafta') }}"
                   class="rounded-lg border border-gray-200 px-3 py-2">
            <button class="rounded-lg bg-emerald-deep px-4 py-2 font-semibold text-cream hover:bg-forest sm:col-span-4">
                {{ __('Qo‘shish') }}
            </button>
        </form>
    </div>
@endif
<div class="mt-6">{{ $courses->links() }}</div>
@endsection
