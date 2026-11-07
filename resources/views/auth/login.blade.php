@extends('layouts.auth')

@section('title', __('Kirish — Qur\'on AI Ustoz'))

@section('content')
    @if (session('status'))<p role="status" class="mb-4 rounded-xl bg-cream p-4 text-sm text-forest">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        @if ($errors && $errors->any())
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-emerald-deep">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}"
                   class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-forest focus:ring-1 focus:ring-forest focus:outline-none">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-emerald-deep">{{ __('Parol') }}</label>
            <input id="password" name="password" type="password" required
                   class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-forest focus:ring-1 focus:ring-forest focus:outline-none">
        </div>
        <p class="text-right text-sm"><a href="{{ route('password.request') }}" class="text-forest underline">{{ __('Parolni unutdingizmi?') }}</a></p>
        <button type="submit"
                class="w-full rounded-lg bg-emerald-deep py-2.5 font-semibold text-cream transition hover:bg-forest">
            {{ __('Tizimga kirish') }}
        </button>
        <p class="text-center text-sm text-graysoft">{{ __('Akkauntingiz yo‘qmi?') }} <a href="{{ route('register') }}" class="font-semibold text-forest underline">{{ __('Ro‘yxatdan o‘tish') }}</a></p>
        <p class="text-center text-sm"><a href="{{ route('prayer-times') }}" class="text-forest underline">{{ __('O‘zbekiston namoz vaqtlari') }}</a></p>
    </form>
@endsection
