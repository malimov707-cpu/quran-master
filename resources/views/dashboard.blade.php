@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">Assalomu alaykum, {{ auth()->user()->name }}!</h1>
    <p class="mt-1 text-graysoft">Rol: {{ auth()->user()->roles->pluck('label')->implode(', ') }}</p>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
                <div class="text-3xl">{{ $card['icon'] }}</div>
                <h3 class="mt-3 font-semibold text-emerald-deep">{{ $card['title'] }}</h3>
                <p class="mt-1 text-sm text-graysoft">{{ $card['description'] }}</p>
                <p class="mt-3 text-2xl font-bold text-gold">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <p class="mt-10 rounded-xl bg-gold/10 p-4 text-sm text-emerald-deep">
        Kerakli bo‘limni menyudan tanlang. Yangi xabarlar bildirishnomalar bo‘limida ko‘rinadi.
    </p>
@endsection
