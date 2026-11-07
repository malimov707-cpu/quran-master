@extends('layouts.app')
@section('title', __('Dars oxiridagi test'))
@section('content')
<a href="{{ route('education.lessons.show', $lesson['id']) }}" class="text-forest">← {{ $lesson['title'] }}</a>
@if ($can_manage)<a href="{{ route('lessons.quiz.edit', $lesson['id']) }}" class="ml-4 text-forest">{{ __('Testni tahrirlash') }}</a>@endif
@if (! $quiz)
    <section class="glass-panel mt-6 p-6"><h1 class="text-xl font-bold">{{ __('Test hali tayyorlanmagan.') }}</h1></section>
@else
    <h1 class="mt-5 text-2xl font-bold text-forest">{{ $quiz['title'] }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ __('O‘tish chegarasi') }}: {{ $quiz['pass_percent'] }}% · {{ count($quiz['questions']) }} {{ __('savol') }}</p>
    @if ($attempts->isNotEmpty())
        @php($latest = $attempts->first())
        <section class="glass-panel mt-5 p-5"><h2 class="text-xl font-bold">{{ __('Natija') }}: {{ $latest->score }}% — {{ $latest->passed ? __('O‘tdingiz') : __('Yana mashq qiling') }}</h2><p>{{ $latest->correct_count }} / {{ $latest->question_count }}</p>
        <details class="mt-4"><summary class="cursor-pointer text-forest">{{ __('Javoblar va izohlar') }}</summary>
            @foreach ($latest->feedback as $feedback)
                <div class="mt-4 border-t border-gray-200 pt-3"><p class="font-semibold">{{ $feedback['is_correct'] ? '✓' : '✗' }} {{ $feedback['prompt'] }}</p><p>{{ __('Sizning javobingiz') }}: {{ $feedback['options'][$feedback['selected_index']] }}</p><p>{{ __('To‘g‘ri javob') }}: {{ $feedback['options'][$feedback['correct_index']] }}</p><p class="mt-2 text-sm text-graysoft">{{ $feedback['explanation'] ?? '' }}</p></div>
            @endforeach
        </details>
        @if ($latest->passed && ($quiz['awards_certificate'] ?? false))<form method="POST" action="{{ route('certificates.exam.claim', $quiz['id']) }}" class="mt-4">@csrf<button class="rounded-xl bg-forest px-5 py-3 text-white">{{ __('Sertifikat olish') }}</button></form>@endif
        <p class="mt-4 text-sm">{{ __('Oxirgi urinishlar') }}: @foreach ($attempts as $attempt) {{ $attempt->score }}%{{ ! $loop->last ? ' · ' : '' }} @endforeach</p>
        </section>
    @endif
    <form method="POST" action="{{ route('lessons.quiz.attempt', $lesson['id']) }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="revision" value="{{ $quiz['revision'] }}">
        <input type="hidden" name="submission_key" value="{{ Illuminate\Support\Str::uuid() }}">
        @foreach ($quiz['questions'] as $index => $question)
            <fieldset class="rounded-2xl bg-white p-5 ring-1 ring-black/5"><legend class="sr-only">{{ $question['prompt'] }}</legend><p class="font-semibold">{{ $index + 1 }}. {{ $question['prompt'] }}</p>@if ($question['rule'])<p class="mt-1 text-xs text-graysoft">{{ $question['rule'] }}</p>@endif
                <div class="mt-4 space-y-3">@foreach ($question['options'] as $optionIndex => $option)<label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3"><input type="radio" name="answers[{{ $index }}]" value="{{ $optionIndex }}" required @checked((string) old("answers.$index") === (string) $optionIndex)><span>{{ $option }}</span></label>@endforeach</div>
            </fieldset>
        @endforeach
        @if ($can_submit)<button class="rounded-xl bg-forest px-6 py-3 font-semibold text-white">{{ __('Tekshirish va progressga saqlash') }}</button>@endif
    </form>
@endif
@endsection
