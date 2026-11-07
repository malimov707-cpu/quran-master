/** OpenRouter orqali navbat bilan ovozli suhbat. API kaliti serverda qoladi. */
function voiceChat() {
    return {
        messages: [], connected: false, connecting: false, listening: false,
        aiSpeaking: false, tokenReady: true, error: '',
        statusText: 'Mikrofonni bosing, gapiring, so‘ng yuborish uchun yana bosing.',
        stream: null, recorder: null, audioEl: null, replyUrl: null,
        timer: null, controller: null, generation: 0,

        init() {
            this.audioEl = new Audio();
            this.audioEl.onended = () => { this.aiSpeaking = false; this.statusText = 'Keyingi savol uchun mikrofonni bosing.'; };
        },

        async toggleMic() {
            if (this.connecting) return;
            if (this.connected) this.finishRecording();
            else await this.start();
        },

        async start() {
            if (this.connecting || this.connected) return;
            this.endSession();
            const generation = this.generation;
            this.connecting = true;
            this.error = '';
            try {
                if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                    throw new Error('Brauzer audio yozishni qo‘llamaydi yoki HTTPS orqali kirmagansiz.');
                }
                const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
                if (generation !== this.generation) { stream.getTracks().forEach(track => track.stop()); return; }
                this.stream = stream;
                const mime = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus'].find(type => MediaRecorder.isTypeSupported(type));
                this.recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
                const chunks = [];
                this.recorder.ondataavailable = event => { if (event.data.size) chunks.push(event.data); };
                this.recorder.onstop = () => {
                    this.stopTracks();
                    if (generation !== this.generation) return;
                    const blob = new Blob(chunks, { type: this.recorder.mimeType || 'audio/webm' });
                    void this.sendAudio(blob, generation);
                };
                this.recorder.onerror = () => { this.endSession(); this.error = 'Ovoz yozishda xato. Qayta urinib ko‘ring.'; };
                this.recorder.start();
                this.connected = true;
                this.listening = true;
                this.statusText = 'Yozilmoqda… Yuborish uchun mikrofonni yana bosing (30 soniyagacha).';
                this.timer = setTimeout(() => this.finishRecording(), 30000);
            } catch (error) {
                if (generation === this.generation) {
                    this.endSession();
                    this.error = error.name === 'NotAllowedError' ? 'Mikrofonga ruxsat bering.' : error.message;
                }
            } finally {
                if (generation === this.generation) this.connecting = false;
            }
        },

        finishRecording() {
            if (!this.recorder || this.recorder.state !== 'recording') return;
            clearTimeout(this.timer);
            this.connected = false;
            this.listening = false;
            this.connecting = true;
            this.statusText = 'AI javob tayyorlamoqda…';
            this.recorder.stop();
        },

        async sendAudio(blob, generation) {
            this.controller = new AbortController();
            const timeout = setTimeout(() => this.controller?.abort(), 100000);
            try {
                if (!blob.size) throw new Error('Ovoz yozilmadi. Qayta gapirib ko‘ring.');
                const form = new FormData();
                const extension = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
                form.append('audio', blob, `voice.${extension}`);
                form.append('history', JSON.stringify(this.messages.slice(-8).map(message => ({ role: message.role, content: message.text.slice(0, 2000) }))));
                const res = await fetch('/ai/voice', {
                    method: 'POST', body: form, signal: this.controller.signal,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                });
                const data = await res.json().catch(() => ({}));
                if (generation !== this.generation) return;
                if (!res.ok) throw new Error(data.message || 'OpenRouter javob bermadi.');
                if (data.transcript) this.messages.push({ role: 'user', text: data.transcript });
                this.messages.push({ role: 'assistant', text: data.answer });
                this.scrollToBottom();
                this.statusText = 'Keyingi savol uchun mikrofonni bosing.';
                this.error = data.warning || '';
                if (data.audio?.data) {
                    if (this.replyUrl) URL.revokeObjectURL(this.replyUrl);
                    const bytes = Uint8Array.from(atob(data.audio.data), character => character.charCodeAt(0));
                    this.replyUrl = URL.createObjectURL(new Blob([bytes], { type: data.audio.mime_type }));
                    this.audioEl.src = this.replyUrl;
                    await this.playReply();
                }
            } catch (error) {
                if (generation === this.generation) {
                    this.error = error.name === 'AbortError' ? 'Server javobi kechikdi. Qayta urinib ko‘ring.' : error.message;
                    this.statusText = 'Qayta urinish uchun mikrofonni bosing.';
                }
            } finally {
                clearTimeout(timeout);
                if (generation === this.generation) { this.connecting = false; this.controller = null; }
            }
        },

        async playReply() {
            if (!this.replyUrl) return;
            try { await this.audioEl.play(); this.aiSpeaking = true; }
            catch { this.error = 'Ovozni eshitish uchun «Javobni eshitish» tugmasini bosing.'; }
        },

        endSession() {
            this.generation++;
            clearTimeout(this.timer);
            this.controller?.abort();
            this.controller = null;
            if (this.recorder) {
                this.recorder.onstop = null;
                this.recorder.onerror = null;
                if (this.recorder.state === 'recording') this.recorder.stop();
            }
            this.recorder = null;
            this.stopTracks();
            this.audioEl?.pause();
            this.connected = false;
            this.connecting = false;
            this.listening = false;
            this.aiSpeaking = false;
        },

        destroy() {
            this.endSession();
            if (this.replyUrl) URL.revokeObjectURL(this.replyUrl);
        },
        stopTracks() { this.stream?.getTracks().forEach(track => track.stop()); this.stream = null; },
        scrollToBottom() { this.$nextTick(() => { const log = this.$refs.log; if (log) log.scrollTop = log.scrollHeight; }); },
    };
}
