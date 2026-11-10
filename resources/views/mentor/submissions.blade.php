@extends('layouts.app')

@section('title', __('Tekshiruv'))

@section('content')
    <div class="flex items-center gap-3">
        <x-icon3d variant="emerald" :size="44" class="icon3d-float"><x-art3d name="mic" class="w-8 h-8"/></x-icon3d>
        <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Kutayotgan topshirishlar') }}</h1>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl bg-ivory shadow-sm ring-1 ring-black/5">
        <table class="w-full text-sm">
            <thead class="bg-cream text-left text-xs uppercase text-graysoft">
                <tr>
                    <th class="px-4 py-3">{{ __('O\'quvchi') }}</th>
                    <th class="px-4 py-3">{{ __('Topshiriq') }}</th>
                    <th class="px-4 py-3">{{ __('Holat') }}</th>
                    <th class="px-4 py-3">{{ __('Sana') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $s)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-medium">{{ $s->student?->name }}</td>
                        <td class="px-4 py-3">{{ $s->assignment?->title }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $s->status === 'resubmit' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">{{ $s->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-graysoft">{{ $s->submitted_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('mentor.review-studio', $s) }}" class="font-medium text-emerald-deep hover:underline">{{ __('Tekshirish →') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-graysoft">{{ __('Yangi topshirish yo\'q ✓') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
