@extends('layouts.app')
@section('title', 'Guruh davomati')
@section('content')
    <a href="{{ route('education.schedules') }}" class="text-sm text-emerald-deep">← Jadvalga qaytish</a>
    <h1 class="mt-4 text-2xl font-bold text-emerald-deep">{{ $schedule->group->name }} — davomat</h1>
    <p class="mt-2 text-sm text-graysoft">{{ $schedule->date->format('d.m.Y') }} · {{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }} (Toshkent)</p>
    @if (! $hasStarted)<p class="mt-4 rounded-xl bg-gold/10 p-4 text-sm">Davomat dars boshlangandan keyin kiritiladi.</p>@endif
    @if ($students->isEmpty())
        <p class="mt-6 rounded-xl bg-ivory p-6 text-graysoft">Bu guruhda faol o‘quvchi yo‘q.</p>
    @else
        <form method="POST" action="{{ route('education.attendance.store', $schedule) }}" class="mt-6 space-y-4">
            @csrf
            <fieldset @disabled(! $hasStarted) class="space-y-4">
                @foreach ($students as $index => $student)
                    <div class="grid items-center gap-4 rounded-2xl bg-ivory p-5 ring-1 ring-black/5 md:grid-cols-3">
                        <p class="font-semibold text-emerald-deep">{{ $student->name }}</p>
                        <input type="hidden" name="records[{{ $index }}][student_id]" value="{{ $student->id }}">
                        <div><label for="status_{{ $student->id }}" class="block text-xs text-graysoft">Davomat holati</label><select id="status_{{ $student->id }}" name="records[{{ $index }}][status]" required class="mt-2 w-full rounded-lg border border-gray-200 p-3"><option value="">Holatni tanlang</option>@foreach (['present' => 'Kelgan', 'absent' => 'Kelmagan', 'late' => 'Kechikkan', 'excused' => 'Sababli'] as $value => $label)<option value="{{ $value }}" @selected(old("records.$index.status", $attendance->get($student->id)?->status) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div><label for="note_{{ $student->id }}" class="block text-xs text-graysoft">Izoh (ixtiyoriy)</label><input id="note_{{ $student->id }}" name="records[{{ $index }}][note]" maxlength="2000" value="{{ old("records.$index.note", $attendance->get($student->id)?->note) }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                    </div>
                @endforeach
                <button type="submit" class="rounded-lg bg-emerald-deep px-5 py-3 font-semibold text-cream">Davomatni saqlash</button>
            </fieldset>
        </form>
    @endif
@endsection
