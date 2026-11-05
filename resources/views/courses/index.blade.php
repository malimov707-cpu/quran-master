@extends('layouts.app')

@section('title', 'Kurslar')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-emerald-deep">Kurslar</h1>
            <p class="text-sm text-graysoft">Ta'lim markazi kurslari</p>
        </div>
        @canany(['courses.create'])
        <details class="relative">
            <summary class="cursor-pointer rounded-lg bg-emerald-deep px-4 py-2 text-sm font-semibold text-cream hover:bg-forest">+ Yangi kurs</summary>
            <form method="POST" action="{{ route('courses.store') }}" class="mt-2 absolute right-0 z-10 w-80 space-y-3 rounded-xl bg-ivory p-4 shadow-lg ring-1 ring-black/10">
                @csrf
                <input name="name" required placeholder="Kurs nomi" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                <select name="level" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <option value="beginner">Boshlang'ich</option>
                    <option value="intermediate">O'rta</option>
                    <option value="advanced">Yuqori</option>
                </select>
                <input name="duration_weeks" type="number" min="1" placeholder="Davomiyligi (hafta)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                <textarea name="description" placeholder="Tavsif" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></textarea>
                <button class="w-full rounded-lg bg-emerald-deep py-2 text-sm font-semibold text-cream">Saqlash</button>
            </form>
        </details>
        @endcanany
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($courses as $course)
            <a href="{{ route('courses.show', $course) }}" class="rounded-2xl bg-ivory p-6 shadow-sm ring-1 ring-black/5 transition hover:shadow-md">
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                    {{ ['beginner' => 'Boshlang\'ich', 'intermediate' => 'O\'rta', 'advanced' => 'Yuqori'][$course->level] }}
                </span>
                <h3 class="mt-3 font-semibold text-emerald-deep">{{ $course->name }}</h3>
                <p class="mt-1 line-clamp-2 text-sm text-graysoft">{{ $course->description }}</p>
                <div class="mt-4 flex justify-between text-xs text-graysoft">
                    <span>{{ $course->groups_count }} guruh</span>
                    <span>{{ $course->status === 'published' ? '✅ Nashr etilgan' : ($course->status === 'draft' ? '📝 Qoralama' : '📦 Arxiv') }}</span>
                </div>
            </a>
        @empty
            <p class="text-graysoft">Hozircha kurs yo'q.</p>
        @endforelse
    </div>

    {{ $courses->links() }}
@endsection
