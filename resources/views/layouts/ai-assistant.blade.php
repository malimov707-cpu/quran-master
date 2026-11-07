@php
    $aiAssistant = app(\App\Services\AI\RoleAwareAiAssistant::class);
    $aiPrompts = $aiAssistant->suggestedPrompts(auth()->user());
    $aiHasAccess = $aiAssistant->hasAccess(auth()->user());
@endphp

@if ($aiHasAccess)
    <aside class="fixed bottom-5 right-5 z-40 w-[min(22rem,calc(100vw-2rem))]" aria-label="{{ __('AI yordamchi') }}">
        <details class="rounded-2xl border border-emerald-deep/10 bg-white shadow-xl">
            <summary class="cursor-pointer list-none rounded-2xl bg-forest px-5 py-3.5 font-semibold text-white">
                {{ __('AI yordamchi') }}
            </summary>
            <div class="p-4">
                <div class="flex flex-wrap gap-2" id="role-ai-prompts">
                    @foreach ($aiPrompts as $prompt)
                        <button type="button" class="rounded-full bg-emerald-soft px-3 py-1.5 text-left text-xs text-emerald-deep role-ai-prompt">{{ $prompt }}</button>
                    @endforeach
                </div>
                <div id="role-ai-answer" class="mt-3 max-h-52 space-y-2 overflow-y-auto text-sm" aria-live="polite"></div>
                <form id="role-ai-form" class="mt-3 flex gap-2">
                    @csrf
                    <input id="role-ai-message" maxlength="4000" required class="min-w-0 flex-1 rounded-xl border border-black/10 px-3 py-2 text-sm" placeholder="{{ __('Savolingizni yozing...') }}">
                    <button id="role-ai-submit" class="rounded-xl bg-gold px-4 text-sm font-semibold text-emerald-deep" type="submit">{{ __('Yuborish') }}</button>
                </form>
            </div>
        </details>
    </aside>
    <script>
        (() => {
            const form = document.getElementById('role-ai-form');
            const input = document.getElementById('role-ai-message');
            const output = document.getElementById('role-ai-answer');
            const submit = document.getElementById('role-ai-submit');
            if (!form || !input || !output || !submit) return;

            document.querySelectorAll('.role-ai-prompt').forEach((button) => {
                button.addEventListener('click', () => {
                    input.value = button.textContent.trim();
                    input.focus();
                });
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const message = input.value.trim();
                if (!message) return;
                submit.disabled = true;
                submit.textContent = '...';
                try {
                    const response = await fetch('{{ route('administration.ai.chat') }}', {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                        body: JSON.stringify({message})
                    });
                    const data = await response.json();
                    const paragraph = document.createElement('p');
                    paragraph.className = 'rounded-xl bg-emerald-soft px-3 py-2 text-emerald-deep';
                    paragraph.textContent = data.answer || data.message || '{{ __('Javob olinmadi.') }}';
                    output.append(paragraph);
                    input.value = '';
                } catch (error) {
                    const paragraph = document.createElement('p');
                    paragraph.className = 'rounded-xl bg-red-50 px-3 py-2 text-red-700';
                    paragraph.textContent = '{{ __('AI provayderga ulanishda xatolik.') }}';
                    output.append(paragraph);
                } finally {
                    submit.disabled = false;
                    submit.textContent = '{{ __('Yuborish') }}';
                    output.scrollTop = output.scrollHeight;
                }
            });
        })();
    </script>
@endif
