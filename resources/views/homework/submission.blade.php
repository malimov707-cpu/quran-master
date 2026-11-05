@extends('layouts.app')
@section('title', 'Topshirish natijasi')
@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">{{ $submission->assignment->title }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ $submission->attempt }}-urinish · {{ $submission->submitted_at->format('d.m.Y H:i') }}</p>
    <p class="mt-4 text-lg font-semibold text-emerald-deep">{{ $statusLabels[$submission->status] ?? $submission->status }}{{ $submission->final_score !== null ? ' · '.$submission->final_score.' ball' : '' }}</p>
    @if ($canResubmit)
        <a href="{{ route('homework.show', $submission->assignment) }}" class="mt-4 inline-block rounded-lg bg-emerald-deep px-4 py-2 text-cream">Qayta topshirish</a>
    @endif
    <section class="mt-6 space-y-4 rounded-2xl bg-ivory p-6 ring-1 ring-black/5">
        <h2 class="font-semibold text-emerald-deep">Yuborilgan audio</h2>
        @if ($submission->student_note)<p class="text-sm whitespace-pre-line">{{ $submission->student_note }}</p>@endif
        @forelse ($submission->files as $file)
            <audio controls preload="none" src="{{ route('submissions.audio', [$submission, $file]) }}" class="w-full"></audio>
        @empty
            <p class="text-sm text-graysoft">Audio biriktirilmagan.</p>
        @endforelse
    </section>
    <section class="mt-6 rounded-2xl bg-ivory p-6 ring-1 ring-black/5">
        <h2 class="font-semibold text-emerald-deep">AI tahlil</h2>
        @if ($aiReview)
            <p class="mt-2 text-sm">{{ $aiReview->summary ?? 'Tahlil natijasi hali tayyor emas.' }}</p>
            @if ($aiReview->needs_mentor_review_reason)<p class="mt-2 text-sm text-graysoft">{{ $aiReview->needs_mentor_review_reason }}</p>@endif
        @else
            <p class="mt-2 text-sm text-graysoft">AI tahlil hali mavjud emas.</p>
        @endif
    </section>
    <h2 class="mt-8 text-lg font-semibold text-emerald-deep">Mentor tekshiruvi</h2>
    <div class="mt-4 space-y-4">
        @forelse ($submission->reviews as $review)
            <section class="space-y-3 rounded-2xl bg-ivory p-6 ring-1 ring-black/5">
                <p class="font-semibold">{{ $review->mentor->name }} · {{ $review->score !== null ? $review->score.' ball' : 'Qayta topshirish' }}</p>
                @if ($review->comment)<p class="text-sm whitespace-pre-line">{{ $review->comment }}</p>@endif
                @foreach ($review->segments as $segment)
                    <div class="border-l-2 border-gold py-2 pl-4 text-sm">
                        @if ($segment->ayah_from)<p>{{ $segment->ayah_from }}-oyat{{ $segment->ayah_to && $segment->ayah_to !== $segment->ayah_from ? ' – '.$segment->ayah_to : '' }}</p>@endif
                        @if ($segment->start_ms !== null)<p class="text-graysoft">{{ $segment->start_ms / 1000 }}–{{ $segment->end_ms / 1000 }} soniya</p>@endif
                        <p class="whitespace-pre-line">{{ $segment->comment }}</p>
                    </div>
                @endforeach
            </section>
        @empty
            <p class="text-sm text-graysoft">Mentor hali tekshirmagan.</p>
        @endforelse
    </div>
@endsection
