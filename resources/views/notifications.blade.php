@extends('layouts.app')

@section('title', 'Bildirishnomalar')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-emerald-deep">Bildirishnomalar</h1>
            <p class="mt-1 text-sm text-graysoft">{{ $unreadCount }} ta o‘qilmagan xabar</p>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-deep px-4 py-2 text-sm font-semibold text-cream">Hammasini o‘qilgan qilish</button>
            </form>
        @endif
    </div>
    <div class="space-y-4">
        @forelse ($notifications as $notification)
            <article class="rounded-2xl border-l-4 bg-ivory p-6 shadow-sm ring-1 ring-black/5 {{ $notification->read_at ? 'border-gray-200' : 'border-gold' }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold break-words text-emerald-deep">{{ $notification->title }}</h2>
                        @if ($notification->body)<p class="mt-2 text-sm break-words whitespace-pre-line">{{ $notification->body }}</p>@endif
                        <p class="mt-3 text-xs text-graysoft">
                            {{ $notification->created_at->format('d.m.Y H:i') }} · {{ $notification->read_at ? 'O‘qilgan' : 'Yangi' }}
                        </p>
                    </div>
                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-emerald-deep px-3 py-2 text-sm text-emerald-deep">O‘qilgan qilish</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-ivory p-6 text-graysoft">Hozircha bildirishnoma yo‘q.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $notifications->links() }}</div>
@endsection
