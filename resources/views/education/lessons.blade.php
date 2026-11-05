@extends('layouts.app')
@section('title', __('Darslar'))
@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Darslar') }}</h1>
    <form method="GET" action="{{ route('education.lessons') }}" class="mt-5 flex flex-wrap gap-3">
        <label for="filter_course" class="sr-only">{{ __('Kurs') }}</label><select id="filter_course" name="course_id" class="rounded-lg border border-gray-200 bg-white p-3"><option value="">{{ __('Barcha kurslar') }}</option>@foreach ($courses as $course)<option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>@endforeach</select>
        <button class="rounded-lg bg-forest px-4 py-2 text-white">{{ __('Ko‘rsatish') }}</button>
    </form>
    @if ($canManage)
        <details class="mt-6 rounded-2xl bg-white p-6 ring-1 ring-black/5" @if ($errors->any()) open @endif>
            <summary class="cursor-pointer font-semibold text-forest">{{ __('Yangi dars yaratish') }}</summary>
            <form method="POST" action="{{ route('education.lessons.store') }}" class="mt-4 space-y-4">
                @csrf
                <label class="block text-sm">{{ __('Kurs') }}<select name="course_id" required class="mt-2 w-full rounded-lg border border-gray-200 p-3">@foreach ($courses as $course)<option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->name }}</option>@endforeach</select></label>
                @include('education.lesson-fields', ['editingLesson' => null])
                <button class="rounded-lg bg-forest px-5 py-3 font-semibold text-white">{{ __('Darsni yaratish') }}</button>
            </form>
        </details>
    @endif
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse ($lessons as $lesson)
            <a href="{{ route('education.lessons.show', $lesson) }}" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5 hover:ring-gold">
                <p class="text-xs text-graysoft">{{ $lesson->course->name }} · {{ $lesson->duration_minutes ?? '—' }} daqiqa</p>
                <h2 class="mt-3 font-semibold text-forest">{{ $lesson->sort_order }}. {{ $lesson->title }}</h2>
                <p class="mt-2 text-sm text-graysoft">{{ Str::limit($lesson->learning_objectives, 150) }}</p>
                @if ($canManage)<p class="mt-3 text-xs text-graysoft">{{ ['draft' => 'Qoralama', 'published' => 'E’lon qilingan', 'archived' => 'Arxiv'][$lesson->status] }}</p>@endif
                <p class="mt-4 text-sm font-semibold text-forest">{{ __('Darsni ochish →') }}</p>
            </a>
        @empty
            <p class="text-graysoft">{{ __('Bu kursga dars qo‘shilmagan.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $lessons->links() }}</div>
@endsection
