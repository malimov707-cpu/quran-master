@extends('layouts.app')

@section('title', 'Review Studio')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Chap: o'quvchi + topshiriq -->
        <div class="space-y-4">
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">O'quvchi</h3>
                <p class="mt-1 text-lg font-semibold text-emerald-deep">{{ $submission->student->name }}</p>
                <p class="text-sm text-graysoft">{{ $submission->student->email }}</p>
                <p class="mt-3 text-xs text-graysoft">Urinish: {{ $submission->attempt }} · {{ $submission->submitted_at->format('d.m.Y H:i') }}</p>
            </div>
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">Topshiriq</h3>
                <p class="mt-1 font-semibold text-emerald-deep">{{ $submission->assignment->title }}</p>
                @if ($submission->assignment->surah_number)
                    <p class="text-sm text-graysoft">Sura {{ $submission->assignment->surah_number }}, oyat {{ $submission->assignment->ayah_from }}–{{ $submission->assignment->ayah_to }}</p>
                @endif
                @if ($submission->student_note)
                    <p class="mt-3 rounded-lg bg-cream p-3 text-sm">{{ $submission->student_note }}</p>
                @endif
            </div>
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">Oldingi baholar</h3>
                @forelse ($submission->reviews as $review)
                    <div class="mt-2 border-t border-gray-100 pt-2 first:border-0">
                        <p class="text-sm font-medium">{{ $review->mentor->name }} — {{ $review->score ? $review->score.' ball' : 'qayta topshirish' }}</p>
                        @if ($review->comment)<p class="text-xs text-graysoft">{{ $review->comment }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-graysoft">Hali baholanmagan.</p>
                @endforelse
            </div>
        </div>

        <!-- O'ng: audio + forma -->
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-xs uppercase text-graysoft">Audio</h3>
                @forelse ($audioUrls as $a)
                    <audio controls src="{{ $a['url'] }}" class="mt-2 w-full"></audio>
                    <p class="text-xs text-graysoft">{{ $a['name'] }} ({{ number_format($a['size'] / 1024) }} KB)</p>
                @empty
                    <p class="text-sm text-graysoft">Audio yo'q.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('mentor.review', $submission) }}" class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-emerald-deep">Ball (0–100)</label>
                        <input name="score" type="number" min="0" max="100" required
                               class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-emerald-deep">Qaror</label>
                        <select name="action" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2">
                            <option value="approve">Tasdiqlash</option>
                            <option value="resubmit">Qayta topshirish</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-emerald-deep">Izoh</label>
                        <textarea name="comment" rows="3" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                                  placeholder="Tajvid va qiroat bo'yicha izoh..."></textarea>
                    </div>
                </div>
                <button class="mt-4 rounded-lg bg-emerald-deep px-6 py-2 font-semibold text-cream hover:bg-forest">
                    Saqlash
                </button>
            </form>
        </div>
    </div>
@endsection
