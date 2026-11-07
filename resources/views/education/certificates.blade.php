@extends('layouts.app')
@section('title', __('Sertifikatlar'))
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-2xl font-bold text-forest">{{ __('Sertifikatlar') }}</h1><p class="mt-2 text-sm text-graysoft">{{ __('PDF, ijtimoiy tarmoqlar uchun rasm va QR orqali tekshirish.') }}</p></div></div>
@if ($courses->isNotEmpty() || $exams->isNotEmpty())
<section class="glass-panel mt-5 p-5"><h2 class="font-semibold text-forest">{{ __('Sertifikat olish') }}</h2><div class="mt-4 grid gap-3 sm:grid-cols-2">
@foreach ($courses as $course)<a href="{{ route('certificates.course', $course) }}" class="rounded-xl border border-gray-200 bg-white p-4"><strong>{{ $course->name }}</strong><span class="mt-2 block text-sm text-forest">{{ __('Kurs talablarini tekshirish') }} →</span></a>@endforeach
@foreach ($exams as $exam)<form method="POST" action="{{ route('certificates.exam.claim', $exam) }}" class="rounded-xl border border-gold bg-cream p-4">@csrf<strong>{{ $exam->title }}</strong><p class="mt-1 text-sm">{{ __('Tajvid nazariy imtihoni') }}</p><button class="mt-3 rounded-lg bg-forest px-4 py-2 text-white">{{ __('Sertifikat olish') }}</button></form>@endforeach
</div></section>
@endif
<div class="mt-6 grid gap-5 lg:grid-cols-2">
@forelse ($certificates as $certificate)
    <article class="overflow-hidden rounded-2xl border border-gold bg-white shadow-sm">
        <div class="bg-forest p-6 text-cream"><p class="text-xs tracking-widest text-gold">QUR’ON AI USTOZ</p><h2 class="mt-3 text-xl font-bold">{{ $certificate->recipient_name }}</h2><p class="mt-2">{{ $certificate->title }}</p><p class="mt-3 text-sm text-gold">{{ __($certificate->type === 'tajwid' ? 'Tajvid nazariy imtihoni' : 'Kursni yakunlash') }}{{ $certificate->score !== null ? ' · '.$certificate->score.'%' : '' }}</p></div>
        <div class="space-y-4 p-5"><div class="flex justify-between gap-3 text-sm"><span>{{ $certificate->issued_at->timezone(config('app.display_timezone', 'Asia/Tashkent'))->format('d.m.Y') }}</span><strong class="{{ $certificate->revoked_at ? 'text-red-700' : 'text-forest' }}">{{ __($certificate->revoked_at ? 'Bekor qilingan' : 'Haqiqiy') }}</strong></div>
            <p class="break-all text-xs text-graysoft">{{ $certificate->code }}</p>
            @if (! $certificate->revoked_at)
                <div class="flex flex-wrap gap-3">@foreach (['pdf' => 'PDF yuklab olish', 'post' => 'Instagram rasmi', 'story' => 'Story rasmi'] as $format => $label)<a href="{{ route('certificates.download', ['certificate' => $certificate, 'format' => $format]) }}" class="rounded-lg border border-forest px-3 py-2 text-sm text-forest">{{ __($label) }}</a>@endforeach</div>
                <button type="button" data-certificate-share="{{ route('certificates.download', ['certificate' => $certificate, 'format' => 'post']) }}" class="rounded-lg bg-forest px-4 py-2 text-sm text-white">{{ __('Rasmni ulashish') }}</button>
                <p data-share-status role="status" class="text-sm text-graysoft"></p>
            @endif
            <a href="{{ route('certificates.verify', $certificate->code) }}" class="inline-block text-sm text-forest">{{ __('Haqiqiyligini tekshirish') }} →</a>
            @if ($can_manage && ! $certificate->revoked_at)<details><summary class="cursor-pointer text-sm text-red-700">{{ __('Sertifikatni bekor qilish') }}</summary><form method="POST" action="{{ route('certificates.revoke', $certificate) }}" class="mt-3 space-y-3">@csrf<label class="block text-sm">{{ __('Sabab') }}<input name="reason" required maxlength="1000" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label><button class="rounded-lg bg-red-700 px-4 py-2 text-white">{{ __('Bekor qilishni tasdiqlash') }}</button></form></details>@endif
        </div>
    </article>
@empty
    <div class="glass-panel p-6"><h2 class="font-semibold">{{ __('Hali sertifikat yo‘q.') }}</h2><p class="mt-2 text-sm text-graysoft">{{ __('Kursni yoki sertifikat beruvchi tajvid imtihonini yakunlang.') }}</p></div>
@endforelse
</div>
<div class="mt-5">{{ $certificates->links() }}</div>
<script src="{{ asset('js/certificates.js').'?v='.filemtime(public_path('js/certificates.js')) }}"></script>
@endsection
