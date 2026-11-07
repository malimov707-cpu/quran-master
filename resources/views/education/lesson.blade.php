@extends('layouts.app')
@section('title', $lesson->title)
@section('content')
    <a href="{{ route('education.lessons', ['course_id' => $lesson->course_id]) }}" class="text-sm text-forest">{{ __('← Kurs darslari') }}</a>
    <h1 class="mt-4 text-2xl font-bold text-forest">{{ $lesson->title }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ $lesson->course->name }} · {{ $lesson->duration_minutes ?? '—' }} daqiqa</p>
    @if ($canComplete || $canUpdate)
        <a href="{{ $canUpdate ? route('lessons.quiz.edit', $lesson) : route('lessons.quiz.show', $lesson) }}" class="mt-5 inline-block rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ $canUpdate ? __('Dars testini boshqarish') : __('Dars oxiridagi test') }} →</a>
    @endif
    <div class="mt-6 space-y-6">
        @if ($lesson->learning_objectives)<section class="rounded-2xl bg-white p-6 ring-1 ring-black/5"><h2 class="font-semibold text-forest">{{ __('O‘quv maqsadi') }}</h2><p class="mt-3 whitespace-pre-line">{{ $lesson->learning_objectives }}</p></section>@endif
        <section class="rounded-2xl bg-white p-6 ring-1 ring-black/5"><h2 class="font-semibold text-forest">{{ __('Dars mazmuni') }}</h2><p class="mt-3 leading-8 whitespace-pre-line">{{ $lesson->description ?? 'Dars mazmuni hali kiritilmagan.' }}</p></section>
        @if ($lesson->homework)<section class="rounded-2xl bg-cream p-6 ring-1 ring-black/5"><h2 class="font-semibold text-forest">{{ __('Mustaqil mashq') }}</h2><p class="mt-3 whitespace-pre-line">{{ $lesson->homework }}</p></section>@endif
        @if ($canComplete)
            <section class="glass-panel p-6"><h2 class="font-semibold text-forest">{{ __('Keyingi qadam') }}</h2><p class="mt-2 text-sm text-graysoft">{{ __('Bu belgi darsni o‘qiganingizni bildiradi. Ustoz bahosi o‘rnini bosmaydi.') }}</p>@if ($completed)<p class="mt-4 text-sm font-semibold text-forest">✓ {{ __('Dars o‘qilgan') }}</p><a href="{{ route('dashboard') }}" class="mt-4 inline-block text-sm text-forest">{{ __('Bugungi rejaga qaytish') }} →</a>@else<form method="POST" action="{{ route('student.lesson.complete', $lesson) }}" class="mt-4">@csrf<button type="submit" class="rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Darsni o‘qidim, mashqqa o‘tish') }} →</button></form>@endif</section>
        @endif
        @if ($canUpdate)
            <details class="rounded-2xl bg-white p-6 ring-1 ring-black/5" @if ($errors->any()) open @endif><summary class="cursor-pointer font-semibold text-forest">{{ __('Darsni tahrirlash') }}</summary><form method="POST" action="{{ route('education.lessons.update', $lesson) }}" class="mt-4 space-y-4">@csrf @method('PATCH') @include('education.lesson-fields', ['editingLesson' => $lesson])<button class="rounded-lg bg-forest px-5 py-3 font-semibold text-white">{{ __('Saqlash') }}</button></form></details>
        @endif
    </div>
@endsection
