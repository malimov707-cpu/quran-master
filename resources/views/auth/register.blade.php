@extends('layouts.auth')

@section('title', __('Ro‘yxatdan o‘tish'))

@section('content')
    <h2 class="mb-2 text-xl font-bold text-emerald-deep">{{ __('Ro‘yxatdan o‘tish') }}</h2>
    <p class="mb-6 text-sm text-graysoft">{{ __('O‘quvchi akkauntini yarating va Qur’on o‘rganishni boshlang.') }}</p>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        @foreach (['name' => ['Ism va familiya', 'text', 'name'], 'email' => ['Email', 'email', 'email'], 'password' => ['Parol', 'password', 'new-password'], 'password_confirmation' => ['Parolni tasdiqlang', 'password', 'new-password']] as $field => [$label, $type, $autocomplete])
            <div>
                <label for="{{ $field }}" class="mb-1 block text-sm font-medium text-emerald-deep">{{ __($label) }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" required
                       @if ($type !== 'password') value="{{ old($field) }}" maxlength="255" @else minlength="8" @endif
                       @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-forest focus:ring-1 focus:ring-forest focus:outline-none">
                @error($field)<p id="{{ $field }}-error" role="alert" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <p class="text-xs text-graysoft">{{ __('Parol kamida 8 ta belgidan iborat bo‘lsin.') }}</p>
        @if ($errors->has('password_confirmation'))<p role="alert" class="text-sm text-red-700">{{ $errors->first('password_confirmation') }}</p>@endif
        <button type="submit" class="w-full rounded-lg bg-emerald-deep py-2.5 font-semibold text-cream transition hover:bg-forest">{{ __('Akkaunt yaratish') }}</button>
        <p class="text-center text-sm text-graysoft">{{ __('Akkauntingiz bormi?') }} <a href="{{ route('login') }}" class="font-semibold text-forest underline">{{ __('Tizimga kirish') }}</a></p>
        <p class="text-center text-sm"><a href="{{ route('prayer-times') }}" class="text-forest underline">{{ __('O‘zbekiston namoz vaqtlari') }}</a></p>
    </form>
@endsection
