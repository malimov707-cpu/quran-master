@extends('layouts.app')
@section('title', $lesson->title)
@section('content')
    <a href="{{ route('education.lessons', ['course_id' => $lesson->course_id]) }}" class="text-sm text-forest">{{ __('← Kurs darslari') }}</a>
    <h1 class="mt-4 text-2xl font-bold text-forest">{{ $lesson->title }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ $lesson->course->name }} · {{ $lesson->duration_minutes ?? '—' }} daqiqa</p>
    <div class="mt-6 space-y-6">
        @if ($lesson->learning_objectives)<section class="rounded-2xl bg-white p-6 ring-1 ring-black/5"><h2 class="font-semibold text-forest">{{ __('O‘quv maqsadi') }}</h2><p class="mt-3 whitespace-pre-line">{{ $lesson->learning_objectives }}</p></section>@endif
        <section class="rounded-2xl bg-white p-6 ring-1 ring-black/5"><h2 class="font-semibold text-forest">{{ __('Dars mazmuni') }}</h2><p class="mt-3 leading-8 whitespace-pre-line">{{ $lesson->description ?? 'Dars mazmuni hali kiritilmagan.' }}</p></section>
        @if ($lesson->homework)<section class="rounded-2xl bg-cream p-6 ring-1 ring-black/5"><h2 class="font-semibold text-forest">{{ __('Mustaqil mashq') }}</h2><p class="mt-3 whitespace-pre-line">{{ $lesson->homework }}</p></section>@endif
        @if ($canUpdate)
            <details class="rounded-2xl bg-white p-6 ring-1 ring-black/5" @if ($errors->any()) open @endif><summary class="cursor-pointer font-semibold text-forest">{{ __('Darsni tahrirlash') }}</summary><form method="POST" action="{{ route('education.lessons.update', $lesson) }}" class="mt-4 space-y-4">@csrf @method('PATCH') @include('education.lesson-fields', ['editingLesson' => $lesson])<button class="rounded-lg bg-forest px-5 py-3 font-semibold text-white">{{ __('Saqlash') }}</button></form></details>
        @endif
    </div>
@endsection
