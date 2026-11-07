@extends('layouts.auth')
@section('title', __('Yangi parol'))
@section('content')
    <h2 class="text-xl font-bold text-forest">{{ __('Yangi parol belgilang') }}</h2>
    <p class="mt-3 text-sm leading-6 text-graysoft">{{ __('Parol yangilangach, barcha qurilmalarda qayta tizimga kirishingiz kerak bo‘ladi.') }}</p>
    <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @if ($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <label class="block"><span class="mb-2 block text-sm font-semibold">{{ __('Email') }}</span><input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" maxlength="255" class="w-full rounded-xl px-3 py-2"></label>
        @foreach (['password' => 'Yangi parol', 'password_confirmation' => 'Parolni tasdiqlang'] as $field => $label)<label class="block"><span class="mb-2 block text-sm font-semibold">{{ __($label) }}</span><input type="password" name="{{ $field }}" required autocomplete="new-password" minlength="8" class="w-full rounded-xl px-3 py-2"></label>@endforeach
        <button type="submit" class="w-full rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Parolni yangilash') }}</button>
    </form>
    <p class="mt-5 text-center text-sm"><a href="{{ route('password.request') }}" class="text-forest underline">{{ __('Yangi tiklash havolasini so‘rash') }}</a></p>
@endsection
