@extends('layouts.auth')

@section('title', __('Kirish — Qur\'on AI Ustoz'))

@section('content')
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
        <button type="submit"
                class="w-full rounded-lg bg-emerald-deep py-2.5 font-semibold text-cream transition hover:bg-forest">
            Tizimga kirish
        </button>
        <p class="text-center text-xs text-graysoft">
            Demo: mentor@markaz.uz / Parol!123
        </p>
    </form>
@endsection
