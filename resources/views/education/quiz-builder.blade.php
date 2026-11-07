@extends('layouts.app')
@section('title', __('Quiz builder'))
@section('content')
<a href="{{ route('education.lessons.show', $lesson['id']) }}" class="text-forest">← {{ $lesson['title'] }}</a>
<h1 class="mt-5 text-2xl font-bold text-forest">{{ __('Quiz builder') }}</h1>
<p class="mt-2 text-sm text-graysoft">{{ __('Variantli savollar tuzing. Har savolda bitta to‘g‘ri javob belgilang. Nashrdan oldin tajvid savollarini ustoz tekshirsin.') }}</p>
@php($initial = ['title' => old('title', $quiz['title'] ?? 'Dars yakuniy testi'), 'status' => old('status', $quiz['status'] ?? 'draft'), 'pass_percent' => old('pass_percent', $quiz['pass_percent'] ?? 70), 'awards_certificate' => (bool) old('awards_certificate', $quiz['awards_certificate'] ?? false), 'questions' => old('questions', $quiz['questions'] ?? $template)])
<form method="POST" action="{{ route('lessons.quiz.update', $lesson['id']) }}" x-data="quizBuilder(@js($initial))" class="mt-6 space-y-5">
    @csrf @method('PUT')
    <input type="hidden" name="revision" value="{{ $quiz['revision'] ?? 0 }}">
    <div class="glass-panel grid gap-4 p-5 sm:grid-cols-3">
        <label class="text-sm">{{ __('Test nomi') }}<input name="title" x-model="title" required maxlength="255" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label>
        <label class="text-sm">{{ __('Holat') }}<select name="status" x-model="status" class="mt-2 w-full rounded-lg border border-gray-200 p-3"><option value="draft">{{ __('Qoralama') }}</option><option value="published">{{ __('Nashr qilingan') }}</option><option value="archived">{{ __('Arxiv') }}</option></select></label>
        <label class="text-sm">{{ __('O‘tish chegarasi') }} (%)<input type="number" name="pass_percent" x-model="pass_percent" min="1" max="100" required class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label>
    </div>
    <label class="glass-panel flex items-start gap-3 p-5"><input type="hidden" name="awards_certificate" value="0"><input type="checkbox" name="awards_certificate" value="1" x-model="awards_certificate"><span>{{ __('Tajvid nazariy imtihoni: o‘tgan o‘quvchiga sertifikat berish') }}</span></label>
    <template x-for="(question, index) in questions" :key="question._key">
        <fieldset class="rounded-2xl bg-white p-5 ring-1 ring-black/5">
            <legend class="sr-only">{{ __('Savol') }}</legend>
            <div class="flex items-center justify-between gap-3"><h2 class="font-semibold" x-text="`${index + 1}. Savol`"></h2><button type="button" @click="questions.splice(index, 1)" :disabled="questions.length <= 1" class="text-sm text-red-700">{{ __('Savolni o‘chirish') }}</button></div>
            <label class="mt-4 block text-sm">{{ __('Savol matni') }}<textarea :name="`questions[${index}][prompt]`" x-model="question.prompt" required maxlength="1000" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></textarea></label>
            <label class="mt-3 block text-sm">{{ __('Tajvid qoidasi yoki mavzu') }}<input :name="`questions[${index}][rule]`" x-model="question.rule" maxlength="80" placeholder="madd, ghunnah, qalqalah…" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label>
            <p class="mt-4 text-sm text-graysoft">{{ __('To‘g‘ri javobni chapdagi doira bilan belgilang.') }}</p>
            <template x-for="(option, optionIndex) in question.options" :key="optionIndex"><div class="mt-3 flex items-center gap-3">
                <input type="radio" :name="`questions[${index}][correct_index]`" :value="optionIndex" x-model.number="question.correct_index" required :aria-label="`${optionIndex + 1}. To‘g‘ri javob`">
                <input :name="`questions[${index}][options][${optionIndex}]`" x-model="question.options[optionIndex]" required maxlength="500" :aria-label="`${optionIndex + 1}. Variant`" class="min-w-0 flex-1 rounded-lg border border-gray-200 p-3">
                <button type="button" @click="removeOption(question, optionIndex)" :disabled="question.options.length <= 2" class="text-red-700" aria-label="Variantni o‘chirish">×</button>
            </div></template>
            <button type="button" @click="question.options.push('')" :disabled="question.options.length >= 6" class="mt-3 text-sm text-forest">+ {{ __('Variant qo‘shish') }}</button>
            <label class="mt-4 block text-sm">{{ __('Javob izohi') }}<textarea :name="`questions[${index}][explanation]`" x-model="question.explanation" maxlength="2000" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></textarea></label>
        </fieldset>
    </template>
    <div class="flex flex-wrap gap-4"><button type="button" @click="addQuestion()" :disabled="questions.length >= 50" class="rounded-xl border border-forest px-5 py-3 text-forest">+ {{ __('Savol qo‘shish') }}</button><button type="submit" :disabled="questions.length === 0" class="rounded-xl bg-forest px-6 py-3 font-semibold text-white">{{ __('Testni saqlash') }}</button><a href="{{ route('lessons.quiz.show', $lesson['id']) }}" class="px-3 py-3 text-forest">{{ __('Testni ko‘rish') }}</a></div>
</form>
<script>
function quizBuilder(data) {
    let nextKey = 0;
    return {
        ...data,
        questions: data.questions.map(question => ({ ...question, correct_index: Number(question.correct_index), _key: nextKey++ })),
        addQuestion() { this.questions.push({ prompt: '', options: ['', '', '', ''], correct_index: 0, explanation: '', rule: '', _key: nextKey++ }); },
        removeOption(question, index) {
            if (question.options.length <= 2) return;
            question.options.splice(index, 1);
            if (question.correct_index === index) question.correct_index = 0;
            else if (question.correct_index > index) question.correct_index--;
        },
    };
}
</script>
@endsection
