@extends('layouts.app')
@section('title', $assignment->title)
@section('content')
    <a href="{{ route('homework.index') }}" class="text-sm text-emerald-deep">← Uy vazifalarim</a>
    <h1 class="mt-4 text-2xl font-bold text-emerald-deep">{{ $assignment->title }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ $assignment->group->name }} · Muddat: {{ $assignment->due_at?->format('d.m.Y H:i') ?? 'Belgilanmagan' }}</p>
    @if ($assignment->surah_number)
        <p class="mt-3 text-sm">Sura {{ $assignment->surah_number }}, oyat {{ $assignment->ayah_from }}–{{ $assignment->ayah_to }}</p>
    @endif
    @if ($assignment->description)<p class="mt-4 whitespace-pre-line">{{ $assignment->description }}</p>@endif
    @if ($assignment->status === 'published')
        <form method="POST" action="{{ route('homework.submit', $assignment) }}" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl bg-ivory p-6 ring-1 ring-black/5">
            @csrf
            <div>
                <label for="audio" class="block text-sm font-semibold text-emerald-deep">Audio yozuv {{ $assignment->requires_audio ? '(majburiy)' : '(ixtiyoriy)' }}</label>
                <input id="audio" name="audio" type="file" accept="audio/*" @required($assignment->requires_audio) aria-describedby="audio-help" class="mt-2 block w-full rounded-lg border border-gray-200 p-3 text-sm">
                <p id="audio-help" class="mt-2 text-xs text-graysoft">MP3, M4A, AAC, OGG, WAV yoki WebM. Maksimal hajm: 20 MB.</p>
                @error('audio')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="student_note" class="block text-sm font-semibold text-emerald-deep">Izoh</label>
                <textarea id="student_note" name="student_note" maxlength="5000" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 p-3">{{ old('student_note') }}</textarea>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-deep px-5 py-3 font-semibold text-cream">Topshiriqni yuborish</button>
        </form>
    @else
        <p class="mt-6 rounded-xl bg-gold/10 p-4">Bu topshiriq yopilgan. Yangi audio yuborib bo‘lmaydi.</p>
    @endif
    <h2 class="mt-8 text-lg font-semibold text-emerald-deep">Urinishlarim</h2>
    <div class="mt-4 space-y-3">
        @forelse ($submissions as $submission)
            <a href="{{ route('homework.submission', $submission) }}" class="block rounded-xl bg-ivory p-4 text-sm ring-1 ring-black/5">{{ $submission->attempt }}-urinish — {{ $statusLabels[$submission->status] ?? $submission->status }}{{ $submission->final_score !== null ? ' · '.$submission->final_score.' ball' : '' }}</a>
        @empty
            <p class="text-sm text-graysoft">Hali topshirmagansiz.</p>
        @endforelse
    </div>
@endsection
