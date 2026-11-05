@extends('layouts.app')

@section('title', 'Guruhlar')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-emerald-deep">Guruhlar</h1>
            <p class="text-sm text-graysoft">O'quvchi guruhlari</p>
        </div>
        @canany(['groups.create'])
        <details class="relative">
            <summary class="cursor-pointer rounded-lg bg-emerald-deep px-4 py-2 text-sm font-semibold text-cream hover:bg-forest">+ Yangi guruh</summary>
            <form method="POST" action="{{ route('groups.store') }}" class="mt-2 absolute right-0 z-10 w-80 space-y-3 rounded-xl bg-ivory p-4 shadow-lg ring-1 ring-black/10">
                @csrf
                <input name="name" required placeholder="Guruh nomi" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                <select name="course_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    @foreach (\App\Models\Course::where('status', 'published')->get() as $course)
                        <option value="{{ $course->id }}">{{ $course->name }}</option>
                    @endforeach
                </select>
                <input name="capacity" type="number" min="1" max="50" value="15" placeholder="Sig'im" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                <button class="w-full rounded-lg bg-emerald-deep py-2 text-sm font-semibold text-cream">Saqlash</button>
            </form>
        </details>
        @endcanany
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl bg-ivory shadow-sm ring-1 ring-black/5">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase text-graysoft">
                    <th class="px-4 py-3">Guruh</th>
                    <th class="px-4 py-3">Kurs</th>
                    <th class="px-4 py-3">Ustoz</th>
                    <th class="px-4 py-3">Mentor</th>
                    <th class="px-4 py-3">O'quvchilar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="px-4 py-3 font-medium text-emerald-deep">{{ $group->name }}</td>
                        <td class="px-4 py-3">{{ $group->course?->name }}</td>
                        <td class="px-4 py-3">{{ $group->teacher?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $group->mentor?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $group->students_count }} / {{ $group->capacity }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-graysoft">Guruh yo'q</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $groups->links() }}
@endsection
