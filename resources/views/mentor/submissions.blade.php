@extends('layouts.app')

@section('title', 'Tekshiruv')

@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">Kutayotgan topshirishlar</h1>

    <div class="mt-6 overflow-hidden rounded-2xl bg-ivory shadow-sm ring-1 ring-black/5">
        <table class="w-full text-sm">
            <thead class="bg-cream text-left text-xs uppercase text-graysoft">
                <tr>
                    <th class="px-4 py-3">O'quvchi</th>
                    <th class="px-4 py-3">Topshiriq</th>
                    <th class="px-4 py-3">Holat</th>
                    <th class="px-4 py-3">Sana</th>
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
                            <a href="{{ route('mentor.review-studio', $s) }}" class="font-medium text-emerald-deep hover:underline">Tekshirish →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-graysoft">Yangi topshirish yo'q ✓</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
