@extends('layouts.app')

@section('title', $surah->name_uz.' — Qur\'on')

@section('content')
    <div class="mx-auto max-w-3xl" data-reader="{{ auth()->id() }}">
        <a href="{{ route('quran.surahs') }}" class="text-sm text-graysoft hover:text-emerald-deep">{{ __('← Suralar') }}</a>

        <div class="mt-4 rounded-2xl bg-emerald-deep p-6 text-center text-cream">
            <p class="ayah-text !text-cream text-3xl">{{ $surah->name_arabic }}</p>
            <p class="mt-2 text-sm text-cream/70">{{ $surah->name_uz }} · {{ $surah->ayah_count }} oyat</p>
        </div>

        <div class="reader-toolbar glass-panel" aria-label="{{ __('O‘qish sozlamalari') }}"><span class="mr-auto text-sm font-semibold">{{ __('O‘qish qulayligi') }}</span><button type="button" data-font-step="-2" aria-label="{{ __('Matnni kichraytirish') }}">A−</button><button type="button" data-font-step="2" aria-label="{{ __('Matnni kattalashtirish') }}">A+</button><button type="button" data-theme-toggle class="px-3 text-sm">{{ __('☾ Rejim') }}</button></div>
        <div class="mt-6 space-y-4">
            @foreach ($ayahs as $ayah)
                <div id="ayah-{{ $ayah->number_in_surah }}" data-ayah-number="{{ $ayah->number_in_surah }}" data-surah-id="{{ $surah->id }}" class="reader-ayah rounded-xl bg-ivory p-5 shadow-sm ring-1 ring-black/5">
                    <div class="flex items-center justify-between">
                        <span class="rounded-full bg-gold/15 px-3 py-1 text-xs font-semibold text-emerald-deep">
                            {{ $surah->number }}:{{ $ayah->number_in_surah }}
                        </span>
                        <span class="text-xs text-graysoft">juz {{ $ayah->juz }} · sahifa {{ $ayah->page }}</span>
                    </div>
                    <button type="button" class="bookmark-button mt-3" data-play-ayah="{{ $ayah->number_in_surah }}" data-surah="{{ $surah->id }}">▶ {{ __('Tinglash') }}</button>
                    <button type="button" class="bookmark-button mt-3" data-bookmark="{{ $surah->number }}:{{ $ayah->number_in_surah }}" aria-pressed="false">{{ __('☆ Belgilash') }}</button>
                    @if (auth()->user()->hasRole('STUDENT'))<form method="POST" action="{{ route('learning.store') }}" class="mt-2">@csrf<input type="hidden" name="ayah_id" value="{{ $ayah->id }}"><button type="submit" class="bookmark-button">{{ __('Takrorlash rejamga qo‘shish') }}</button></form>@endif
                    <p lang="ar" dir="rtl" class="ayah-text mt-4">{{ $ayah->text_arabic }}</p>
                    @if ($ayah->text_uz)
                        <p class="mt-3 border-t border-dashed border-gray-200 pt-3 text-sm text-gray-700">{{ $ayah->text_uz }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @include('quran.source-notice')
@endsection
