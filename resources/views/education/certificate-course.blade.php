@extends('layouts.app')
@section('title', __('Kurs sertifikati'))
@section('content')
<a href="{{ route('certificates.index') }}" class="text-forest">← {{ __('Sertifikatlar') }}</a>
<h1 class="mt-5 text-2xl font-bold text-forest">{{ $course['name'] }}</h1>
<section class="glass-panel mt-5 space-y-5 p-6"><h2 class="font-semibold">{{ __('Sertifikat talablari') }}</h2>
@foreach ($requirements as $requirement)<div class="flex items-center justify-between gap-4"><span>{{ __($requirement['label']) }}</span><strong class="{{ $requirement['done'] === $requirement['total'] ? 'text-forest' : 'text-graysoft' }}">{{ $requirement['done'] }} / {{ $requirement['total'] }}</strong></div>@endforeach
<p class="text-sm text-graysoft">{{ __('Barcha darslar va tekshiruvlar yakunlanishi kerak. Kamida bitta test yoki ustoz tasdiqlagan topshiriq talab qilinadi.') }}</p>
<form method="POST" action="{{ route('certificates.course.claim', $course['id']) }}">@csrf<button @disabled(! $eligible) class="rounded-xl bg-forest px-5 py-3 font-semibold text-white disabled:opacity-40">{{ __('Sertifikat olish') }}</button></form>
</section>
@endsection
