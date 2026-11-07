<section class="glass-panel mb-6 p-5 sm:p-6" data-prayer-widget data-endpoint="{{ route('api.prayer-times') }}" aria-label="{{ __('Namoz vaqtlari') }}">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h2 class="text-xl font-bold text-forest">{{ __('Namoz vaqtlari') }}</h2><p class="mt-1 text-xs text-graysoft">{{ __('O‘zbekiston · Toshkent vaqti (UTC+5)') }} <span data-prayer-date></span></p></div>
        <label class="flex items-center gap-3 text-sm"><span>{{ __('Shahar') }}</span><select data-prayer-city aria-label="{{ __('Namoz vaqtlari uchun shahar') }}">@foreach (config('prayer_times.cities') as $id => $city)<option value="{{ $id }}">{{ __($city['name']) }}</option>@endforeach</select></label>
    </div>
    <p class="mt-4 text-sm text-graysoft" role="status" data-prayer-status>{{ __('Namoz vaqtlari yuklanmoqda…') }}</p>
    <div class="mt-5 grid grid-cols-3 gap-2 {{ auth()->check() ? 'lg:grid-cols-6' : '' }}" data-prayer-times hidden></div>
    <p class="mt-4 text-sm font-semibold text-forest" data-prayer-next hidden></p>
    <p class="mt-3 text-xs leading-5 text-graysoft" data-prayer-source hidden><span data-prayer-note></span> <a data-prayer-source-link class="underline" target="_blank" rel="noopener noreferrer"></a></p>
    <button type="button" data-prayer-retry class="bookmark-button mt-4" hidden>{{ __('Qayta urinish') }}</button>
    <noscript><p class="mt-4 text-sm">{{ __('Namoz vaqtlarini ko‘rish uchun JavaScriptni yoqing.') }} <a href="https://islom.uz/taqvim" class="underline">Islom.uz</a></p></noscript>
</section>
