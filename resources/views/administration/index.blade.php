@extends('layouts.app')
@section('title', __($pageTitle))
@section('content')
    <div class="mb-6"><p class="welcome-eyebrow">{{ __('Boshqaruv markazi') }}</p><h1 class="text-3xl font-bold text-emerald-deep">{{ __($pageTitle) }}</h1><p class="mt-2 text-sm text-graysoft">{{ __('Markaz ma’lumotlari va xizmatlar holati') }}</p></div>
    @if ($section === 'users')
        @if (auth()->user()->hasRole('SUPER_ADMIN'))<a href="{{ route('administration.users.create') }}" class="mb-5 inline-block rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Foydalanuvchi yaratish') }} +</a>@endif
        <form method="GET" class="glass-panel mb-5 flex flex-wrap gap-3 p-4"><label class="min-w-0 flex-1"><span class="sr-only">{{ __('Ism yoki email') }}</span><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Ism yoki email') }}" class="w-full"></label><select name="role" aria-label="{{ __('Rol') }}"><option value="">{{ __('Barcha rollar') }}</option>@foreach ($roles as $role)<option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->label }}</option>@endforeach</select><button type="submit" class="rounded-xl bg-forest px-5 text-white">{{ __('Qidirish') }}</button><a href="{{ route('administration', 'users') }}" class="px-3 py-3 text-sm text-forest">{{ __('Tozalash') }}</a></form>
        <div class="glass-panel overflow-x-auto"><table class="text-left"><thead><tr><th class="px-5">{{ __('Foydalanuvchi') }}</th><th>{{ __('Email') }}</th><th>{{ __('Rol') }}</th><th class="px-5">{{ __('Holat') }}</th></tr></thead><tbody class="divide-y divide-gray-200">@forelse ($users as $member)<tr><td class="px-5"><div class="flex items-center gap-3"><span class="avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span><span class="min-w-32 font-semibold">{{ $member->name }}</span></div></td><td class="break-all">{{ $member->email }}</td><td>{{ $member->roles->pluck('label')->join(', ') ?: '—' }}@if (auth()->user()->hasRole('SUPER_ADMIN'))<a href="{{ route('administration.users.edit', $member) }}" class="mt-2 block text-xs font-semibold text-forest underline">{{ __('Rol va bog‘lanishlar') }}</a>@endif</td><td class="px-5"><span class="rounded-full px-3 py-1 text-xs {{ $member->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-red-100 text-red-900' }}">{{ $member->is_active ? __('Faol') : __('Faol emas') }}</span></td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-graysoft">{{ __('Foydalanuvchi topilmadi.') }}</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $users->links() }}</div>
    @elseif ($section === 'roles')
        <div class="grid gap-4 md:grid-cols-2">@foreach ($roles as $role)<article class="glass-panel p-6"><div class="flex items-center justify-between gap-3"><h2 class="font-bold text-forest">{{ $role->label }}</h2><span class="rounded-full bg-gold/15 px-3 py-1 text-xs">{{ $role->users_count }} {{ __('foydalanuvchi') }}</span></div><p class="mt-2 text-xs text-graysoft">{{ $role->name }}</p><details class="mt-4"><summary class="text-sm text-forest">{{ $role->permissions->count() }} {{ __('ruxsat') }}</summary><ul class="mt-3 space-y-2 text-xs text-graysoft">@foreach ($role->permissions as $permission)<li>{{ $permission->label }} <span class="opacity-60">· {{ $permission->name }}</span></li>@endforeach</ul></details></article>@endforeach</div>
    @elseif ($section === 'audit')
        <form method="GET" class="mb-5 flex gap-3"><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Amal bo‘yicha qidirish') }}" aria-label="{{ __('Amal bo‘yicha qidirish') }}"><button class="rounded-xl bg-forest px-5 text-white">{{ __('Qidirish') }}</button></form><div class="glass-panel overflow-x-auto"><table class="text-left"><thead><tr><th class="px-5">{{ __('Vaqt') }}</th><th>{{ __('Foydalanuvchi') }}</th><th>{{ __('Amal') }}</th><th class="px-5">{{ __('Obyekt') }}</th></tr></thead><tbody class="divide-y divide-gray-200">@forelse ($logs as $log)<tr><td class="whitespace-nowrap px-5">{{ $log->created_at }}</td><td>{{ $log->name ?? '—' }}</td><td>{{ $log->action }}</td><td class="break-all px-5">{{ class_basename($log->subject_type ?? '') }} {{ $log->subject_id }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-graysoft">{{ __('Faoliyat yozuvlari topilmadi.') }}</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $logs->links() }}</div>
    @elseif ($section === 'ai')
        <div class="grid gap-4 md:grid-cols-2">
            <article class="glass-panel p-6"><x-icon name="chart" class="mb-4 text-gold"/><h2 class="font-bold text-forest">{{ __('Nutqni aniqlash') }}</h2><p class="mt-3 text-sm text-graysoft">{{ $speechConfigured ? __('Provayder kaliti sozlangan.') : __('Provayder kaliti sozlanmagan.') }}</p></article>
            <article class="glass-panel p-6"><x-icon name="book" class="mb-4 text-gold"/><h2 class="font-bold text-forest">{{ __('AI yordamchi') }}</h2><p class="mt-3 text-sm text-graysoft">{{ $aiConfigured ? __('Ulandi · ').$aiModel : __('OpenRouter kaliti sozlanmagan.') }}</p></article>
        </div>
        <section class="glass-panel mt-4 overflow-hidden">
            <div class="border-b border-black/5 px-5 py-4"><h2 class="font-bold text-forest">{{ __('AI yordamchi') }}</h2><p class="text-xs text-graysoft">{{ __('CEO, administrator, ustoz, mentor va ota-ona uchun boshqaruv yordamchisi.') }}</p></div>
            <div id="admin-ai-messages" class="h-80 space-y-3 overflow-y-auto bg-white/40 p-5" aria-live="polite"><p class="text-sm text-graysoft">{{ __('Savolingizni yuboring. AI platforma boshqaruvi bo‘yicha qisqa tavsiya beradi.') }}</p></div>
            <form id="admin-ai-form" class="flex gap-3 border-t border-black/5 p-4">
                @csrf
                <input id="admin-ai-message" name="message" maxlength="4000" required placeholder="{{ __('Masalan: o‘quvchilar uchun bugungi ustuvor vazifani tuz') }}" class="min-w-0 flex-1">
                <button id="admin-ai-submit" type="submit" class="rounded-xl bg-forest px-5 text-sm font-semibold text-white">{{ __('Yuborish') }}</button>
            </form>
        </section>
        <section class="glass-panel mt-4 p-5">
            <h2 class="font-bold text-forest">{{ __('Talaba premiumlari') }}</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2" id="student-premiums">
                @foreach ($studentUsers as $student)
                    <label class="flex items-center justify-between gap-3 rounded-xl border border-black/5 bg-white/50 p-3 text-sm"><span><strong>{{ $student->name }}</strong><small class="block text-graysoft">{{ $student->email }}</small></span><input type="checkbox" class="premium-toggle h-4 w-4" data-user-id="{{ $student->id }}" @checked($student->ai_premium) aria-label="{{ __('Premium') }}"></label>
                @endforeach
            </div>
        </section>
        <script>
            (() => {
                const form = document.getElementById('admin-ai-form');
                const input = document.getElementById('admin-ai-message');
                const messages = document.getElementById('admin-ai-messages');
                const submit = document.getElementById('admin-ai-submit');
                if (!form || !input || !messages) return;
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const message = input.value.trim();
                    if (!message) return;
                    const question = document.createElement('p');
                    question.className = 'ml-auto max-w-[85%] rounded-xl bg-forest px-4 py-3 text-sm text-white';
                    question.textContent = message;
                    messages.append(question);
                    input.value = '';
                    submit.disabled = true;
                    submit.textContent = '{{ __('Javob kutilmoqda...') }}';
                    try {
                        const response = await fetch('{{ route('administration.ai.chat') }}', {
                            method: 'POST',
                            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                            body: JSON.stringify({message})
                        });
                        const data = await response.json();
                        const answer = document.createElement('p');
                        answer.className = 'max-w-[85%] rounded-xl border border-gold/30 bg-white px-4 py-3 text-sm text-emerald-deep';
                        answer.textContent = data.answer || data.message || '{{ __('Xatolik yuz berdi.') }}';
                        messages.append(answer);
                    } catch (error) {
                        const answer = document.createElement('p');
                        answer.className = 'max-w-[85%] rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700';
                        answer.textContent = '{{ __('AI provayderga ulanishda xatolik.') }}';
                        messages.append(answer);
                    } finally {
                        submit.disabled = false;
                        submit.textContent = '{{ __('Yuborish') }}';
                        messages.scrollTop = messages.scrollHeight;
                    }
                });
                document.querySelectorAll('.premium-toggle').forEach((toggle) => {
                    toggle.addEventListener('change', async () => {
                        toggle.disabled = true;
                        await fetch(`{{ url('/administration/ai/users') }}/${toggle.dataset.userId}/premium`, {
                            method: 'PATCH',
                            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                            body: JSON.stringify({enabled: toggle.checked})
                        });
                        toggle.disabled = false;
                    });
                });
            })();
        </script>
    @elseif ($section === 'payments')
        {{-- Moliyaviy xulosa --}}
        <section class="glass-panel p-6" aria-labelledby="payments-summary">
            <h2 id="payments-summary" class="font-bold text-forest">{{ __('Moliyaviy holat') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-cream p-5 text-center">
                    <p class="text-2xl font-extrabold text-emerald-deep">{{ number_format($paymentsSummary['this_month_som']) }}</p>
                    <p class="mt-1 text-xs text-graysoft">{{ __('Bu oy tushumi (so’m)') }}</p>
                </div>
                <div class="rounded-xl bg-cream p-5 text-center">
                    <p class="text-2xl font-extrabold text-emerald-deep">{{ number_format($paymentsSummary['this_month_count']) }}</p>
                    <p class="mt-1 text-xs text-graysoft">{{ __('Bu oy to’lovlar') }}</p>
                </div>
                <div class="rounded-xl bg-cream p-5 text-center">
                    <p class="text-2xl font-extrabold text-emerald-deep">{{ number_format($paymentsSummary['active_subscriptions']) }}</p>
                    <p class="mt-1 text-xs text-graysoft">{{ __('Faol obunalar') }}</p>
                </div>
            </div>
        </section>

        {{-- Oxirgi tranzaksiyalar + oylik dinamika --}}
        <section class="glass-panel mt-4 p-6" aria-labelledby="payments-list">
            <h2 id="payments-list" class="font-bold text-forest">{{ __('Oxirgi tranzaksiyalar') }}</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-graysoft"><th class="py-2">{{ __('Foydalanuvchi') }}</th><th>{{ __('Reja') }}</th><th>{{ __('Summa') }}</th><th>{{ __('Provayder') }}</th><th>{{ __('Holat') }}</th><th>{{ __('Sana') }}</th></tr></thead>
                    <tbody>
                        @forelse ($paymentsList as $payment)
                            <tr class="border-t border-gray-100">
                                <td class="py-3 font-medium">{{ $payment->user?->name }}</td>
                                <td>{{ $payment->plan?->name ?? '—' }}</td>
                                <td>{{ number_format($payment->amount_tiyin / 100) }} {{ __('so’m') }}</td>
                                <td class="uppercase">{{ $payment->provider }}</td>
                                <td><span class="rounded-full px-2 py-0.5 text-xs {{ $payment->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $payment->status }}</span></td>
                                <td class="text-graysoft">{{ $payment->created_at->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-graysoft">{{ __('Tranzaksiya yo‘q') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Oylik o'quv to'lovlari (fee_items) --}}
        <section class="glass-panel mt-4 p-6" aria-labelledby="fees-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="fees-heading" class="font-bold text-forest">{{ __('Oylik o‘quv to‘lovlari') }}</h2>
                <form method="POST" action="{{ route('administration.payments.generate') }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-emerald-deep px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest">
                        ⟳ {{ __('Joriy oy invoyslarini yaratish') }}
                    </button>
                </form>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-cream p-4 text-center"><p class="text-xl font-extrabold text-forest">{{ number_format($feesStats['paid_som']) }}</p><p class="text-[11px] text-graysoft">{{ __('To‘langan (so‘m)') }}</p></div>
                <div class="rounded-xl bg-cream p-4 text-center"><p class="text-xl font-extrabold text-emerald-deep">{{ number_format($feesStats['pending_som']) }}</p><p class="text-[11px] text-graysoft">{{ __('To‘lanmagan (so‘m)') }}</p></div>
                <div class="rounded-xl bg-cream p-4 text-center"><p class="text-xl font-extrabold text-amber-600">{{ $feesStats['pending_count'] }}</p><p class="text-[11px] text-graysoft">{{ __('Kutayotgan yozuvlar') }}</p></div>
                <div class="rounded-xl bg-cream p-4 text-center"><p class="text-xl font-extrabold text-red-600">{{ $feesStats['overdue_count'] }}</p><p class="text-[11px] text-graysoft">{{ __('Muddati o‘tgan') }}</p></div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-graysoft"><th class="py-2">{{ __('O‘quvchi') }}</th><th>{{ __('Guruh') }}</th><th>{{ __('Oy') }}</th><th>{{ __('Summa') }}</th><th>{{ __('To‘langan') }}</th><th>{{ __('Muddat') }}</th><th>{{ __('Amallar') }}</th></tr></thead>
                    <tbody>
                        @forelse ($fees as $fee)
                            @php $due = $fee->amount_tiyin - $fee->paid_amount_tiyin; @endphp
                            <tr class="border-t border-gray-100">
                                <td class="py-3"><strong>{{ $fee->student?->name }}</strong><br><small class="text-graysoft">{{ $fee->student?->email }}</small></td>
                                <td>{{ $fee->group?->name ?? $fee->course?->name }}</td>
                                <td>{{ str_pad($fee->month, 2, '0', STR_PAD_LEFT) }}.{{ $fee->year }}</td>
                                <td>{{ number_format($fee->amount_tiyin / 100) }}</td>
                                <td>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold
                                        {{ $fee->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($fee->status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') }}">
                                        {{ match ($fee->status) { 'paid' => __('To‘langan'), 'partial' => __('Qismiy'), 'cancelled' => __('Bekor'), default => __('Kutmoqda') } }}
                                    </span>
                                    @if ($due > 0)<small class="block text-[10px] text-graysoft">{{ __('qoldiq') }}: {{ number_format($due / 100) }}</small>@endif
                                </td>
                                <td class="text-graysoft">{{ $fee->due_date?->format('d.m') ?? '—' }}@if ($fee->due_date && $fee->due_date->isPast() && $due > 0) <span class="text-red-600 font-bold" title="{{ __('Muddati o‘tgan') }}">⚠</span>@endif</td>
                                <td>
                                    @if ($due > 0)
                                        <form method="POST" action="{{ route('administration.payments.receive', $fee) }}" class="flex items-center gap-1">
                                            @csrf
                                            <input type="number" name="amount_som" min="1" max="{{ (int) ($due / 100) }}" value="{{ (int) ($due / 100) }}" aria-label="{{ __('To‘lov summasi') }}" class="!min-h-0 w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
                                            <button type="submit" class="rounded-lg bg-forest px-2.5 py-1.5 text-xs font-bold text-white" title="{{ __('To‘lov qabul qilish') }}">✓</button>
                                        </form>
                                        <form method="POST" action="{{ route('administration.payments.remind', $fee) }}" class="mt-1">
                                            @csrf
                                            <button type="submit" class="text-[10px] text-graysoft hover:text-forest hover:underline">🔔 {{ __('Eslatma yuborish') }}</button>
                                        </form>
                                    @else
                                        <span class="text-emerald-600" title="{{ __('To‘liq to‘langan') }}">✓</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-graysoft">{{ __('To‘lov yozuvi yo‘q. Yuqoridagi tugma bilan joriy oy invoyslarini yarating.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $fees->links() }}
        </section>
    @else
        <section class="glass-panel max-w-3xl p-6"><h2 class="font-bold text-forest">{{ __('Platforma ma’lumotlari') }}</h2><dl class="mt-5 divide-y divide-gray-200 text-sm"><div class="flex flex-wrap justify-between gap-3 py-4"><dt class="text-graysoft">{{ __('Platforma nomi') }}</dt><dd>{{ config('app.name') }}</dd></div><div class="flex justify-between gap-3 py-4"><dt class="text-graysoft">{{ __('Vaqt mintaqasi') }}</dt><dd>{{ config('app.display_timezone', 'Asia/Tashkent') }}</dd></div><div class="flex justify-between gap-3 py-4"><dt class="text-graysoft">{{ __('Til') }}</dt><dd>{{ app()->getLocale() }}</dd></div></dl><p class="mt-5 text-xs text-graysoft">{{ __('Xizmat kalitlari xavfsizlik sababli interfeysda ko‘rsatilmaydi.') }}</p></section>
    @endif
@endsection
