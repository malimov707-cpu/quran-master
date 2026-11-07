@extends('layouts.auth')
@section('title', __('Parolni tiklash'))
@section('content')
    <h2 class="text-xl font-bold text-forest">{{ __('Parolni unutdingizmi?') }}</h2>
    <p class="mt-3 text-sm leading-6 text-graysoft">{{ __('Email manzilingizni kiriting. Parolni yangilash uchun havola yuboriladi.') }}</p>
    @unless ($mailReady)<p role="status" class="mt-4 rounded-xl bg-cream p-4 text-sm text-graysoft">{{ __('Email orqali tiklash vaqtincha mavjud emas. Administratorga murojaat qiling.') }}</p>@endunless
    @if (session('status'))<p role="status" class="mt-4 rounded-xl bg-cream p-4 text-sm text-forest">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
        @csrf
        <label class="block"><span class="mb-2 block text-sm font-semibold">{{ __('Email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="255" class="w-full rounded-xl px-3 py-2"></label>
        @error('email')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
        <button type="submit" @disabled(! $mailReady) class="disabled:opacity-50 w-full rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Tiklash havolasini yuborish') }}</button>
    </form>
    <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="text-forest underline">{{ __('Tizimga kirish') }}</a></p>
@endsection
