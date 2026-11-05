@extends('layouts.app')

@section('title', __('Mentor tekshiruv studiyasi'))

@section('content')
    <div class="mb-6"><p class="welcome-eyebrow">{{ __('Mentor ish maydoni') }}</p><h1 class="text-3xl font-bold text-forest">{{ __('Tekshiruv studiyasi') }}</h1><p class="mt-2 text-sm text-graysoft">{{ __('Tinglang, oyatlar bilan solishtiring va fikr bildiring.') }}</p></div>
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Chap: o'quvchi + topshiriq -->
        <div class="space-y-4">
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">{{ __('O\'quvchi') }}</h3>
                <p class="mt-1 text-lg font-semibold text-emerald-deep">{{ $submission->student->name }}</p>
                <p class="text-sm text-graysoft">{{ $submission->student->email }}</p>
                <p class="mt-3 text-xs text-graysoft">Urinish: {{ $submission->attempt }} · {{ $submission->submitted_at->format('d.m.Y H:i') }}</p>
            </div>
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">{{ __('Topshiriq') }}</h3>
                <p class="mt-1 font-semibold text-emerald-deep">{{ $submission->assignment->title }}</p>
                @if ($submission->assignment->surah_number)
                    <p class="text-sm text-graysoft">Sura {{ $submission->assignment->surah_number }}, oyat {{ $submission->assignment->ayah_from }}–{{ $submission->assignment->ayah_to }}</p>
                @endif
                @if ($submission->student_note)
                    <p class="mt-3 rounded-lg bg-cream p-3 text-sm">{{ $submission->student_note }}</p>
                @endif
            </div>
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">{{ __('Oldingi baholar') }}</h3>
                @forelse ($submission->reviews as $review)
                    <div class="mt-2 border-t border-gray-100 pt-2 first:border-0">
                        <p class="text-sm font-medium">{{ $review->mentor->name }} — {{ $review->score ? $review->score.' ball' : 'qayta topshirish' }}</p>
                        @if ($review->comment)<p class="text-xs text-graysoft">{{ $review->comment }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-graysoft">{{ __('Hali baholanmagan.') }}</p>
                @endforelse
            </div>
        </div>

        <!-- O'ng: audio + forma -->
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">{{ __('Audio') }}</h3>
                @forelse ($audioUrls as $a)
                    <div class="audio-studio mt-4" data-audio-studio><audio controls preload="metadata" src="{{ $a['url'] }}" class="mt-2 w-full"></audio><canvas class="audio-waveform" height="96" aria-label="{{ __('Audio to‘lqin ko‘rinishi') }}"></canvas><button type="button" data-load-waveform class="mt-3 rounded-lg bg-forest px-4 py-2 text-sm text-white">{{ __('Audio to‘lqinini ko‘rsatish') }}</button><p data-waveform-status role="status" class="mt-2 text-xs text-graysoft"></p><div class="mt-3 flex flex-wrap gap-3"><button type="button" data-audio-skip="-5" class="bookmark-button">{{ __('−5 s') }}</button><button type="button" data-audio-skip="5" class="bookmark-button">{{ __('+5 s') }}</button><label class="text-xs text-graysoft">{{ __('Tezlik') }} <select data-playback-rate><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option></select></label></div></div>
                    <p class="text-xs text-graysoft">{{ $a['name'] }} ({{ number_format($a['size'] / 1024) }} KB)</p>
                @empty
                    <p class="text-sm text-graysoft">{{ __('Audio yo\'q.') }}</p>
                @endforelse
            </div>

            <section class="glass-panel p-6"><h2 class="font-semibold text-forest">{{ __('Oyat bilan taqqoslash') }}</h2>@forelse ($comparisonAyahs as $ayah)<div class="mt-4 border-b border-gray-200 pb-4"><span class="text-xs text-graysoft">{{ $ayah->number_in_surah }}</span><p lang="ar" dir="rtl" class="ayah-text">{{ $ayah->text_arabic }}</p></div>@empty<p class="mt-3 text-sm text-graysoft">{{ __('Topshiriqqa oyatlar biriktirilmagan.') }}</p>@endforelse</section>
            <section class="glass-panel p-6"><h2 class="font-semibold text-forest">{{ __('AI tahlili') }}</h2>@forelse ($submission->aiReviews as $analysis)<div class="mt-4"><p class="text-xs text-graysoft">{{ $analysis->status }} · {{ $analysis->provider }}</p>@if ($analysis->summary)<p class="mt-2 text-sm">{{ $analysis->summary }}</p>@endif @if ($analysis->needs_mentor_review_reason)<p class="mt-2 text-sm text-graysoft">{{ $analysis->needs_mentor_review_reason }}</p>@endif @foreach ($analysis->segments as $segment)<div class="mt-3 rounded-xl bg-cream p-3 text-sm"><p>{{ $segment->error_type }} · {{ __('Oyat') }} {{ $segment->ayah_number }}</p><p lang="ar" dir="rtl" class="mt-2 text-lg">{{ $segment->expected_text }}</p>@if ($segment->start_ms !== null)<button type="button" class="bookmark-button mt-2" data-seek-audio="{{ $segment->start_ms / 1000 }}">▶ {{ number_format($segment->start_ms / 1000, 1) }} s</button>@endif</div>@endforeach</div>@empty<p class="mt-3 text-sm text-graysoft">{{ __('AI natijasi hali mavjud emas. Yakuniy bahoni mentor beradi.') }}</p>@endforelse</section>
            <form method="POST" action="{{ route('mentor.review', $submission) }}" data-confirm="{{ __('Tekshiruvni saqlaysizmi?') }}" class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="review-score" class="text-sm font-medium text-emerald-deep">{{ __('Ball (0–100)') }}</label>
                        <input id="review-score" value="{{ old('score') }}" name="score" type="number" min="0" max="100" required
                               class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label for="review-action" class="text-sm font-medium text-emerald-deep">{{ __('Qaror') }}</label>
                        <select id="review-action" name="action" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2">
                            <option value="approve">{{ __('Tasdiqlash') }}</option>
                            <option value="resubmit">{{ __('Qayta topshirish') }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="review-comment" class="text-sm font-medium text-emerald-deep">{{ __('Izoh') }}</label>
                        <textarea id="review-comment" name="comment" rows="3" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                                  placeholder="{{ __('Tajvid va qiroat bo\'yicha izoh...') }}">{{ old('comment') }}</textarea>
                    </div>
                </div>
                <button class="mt-4 rounded-lg bg-emerald-deep px-6 py-2 font-semibold text-cream hover:bg-forest">
                    Saqlash
                </button>
            </form>
        </div>
    </div>
@endsection
