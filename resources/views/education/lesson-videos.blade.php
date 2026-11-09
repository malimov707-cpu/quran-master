<section data-lesson-videos data-lesson="{{ $lesson->id }}" class="rounded-2xl bg-white p-5 ring-1 ring-black/5">
    <h2 class="text-xl font-bold text-forest">{{ __('Video darslar') }}</h2>
    <p class="mt-2 text-sm text-graysoft">{{ __('Ko‘rgan joyingiz saqlanadi. Keyingi safar shu joydan davom etasiz.') }}</p>
    <div data-videos-status role="status" class="mt-4 text-sm text-graysoft">{{ __('Yuklanmoqda...') }}</div>
    <div data-videos-list class="mt-5 space-y-6"></div>
    @if ($canUpdate && auth()->user()->hasPermission('materials.manage'))
    <details class="mt-6 border-t border-gray-200 pt-5"><summary class="cursor-pointer font-semibold text-forest">{{ __('Video qo‘shish') }}</summary>
        <form data-video-form class="mt-4 space-y-4">
            <label class="block text-sm">{{ __('Video nomi') }}<input name="title" required maxlength="255" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label>
            <label class="block text-sm">{{ __('Manba') }}<select name="provider" class="mt-2 w-full rounded-lg border border-gray-200 p-3"><option value="upload">{{ __('MP4 video yuklash') }}</option><option value="youtube">YouTube embed</option></select></label>
            <label data-file-field class="block text-sm">{{ __('MP4 (H.264/AAC), 256 MB gacha') }}<input name="file" type="file" accept="video/mp4,.mp4" class="mt-2 block w-full"></label>
            <label data-url-field hidden class="block text-sm">YouTube URL<input name="url" type="url" placeholder="https://www.youtube.com/watch?v=…" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label>
            <label class="block text-sm">{{ __('Davomiylik (soniya)') }}<input name="duration" required type="number" min="1" max="21600" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></label>
            <label class="flex items-center gap-3 text-sm"><input name="offline" type="checkbox" checked>{{ __('Mobilga offline yuklashga ruxsat berish') }}</label>
            <button type="submit" class="rounded-lg bg-forest px-5 py-3 text-white">{{ __('Videoni saqlash') }}</button>
            <p data-upload-status role="status" class="text-sm text-graysoft"></p>
        </form>
    </details>
    @endif
</section>
<script type="module" src="{{ asset('js/lesson-videos.js').'?v='.filemtime(public_path('js/lesson-videos.js')) }}"></script>
