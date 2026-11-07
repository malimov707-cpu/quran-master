import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../public/js/voice-chat.js', import.meta.url), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
function harness(reply = () => new Response(JSON.stringify({ transcript: 'Salom', answer: 'Assalomu alaykum.', audio: { data: 'UklGRg==', mime_type: 'audio/wav' } }))) {
    const calls = [], tracks = [], recordings = [], revoked = [];
    class Recorder {
        static isTypeSupported(type) { return type.startsWith('audio/webm'); }
        constructor() { this.mimeType = 'audio/webm'; recordings.push(this); }
        start() { this.state = 'recording'; }
        stop() {
            this.state = 'inactive';
            this.ondataavailable?.({ data: new Blob(['recorded audio']) });
            queueMicrotask(() => this.onstop?.());
        }
    }
    const context = {
        Audio: class { async play() { this.playing = true; } pause() { this.playing = false; } },
        MediaRecorder: Recorder, Blob, FormData, AbortController, atob, Uint8Array,
        URL: { createObjectURL: () => 'blob:reply', revokeObjectURL: url => revoked.push(url) },
        setTimeout: () => 1, clearTimeout() {},
        navigator: { mediaDevices: { getUserMedia: async () => {
            const track = { stop() { this.stopped = true; } }; tracks.push(track);
            return { getTracks: () => [track] };
        } } },
        document: { querySelector: () => ({ content: 'csrf-test' }) },
        fetch: async (url, options) => { calls.push({ url, ...options }); return reply(); },
    };
    vm.runInNewContext(source, context);
    const chat = context.voiceChat(); chat.$nextTick = () => {}; chat.init();
    return { chat, calls, tracks, recordings, revoked, context };
}

test('a complete voice turn uploads recording to Laravel and plays its OpenRouter reply', async () => {
    const { chat, calls, tracks } = harness();
    await chat.toggleMic();
    assert.equal(chat.connected, true);
    assert.equal(calls.length, 0);
    await chat.toggleMic(); await tick();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/ai/voice');
    assert.equal(calls[0].headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.ok(calls[0].body.get('audio').size > 0);
    assert.equal(calls[0].body.get('history'), '[]');
    assert.equal(tracks[0].stopped, true);
    assert.equal(chat.messages[0].text, 'Salom');
    assert.equal(chat.messages[1].text, 'Assalomu alaykum.');
    assert.equal(chat.audioEl.playing, true);
    assert.equal(chat.connecting, false);
});

test('next turn includes bounded history and cancels previous playback', async () => {
    const { chat, calls } = harness();
    await chat.start(); chat.finishRecording(); await tick();
    await chat.start();
    assert.equal(chat.audioEl.playing, false);
    chat.finishRecording(); await tick();
    assert.equal(JSON.parse(calls[1].body.get('history')).length, 2);
});

test('cancelling recording releases microphone without making a paid request', async () => {
    const { chat, tracks, calls } = harness();
    await chat.start(); chat.endSession(); await tick();
    assert.equal(tracks[0].stopped, true);
    assert.equal(calls.length, 0);
    assert.equal(chat.connected, false);
});

test('provider failure displays an actionable error and permits another recording', async () => {
    const { chat, calls, tracks } = harness(() => new Response(JSON.stringify({ message: 'OpenRouter kredit yetarli emas.' }), { status: 502 }));
    await chat.start(); chat.finishRecording(); await tick();
    assert.ok(chat.error.includes('kredit'));
    assert.equal(tracks[0].stopped, true);
    assert.equal(chat.messages.length, 0);
    await chat.start();
    assert.equal(chat.connected, true);
    assert.equal(calls.length, 1);
});

test('speech failure keeps text visible without claiming playback', async () => {
    const { chat } = harness(() => new Response(JSON.stringify({ transcript: 'Salom', answer: 'Javob', audio: null, warning: 'Ovoz yaratilmadi.' })));
    await chat.start(); chat.finishRecording(); await tick();
    assert.equal(chat.messages[1].text, 'Javob');
    assert.equal(chat.aiSpeaking, false);
    assert.equal(chat.error, 'Ovoz yaratilmadi.');
});

test('late response after cancellation cannot resume playback or change conversation', async () => {
    let resolve;
    const { chat, calls } = harness(() => new Promise(done => { resolve = done; }));
    await chat.start(); chat.finishRecording(); await tick();
    chat.endSession();
    assert.equal(calls[0].signal.aborted, true);
    resolve(new Response(JSON.stringify({ answer: 'Late response', transcript: 'Late' })));
    await tick();
    assert.equal(chat.messages.length, 0);
    assert.equal(chat.aiSpeaking, false);
});

test('leaving chat revokes playback URLs and stops audio', async () => {
    const { chat, revoked } = harness();
    await chat.start(); chat.finishRecording(); await tick();
    chat.destroy();
    assert.deepEqual(revoked, ['blob:reply']);
    assert.equal(chat.audioEl.playing, false);
});
