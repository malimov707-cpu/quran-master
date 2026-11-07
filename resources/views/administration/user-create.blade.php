@extends('layouts.app')
@section('title', __('Foydalanuvchi yaratish'))
@section('content')
    @php
        $selectedRoles = old('role_ids', []);
        $selectedRoles = is_array($selectedRoles) ? $selectedRoles : [];
    @endphp
    <a href="{{ route('administration', 'users') }}" class="text-sm text-forest">← {{ __('Foydalanuvchilar') }}</a>
    <h1 class="mt-4 text-2xl font-bold text-forest">{{ __('Foydalanuvchi yaratish') }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ __('Akkaunt uchun rol belgilang. Foydalanuvchi shu email va parol bilan umumiy login orqali kiradi.') }}</p>
    <form method="POST" action="{{ route('administration.users.store') }}" class="glass-panel mt-6 max-w-2xl space-y-5 p-5 sm:p-7">
        @csrf
        @if ($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @foreach (['name' => ['Ism va familiya', 'text'], 'email' => ['Email', 'email'], 'password' => ['Parol', 'password'], 'password_confirmation' => ['Parolni tasdiqlang', 'password']] as $field => [$label, $type])
            <label class="block"><span class="mb-2 block text-sm font-semibold">{{ __($label) }}</span><input name="{{ $field }}" type="{{ $type }}" required class="w-full" @if ($type !== 'password') value="{{ old($field) }}" maxlength="255" @else minlength="8" autocomplete="new-password" @endif></label>
        @endforeach
        <p class="text-xs text-graysoft">{{ __('Parol kamida 8 ta belgidan iborat bo‘lsin.') }}</p>
        <fieldset><legend class="mb-3 font-semibold">{{ __('Rollar') }}</legend><div class="grid gap-3 sm:grid-cols-2">@foreach ($roles as $role)<label class="flex items-center gap-3 rounded-xl bg-cream p-3"><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoles))><span class="text-sm">{{ __($role->label) }}</span></label>@endforeach</div></fieldset>
        <button type="submit" class="rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Akkaunt yaratish') }}</button>
    </form>
@endsection
