@extends('layouts.app')

@section('title', __('Guruhlar'))

@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Guruhlar') }}</h1>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl bg-ivory shadow-sm ring-1 ring-black/5">
        <table class="w-full text-sm">
            <thead class="bg-cream text-left text-xs uppercase text-graysoft">
                <tr>
                    <th class="px-4 py-3">{{ __('Guruh') }}</th>
                    <th class="px-4 py-3">{{ __('Kurs') }}</th>
                    <th class="px-4 py-3">{{ __('O\'quvchi') }}</th>
                    <th class="px-4 py-3">{{ __('Mentor') }}</th>
                    <th class="px-4 py-3">{{ __('Holat') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-medium text-emerald-deep">{{ $group->name }}</td>
                        <td class="px-4 py-3">{{ $group->course?->name }}</td>
                        <td class="px-4 py-3">{{ $group->students_count }}/{{ $group->capacity }}</td>
                        <td class="px-4 py-3">{{ $group->mentor?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $group->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-graysoft">{{ __('Guruh yo\'q') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
            <h3 class="font-semibold text-emerald-deep">{{ __('Yangi guruh') }}</h3>
            <form method="POST" action="{{ route('education.groups.store') }}" class="mt-4 space-y-3">
                @csrf
                <input aria-label="{{ __('Guruh nomi') }}" name="name" required placeholder="{{ __('Guruh nomi') }}"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2">
                <select aria-label="{{ __('Kurs') }}" name="course_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2">
                    @foreach ($courses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <select aria-label="{{ __('Mentor') }}" name="mentor_id" class="w-full rounded-lg border border-gray-200 px-3 py-2">
                    <option value="">{{ __('— Mentor tanlanmagan —') }}</option>
                    @foreach ($mentors as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <input aria-label="{{ __('Guruh sig‘imi') }}" name="capacity" type="number" min="1" value="15" required
                       class="w-full rounded-lg border border-gray-200 px-3 py-2">
                <button class="w-full rounded-lg bg-emerald-deep py-2 font-semibold text-cream hover:bg-forest">{{ __('Yaratish') }}</button>
            </form>
        </div>

        <div class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5">
            <h3 class="font-semibold text-emerald-deep">{{ __('Guruhga o\'quvchi qo\'shish') }}</h3>
            <form method="POST" action="{{ route('education.groups.add-student.select') }}"
                  class="mt-4 space-y-3">
                @csrf
                <select aria-label="{{ __('Guruh') }}" name="group_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2">
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                <select aria-label="{{ __('O‘quvchi') }}" name="student_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2">
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}">{{ $student->name }}</option>
                    @endforeach
                </select>
                <button class="w-full rounded-lg bg-forest py-2 font-semibold text-cream hover:bg-emerald-deep">
                    {{ __('Qo‘shish') }}
                </button>
            </form>
        </div>
    </div>
<div class="mt-6">{{ $groups->links() }}</div>
@endsection
