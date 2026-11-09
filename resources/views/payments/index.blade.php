@extends('layouts.app')

@section('title', __('To‘lovlar'))

@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">{{ __('To‘lovlar') }}</h1>
    <p class="mt-1 text-sm text-graysoft">
        {{ $isParent ? __('Farzandingizning oylik to‘lovlari va AI obunasi bitta joyda.') : __('Oylik to‘lovlaringiz va AI obunangiz bitta joyda.') }}
    </p>

    {{-- Farzand tanlash (ota-ona uchun) --}}
    @if ($isParent && $targets->count() > 1)
        <div class="glass-panel mt-4 flex flex-wrap items-center gap-3 p-4">
            <span class="text-sm font-medium text-forest">{{ __('Farzand:') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach ($targets as $target)
                    <a href="?student_id={{ $target->id }}"
                       class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $selected->id === $target->id ? 'bg-forest text-white' : 'bg-cream text-forest hover:bg-gray-100' }}">
                        {{ $target->name }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-5 grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        {{-- TAB 1: Oylik o'quv to'lovi --}}
        <section class="glass-panel p-6" aria-labelledby="tuition-heading">
            <h2 id="tuition-heading" class="flex items-center gap-3 font-bold text-emerald-deep"><x-icon3d variant="emerald" :size="38"><x-art3d name="mushaf"/></x-icon3d> {{ __('Oylik o‘quv to‘lovi') }}</h2>

            @if ($tuition)
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-cream p-4 text-center">
                        <p class="text-2xl font-extrabold text-forest">{{ number_format($tuition['total_paid_som']) }}</p>
                        <p class="text-xs text-graysoft">{{ __('To‘langan (so‘m)') }}</p>
                    </div>
                    <div class="rounded-xl bg-cream p-4 text-center {{ $tuition['has_overdue'] ? 'ring-2 ring-red-400' : '' }}">
                        <p class="text-2xl font-extrabold {{ $tuition['has_overdue'] ? 'text-red-600' : 'text-emerald-deep' }}">{{ number_format($tuition['total_pending_som']) }}</p>
                        <p class="text-xs text-graysoft">{{ $tuition['has_overdue'] ? __('Muddati o‘tgan!') : __('To‘lanmagan') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-2">
                    @forelse ($tuition['items'] as $item)
                        <div class="flex items-center justify-between rounded-xl bg-cream p-4">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-forest">{{ $item['course'] }}</p>
                                <p class="text-[11px] text-graysoft">{{ str_pad($item['month'], 2, '0', STR_PAD_LEFT) }}.{{ $item['year'] }}
                                    @if ($item['due_date']) · {{ __('muddat') }}: {{ $item['due_date'] }} @endif
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-emerald-deep">{{ number_format($item['amount_som']) }} {{ __('so’m') }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold
                                    {{ $item['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($item['status'] === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') }}">
                                    {{ match ($item['status']) { 'paid' => __('To‘langan'), 'partial' => __('Qismiy'), default => __('Kutmoqda') } }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-graysoft">{{ __('To‘lov yozuvi yo‘q.') }}</p>
                    @endforelse
                </div>
            @else
                <p class="mt-4 text-sm text-graysoft">{{ __('Ma’lumot yo‘q.') }}</p>
            @endif
        </section>

        {{-- TAB 2: AI obuna --}}
        <section class="glass-panel p-6" aria-labelledby="ai-sub-heading">
            <h2 id="ai-sub-heading" class="flex items-center gap-3 font-bold text-emerald-deep"><x-icon3d variant="navy" :size="38"><x-art3d name="mic"/></x-icon3d> {{ __('AI obunasi') }}</h2>

            @if ($subscription)
                <div class="mt-4 rounded-xl bg-cream p-4">
                    <p class="text-sm font-semibold text-forest">{{ $subscription->plan->name }}</p>
                    <p class="mt-1 text-xs text-graysoft">{{ __('Muddat') }}: {{ $subscription->expires_at->format('d.m.Y') }} {{ __('gacha') }}</p>
                    <span class="mt-3 inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ __('Premium faol') }}</span>
                </div>
            @else
                <div class="mt-4 rounded-xl bg-cream p-4">
                    <p class="text-sm text-graysoft">{{ __('Hozir bepul rejada — AI chat kunda 20 savol.') }}</p>
                </div>
            @endif

            <div class="mt-4 space-y-3">
                @foreach ($plans as $plan)
                    @if ($plan->price_som > 0)
                        <div class="rounded-xl border border-gray-200 p-4 {{ $subscription?->plan_id === $plan->id ? 'ring-2 ring-gold' : '' }}">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-forest">{{ $plan->name }}</p>
                                <p class="font-bold text-emerald-deep">{{ number_format($plan->price_som) }} {{ __('so’m') }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
                <a href="{{ route('subscriptions.index') }}" class="hero-goal-btn block text-center">{{ __('Rejalarni boshqarish') }} →</a>
            </div>
        </section>
    </div>
@endsection
