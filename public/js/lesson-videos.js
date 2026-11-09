export function mergeVideoRanges(ranges) {
    const result = [];
    for (const [from, to] of [...ranges].sort((a, b) => a[0] - b[0])) {
        if (to <= from) continue;
        const last = result.at(-1);
        if (last && from <= last[1]) last[1] = Math.max(last[1], to);
        else result.push([from, to]);
    }
    return result;
}

let youtubeReady;
function youtubeAPI() {
    if (window.YT?.Player) return Promise.resolve(window.YT);
    if (!youtubeReady) youtubeReady = new Promise(resolve => {
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => { previous?.(); resolve(window.YT); };
        const script = document.createElement('script'); script.src = 'https://www.youtube.com/iframe_api'; document.head.append(script);
    });
    return youtubeReady;
}
async function request(path, body, method = 'POST') {
    const response = await fetch(path, { method, ...(body ? { body: body instanceof FormData ? body : JSON.stringify(body) } : {}), headers: {
        Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
    } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || `Video so‘rovida xato (HTTP ${response.status}).`);
    return data;
}
function element(tag, text, className) { const node = document.createElement(tag); if (text) node.textContent = text; if (className) node.className = className; return node; }
async function setup(root) {
    const lessonId = root.dataset.lesson, list = root.querySelector('[data-videos-list]'), status = root.querySelector('[data-videos-status]');
    const dispose = [];
    async function load() {
        dispose.splice(0).forEach(fn => fn()); list.replaceChildren();
        try {
            const page = await request(`/lessons/${lessonId}/videos`, null, 'GET');
            status.textContent = page.videos.length ? '' : 'Hali video qo‘shilmagan.';
            for (const video of page.videos) {
                const card = element('article', '', 'space-y-3 rounded-xl border border-gray-200 p-4');
                card.append(element('h3', video.title, 'font-semibold text-forest'));
                const slot = element('div', '', 'aspect-video w-full overflow-hidden rounded-xl bg-black'); card.append(slot);
                const progressText = element('p', '', 'text-sm text-graysoft'); card.append(progressText);
                const key = `lesson-video:${location.origin}:${page.user_id}:${video.id}:${video.version}`;
                let local;
                try { local = JSON.parse(localStorage.getItem(key) || 'null'); } catch {}
                let ranges = mergeVideoRanges([...(video.progress.ranges || []), ...(local?.ranges || [])]);
                let position = local && Date.parse(local.observed_at) > Date.parse(video.progress.observed_at || 0) ? local.position_seconds : video.progress.position_seconds;
                let observed = local?.observed_at || video.progress.observed_at || new Date().toISOString();
                let sample = null, playing = false, ready = false, ytInteracted = false, yt = null, player = null, stopped = false, sending = false;
                const updateLabel = () => { const seconds = ranges.reduce((sum, [from, to]) => sum + to - from, 0); progressText.textContent = `Ko‘rilgan: ${Math.min(100, Math.round(seconds / video.duration_seconds * 100))}% · Davom etish: ${Math.floor(position / 60)}:${String(Math.floor(position % 60)).padStart(2, '0')}`; };
                const body = () => ({ owner_id: page.user_id, position_seconds: Math.min(video.duration_seconds, Math.floor(position)), ranges, observed_at: observed });
                const persist = () => { if (!page.can_track) return; try { localStorage.setItem(key, JSON.stringify(body())); } catch {} };
                async function flush() {
                    if (!page.can_track || sending || stopped) return;
                    persist(); sending = true;
                    try { await request(`/videos/${video.id}/progress`, body()); }
                    catch (error) { progressText.textContent = `${error.message} Ko‘rish joyi qurilmada saqlandi.`; }
                    finally { sending = false; }
                }
                function track(time, active) {
                    const now = performance.now();
                    if (sample && active && playing) {
                        const delta = time - sample.position, elapsed = (now - sample.time) / 1000;
                        if (delta > 0 && delta <= elapsed * 3 + 1 && elapsed < 15) ranges = mergeVideoRanges([...ranges, [Math.floor(sample.position), Math.min(video.duration_seconds, Math.floor(time))]]);
                    }
                    sample = { position: time, time: now }; playing = active;
                    position = Math.min(video.duration_seconds, Math.max(0, time)); observed = new Date().toISOString();
                    persist(); updateLabel();
                }
                if (video.provider === 'upload') {
                    player = document.createElement('video'); player.controls = true; player.playsInline = true; player.preload = 'metadata'; player.className = 'h-full w-full'; const source = new URL(video.url); player.src = source.pathname + source.search;
                    player.addEventListener('loadedmetadata', () => { player.currentTime = Math.min(position, Math.max(0, player.duration - 0.1)); ready = true; });
                    player.addEventListener('timeupdate', () => { if (ready) track(player.currentTime, !player.paused); });
                    player.addEventListener('seeking', () => { sample = null; });
                    player.addEventListener('play', () => { track(player.currentTime, true); });
                    player.addEventListener('pause', () => { track(player.currentTime, false); void flush(); });
                    player.addEventListener('ended', () => { track(player.currentTime, false); void flush(); });
                    player.addEventListener('error', () => { progressText.textContent = 'Video ochilmadi. Sahifani yangilang yoki MP4 formatini tekshiring.'; });
                    slot.append(player);
                } else {
                    const target = document.createElement('div'); slot.append(target);
                    void youtubeAPI().then(YT => {
                        if (stopped) return;
                        yt = new YT.Player(target, { host: 'https://www.youtube-nocookie.com', width: '100%', height: '100%', videoId: video.youtube_id,
                            playerVars: { playsinline: 1, origin: location.origin, start: Math.floor(position) },
                            events: { onStateChange: event => { if (event.data === 1 || event.data === 2) ytInteracted = true; if (ytInteracted) track(event.target.getCurrentTime(), event.data === 1); if (event.data !== 1) void flush(); }, onError: () => { progressText.textContent = 'YouTube videosi ochilmadi yoki embed ruxsati o‘chirilgan.'; } } });
                    });
                }
                const sampleTimer = setInterval(() => { if (ytInteracted && yt?.getCurrentTime) track(yt.getCurrentTime(), yt.getPlayerState() === 1); }, 1000);
                const saveTimer = setInterval(() => { if (playing) void flush(); }, 10000);
                const hidden = () => { if (document.hidden) { player?.pause(); yt?.pauseVideo?.(); void flush(); } };
                const leave = () => { persist(); if (page.can_track) fetch(`/videos/${video.id}/progress`, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify(body()) }).catch(() => {}); };
                document.addEventListener('visibilitychange', hidden); window.addEventListener('pagehide', leave);
                dispose.push(() => { void flush(); stopped = true; clearInterval(sampleTimer); clearInterval(saveTimer); document.removeEventListener('visibilitychange', hidden); window.removeEventListener('pagehide', leave); player?.pause(); yt?.destroy?.(); });
                if (page.can_manage) {
                    const remove = element('button', 'Videoni o‘chirish', 'text-sm text-red-700'); remove.type = 'button';
                    remove.onclick = async () => { if (!confirm('Videoni o‘chirasizmi?')) return; remove.disabled = true; try { await request(`/videos/${video.id}`, null, 'DELETE'); await load(); } catch (error) { progressText.textContent = error.message; remove.disabled = false; } };
                    card.append(remove);
                }
                updateLabel(); list.append(card);
                if (local) void flush();
            }
        } catch (error) { status.textContent = error.message; }
    }
    const form = root.querySelector('[data-video-form]');
    if (form) {
        const provider = form.elements.provider, fileField = form.querySelector('[data-file-field]'), urlField = form.querySelector('[data-url-field]'), output = form.querySelector('[data-upload-status]');
        provider.onchange = () => { fileField.hidden = provider.value !== 'upload'; urlField.hidden = provider.value !== 'youtube'; };
        form.elements.file.onchange = () => {
            const file = form.elements.file.files[0]; if (!file) return;
            const probe = document.createElement('video'), url = URL.createObjectURL(file); probe.preload = 'metadata'; probe.src = url;
            probe.onloadedmetadata = () => { if (Number.isFinite(probe.duration)) form.elements.duration.value = Math.ceil(probe.duration); URL.revokeObjectURL(url); };
            probe.onerror = () => URL.revokeObjectURL(url);
        };
        form.onsubmit = async event => {
            event.preventDefault(); const submit = form.querySelector('button[type=submit]'); submit.disabled = true; let uploadId;
            try {
                const data = { title: form.elements.title.value, duration_seconds: Number(form.elements.duration.value), offline_allowed: form.elements.offline.checked };
                if (provider.value === 'youtube') await request(`/lessons/${lessonId}/videos/youtube`, { ...data, url: form.elements.url.value });
                else {
                    const file = form.elements.file.files[0]; if (!file) throw new Error('MP4 video faylini tanlang.');
                    if (file.size > 256 * 1024 * 1024) throw new Error('Video 256 MB dan oshmasligi kerak.');
                    const session = await request(`/lessons/${lessonId}/video-uploads`, { ...data, size_bytes: file.size }); uploadId = session.upload_id;
                    for (let offset = 0, index = 0; offset < file.size; offset += session.chunk_bytes, index++) {
                        const chunk = new FormData(); chunk.append('index', String(index)); chunk.append('chunk', file.slice(offset, offset + session.chunk_bytes), 'chunk.bin');
                        await request(`/video-uploads/${uploadId}/chunks`, chunk); output.textContent = `Yuklanmoqda: ${Math.min(100, Math.round((offset + session.chunk_bytes) / file.size * 100))}%`;
                    }
                    output.textContent = 'Video saqlanmoqda…'; await request(`/video-uploads/${uploadId}/finish`); uploadId = null;
                }
                output.textContent = 'Video saqlandi.'; form.reset(); provider.onchange(); await load();
            } catch (error) { output.textContent = error.message; if (uploadId) await request(`/video-uploads/${uploadId}`, null, 'DELETE').catch(() => {}); }
            finally { submit.disabled = false; }
        };
    }
    await load();
}
if (typeof document !== 'undefined') document.querySelectorAll('[data-lesson-videos]').forEach(root => { void setup(root); });
