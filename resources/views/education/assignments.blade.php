@extends('layouts.app')
@section('title', 'Topshiriqlar boshqaruvi')
@section('content')
    <h1 class="text-2xl font-bold text-emerald-deep">Topshiriqlar boshqaruvi</h1>
    <p class="mt-2 text-sm text-graysoft">Guruh uchun vazifa yarating, e’lon qiling va topshirishlarni kuzating.</p>
    <details class="mt-6 rounded-2xl bg-ivory p-6 ring-1 ring-black/5" @if ($errors->any()) open @endif>
        <summary class="cursor-pointer font-semibold text-emerald-deep">Yangi topshiriq yaratish</summary>
        <form method="POST" action="{{ route('assignments.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label for="group_id" class="block text-sm font-medium">Guruh</label>
                <select id="group_id" name="group_id" required class="mt-2 w-full rounded-lg border border-gray-200 p-3">
                    <option value="">Faol guruhni tanlang</option>
                    @foreach ($groups->where('status', 'active') as $group)
                        <option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="title" class="block text-sm font-medium">Topshiriq nomi</label>
                <input id="title" name="title" required maxlength="255" value="{{ old('title') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3">
            </div>
            <div class="md:col-span-2">
                <label for="description" class="block text-sm font-medium">Ko‘rsatma</label>
                <textarea id="description" name="description" maxlength="10000" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 p-3">{{ old('description') }}</textarea>
            </div>
            <div class="grid grid-cols-3 gap-3 md:col-span-2">
                <div><label for="surah_number" class="block text-sm">Sura raqami</label><input id="surah_number" name="surah_number" type="number" min="1" max="114" value="{{ old('surah_number') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                <div><label for="ayah_from" class="block text-sm">Birinchi oyat</label><input id="ayah_from" name="ayah_from" type="number" min="1" max="286" value="{{ old('ayah_from') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
                <div><label for="ayah_to" class="block text-sm">Oxirgi oyat</label><input id="ayah_to" name="ayah_to" type="number" min="1" max="286" value="{{ old('ayah_to') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3"></div>
            </div>
            <div>
                <label for="due_at" class="block text-sm font-medium">Muddat (Toshkent vaqti)</label>
                <input id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at') }}" class="mt-2 w-full rounded-lg border border-gray-200 p-3">
            </div>
            <div>
                <label for="requires_audio" class="block text-sm font-medium">Audio talab qilinadimi?</label>
                <select id="requires_audio" name="requires_audio" class="mt-2 w-full rounded-lg border border-gray-200 p-3">
                    <option value="1" @selected((string) old('requires_audio', '1') === '1')>Ha</option>
                    <option value="0" @selected((string) old('requires_audio', '1') === '0')>Yo‘q</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium">Holat</label>
                <select id="status" name="status" class="mt-2 w-full rounded-lg border border-gray-200 p-3">
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Qoralama</option>
                    <option value="published" @selected(old('status') === 'published')>Darhol e’lon qilish</option>
                </select>
            </div>
            <div class="flex items-end"><button type="submit" class="rounded-lg bg-emerald-deep px-5 py-3 font-semibold text-cream">Topshiriqni yaratish</button></div>
        </form>
    </details>
    <form method="GET" action="{{ route('assignments.manage') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div><label for="filter_group" class="block text-sm">Guruh bo‘yicha</label><select id="filter_group" name="group_id" class="mt-2 rounded-lg border border-gray-200 bg-ivory p-2"><option value="">Barcha guruhlar</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
        <div><label for="filter_status" class="block text-sm">Holat bo‘yicha</label><select id="filter_status" name="status" class="mt-2 rounded-lg border border-gray-200 bg-ivory p-2"><option value="">Barcha holatlar</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Qoralama</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>E’lon qilingan</option><option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Yopilgan</option></select></div>
        <button type="submit" class="rounded-lg border border-emerald-deep px-4 py-2 text-emerald-deep">Filtrlash</button>
        <a href="{{ route('assignments.manage') }}" class="px-2 py-2 text-sm text-graysoft">Tozalash</a>
    </form>
    <div class="mt-6 space-y-4">
        @forelse ($assignments as $assignment)
            <article class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-ivory p-6 ring-1 ring-black/5">
                <div>
                    <h2 class="font-semibold text-emerald-deep">{{ $assignment->title }}</h2>
                    <p class="mt-2 text-sm text-graysoft">{{ $assignment->group->name }} · {{ ['draft' => 'Qoralama', 'published' => 'E’lon qilingan', 'closed' => 'Yopilgan'][$assignment->status] }}</p>
                    <p class="mt-2 text-sm">{{ $assignment->submissions_count }} ta topshirish · {{ $assignment->graded_count }} ta baholangan</p>
                    <p class="mt-2 text-xs text-graysoft">Muddat: {{ $assignment->due_at?->timezone(config('app.display_timezone'))->format('d.m.Y H:i') ?? 'Belgilanmagan' }}</p>
                </div>
                <form method="POST" action="{{ route('assignments.status', $assignment) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $assignment->status === 'published' ? 'closed' : 'published' }}">
                    <button type="submit" class="rounded-lg border border-emerald-deep px-4 py-2 text-sm text-emerald-deep">{{ $assignment->status === 'published' ? 'Topshirishni yopish' : ($assignment->status === 'draft' ? 'E’lon qilish' : 'Qayta ochish') }}</button>
                </form>
            </article>
        @empty
            <p class="rounded-xl bg-ivory p-6 text-graysoft">Bu filtrga mos topshiriq yo‘q.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $assignments->links() }}</div>
@endsection
