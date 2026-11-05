@extends('layouts.app')
@section('title', __('Jadval va davomat'))
@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">{{ __('Jadval va davomat') }}</h1>
    <p class="mt-2 text-sm text-graysoft">{{ __('Dars vaqtlarini Toshkent vaqti bilan belgilang va guruh davomatini kiriting.') }}</p>
    @if ($canCreate)
        <details class="mt-6 rounded-2xl bg-ivory p-6 ring-1 ring-black/5" @if ($errors->any()) open @endif>
            <summary class="cursor-pointer font-semibold text-emerald-deep">{{ __('Jadvalga dars qo‘shish') }}</summary>
            <form method="POST" action="{{ route('education.schedules.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <div><label for="group_id" class="block text-sm">{{ __('Guruh') }}</label><select id="group_id" name="group_id" required class="mt-2 w-full rounded-lg border border-gray-200 p-3"><option value="">{{ __('Faol guruhni tanlang') }}</option>@foreach ($groups->where('status', 'active') as $group)<option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div><label for="date" class="block text-sm">{{ __('Sana') }}</label><input id="date" name="date" type="date" required value="{{ old('date') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                <div><label for="room" class="block text-sm">{{ __('Xona') }}</label><input id="room" name="room" maxlength="64" value="{{ old('room') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                <div><label for="start_time" class="block text-sm">{{ __('Boshlanish (Toshkent)') }}</label><input id="start_time" name="start_time" type="time" required value="{{ old('start_time') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                <div><label for="end_time" class="block text-sm">{{ __('Tugash (Toshkent)') }}</label><input id="end_time" name="end_time" type="time" required value="{{ old('end_time') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                <div class="flex items-end"><button type="submit" class="rounded-lg bg-emerald-deep px-5 py-3 font-semibold text-cream">{{ __('Darsni qo‘shish') }}</button></div>
            </form>
        </details>
    @endif
    <form method="GET" action="{{ route('education.schedules') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div><label for="filter_group" class="block text-sm">{{ __('Guruh') }}</label><select id="filter_group" name="group_id" class="mt-2 rounded-lg border border-gray-200 bg-ivory p-2"><option value="">{{ __('Barcha guruhlar') }}</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
        <div><label for="filter_date" class="block text-sm">{{ __('Sana') }}</label><input id="filter_date" name="date" type="date" value="{{ $filters['date'] ?? '' }}" class="mt-2 rounded-lg border border-gray-200 bg-ivory p-2"></div>
        <button type="submit" class="rounded-lg border border-emerald-deep px-4 py-2 text-emerald-deep">{{ __('Filtrlash') }}</button>
        <a href="{{ route('education.schedules') }}" class="p-2 text-sm text-graysoft">{{ __('Tozalash') }}</a>
    </form>
    <div class="mt-6 space-y-4">
        @forelse ($schedules as $schedule)
            <article class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-ivory p-6 ring-1 ring-black/5">
                <div><h2 class="font-semibold text-emerald-deep">{{ $schedule->group->name }}</h2><p class="mt-2 text-sm">{{ $schedule->date->format('d.m.Y') }} · {{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }} (Toshkent)</p>@if ($schedule->room)<p class="mt-2 text-sm text-graysoft">Xona: {{ $schedule->room }}</p>@endif<p class="mt-2 text-xs text-graysoft">{{ $schedule->attendance_count }} ta davomat yozuvi</p></div>
                @if ($canMark)<a href="{{ route('education.attendance', $schedule) }}" class="rounded-lg border border-emerald-deep px-4 py-2 text-sm text-emerald-deep">{{ __('Davomatni ochish') }}</a>@endif
            </article>
        @empty
            <p class="rounded-xl bg-ivory p-6 text-graysoft">{{ __('Bu filtrga mos dars yo‘q.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $schedules->links() }}</div>
@endsection
