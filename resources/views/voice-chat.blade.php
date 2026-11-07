@extends('layouts.app')

@section('title', 'AI Ustoz — Ovozli muloqot')

@section('content')
<div class="mx-auto max-w-2xl" x-data="voiceChat()">
    {{-- Sarlavha --}}
    <div class="text-center">
        <h1 class="text-xl font-extrabold text-forest">🎙 AI Ustoz — Ovozli muloqot</h1>
        <p class="mt-1 text-sm text-gray-500">Mikrofonni bosing va gapiring. Yuborish uchun yana bosing — AI ovozda javob beradi.</p>
    </div>

    {{-- Chat tarixi --}}
    <div class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <div class="h-[55vh] max-h-[460px] space-y-3 overflow-y-auto" x-ref="log">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'text-right' : 'text-left'">
                    <div :class="msg.role === 'user'
                        ? 'ml-12 rounded-2xl rounded-br-sm bg-forest px-4 py-3 text-white'
                        : 'mr-12 rounded-2xl rounded-bl-sm bg-cream px-4 py-3 text-gray-800'">
                        <p class="text-sm leading-relaxed" x-text="msg.text"></p>
                    </div>
                </div>
            </template>
            <div x-show="aiSpeaking" class="flex items-center gap-2 text-sm text-forest">
                <span class="inline-flex gap-1">
                    <span class="h-2 w-2 animate-bounce rounded-full bg-forest"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-forest" style="animation-delay: 150ms"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-forest" style="animation-delay: 300ms"></span>
                </span>
                AI gapiryapti...
            </div>
        </div>
    </div>

    {{-- Boshqaruv --}}
    <div class="mt-4 flex flex-col items-center gap-3">
        <button x-on:click="toggleMic()"
                :class="connected ? 'bg-red-600' : 'bg-forest hover:bg-emerald-deep'"
                :disabled="connecting"
                :aria-label="connected ? 'Yozishni tugatish va yuborish' : 'Ovoz yozish'"
                class="flex h-20 w-20 items-center justify-center rounded-full text-3xl text-white shadow-lg transition-all">
            <span x-text="connecting ? '…' : (connected ? '⏹' : '🎙')"></span>
        </button>
        <p class="text-sm text-gray-500" x-text="statusText"></p>
        <button x-on:click="endSession()" x-show="connected || connecting || aiSpeaking" class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-600 hover:bg-gray-200">Bekor qilish</button>
        <button x-on:click="playReply()" x-show="replyUrl && !connected && !connecting" class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-600">Javobni eshitish</button>
    </div>

    <div x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="error"></div>
</div>

<script src="{{ asset('js/voice-chat.js').'?v='.filemtime(public_path('js/voice-chat.js')) }}"></script>
@endsection
