import { t } from './ui-translations';
const root = document.querySelector('#quran-mini-player');
if (root) {
    const audio = root.querySelector('audio'), full = document.querySelector('#quran-full-player');
    let catalog = null, playlist = null, index = 0, repeats = 0, generation = 0, pauseForReading = false, loop = false, gap = 0, timer = null, lastSaved = 0;
    const userKey = `quran-audio-${document.querySelector('[data-reader]')?.dataset.reader || 'session'}`;
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const clock = value => `${Math.floor((value || 0) / 60)}:${String(Math.floor((value || 0) % 60)).padStart(2, '0')}`;
    const message = value => { root.querySelector('[data-audio-error]').textContent = value; document.querySelector('[data-library-message]')?.replaceChildren(document.createTextNode(value)); };
    const request = async (url, method = 'GET', body) => {
        const response = await fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, ...(body ? { body: JSON.stringify(body) } : {}) });
        if (!response.ok) { const data = await response.json().catch(() => ({})); throw new Error(data.message || t("Audio xizmati ochilmadi. Qayta urinib ko‘ring.")); }
        return response.json();
    };
    const loadCatalog = async () => {
        if (!catalog) catalog = await request(root.dataset.catalogUrl);
        return catalog;
    };
    const renderLists = () => {
        const container = document.querySelector('[data-hifz-lists]'); if (!container || !catalog) return;
        container.replaceChildren();
        for (const list of catalog.lists || []) {
            const row = document.createElement('div'); row.className = 'flex gap-3';
            const play = document.createElement('button'); play.type = 'button'; play.className = 'quick-link flex-1 text-left'; play.dataset.hifzPlay = list.id; play.textContent = `▶ ${list.name} · ${list.ayah_from}–${list.ayah_to}`;
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'bookmark-button'; remove.dataset.hifzDelete = list.id; remove.textContent = t("Olib tashlash");
            row.append(play, remove); container.append(row);
        }
    };
    document.addEventListener('quran:page', renderLists);
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-library-save],[data-hifz-play],[data-hifz-delete]'); if (!button) return;
        button.disabled = true;
        try {
            const data = await loadCatalog();
            if (button.hasAttribute('data-library-save')) {
                const name = document.querySelector('[data-hifz-name]').value.trim(); if (!name) throw new Error(t("Ro‘yxat nomini kiriting."));
                const result = await request('/audio/lists', 'POST', { name, edition_id: Number(document.querySelector('[data-library-edition]').value), surah_id: Number(document.querySelector('[data-library-surah]').value), ayah_from: Number(document.querySelector('[data-library-from]').value), ayah_to: Number(document.querySelector('[data-library-to]').value) });
                data.lists.unshift(result.list); document.querySelector('[data-hifz-name]').value = ''; renderLists(); message(t("Hifz ro‘yxati saqlandi."));
            } else if (button.hasAttribute('data-hifz-delete')) {
                const id = Number(button.dataset.hifzDelete); await request(`/audio/lists/${id}`, 'DELETE'); data.lists = data.lists.filter(list => list.id !== id); renderLists();
            } else {
                const list = data.lists.find(item => item.id === Number(button.dataset.hifzPlay)); if (list) await start(list.edition_id, list.surah_id, list.ayah_from, list.ayah_to);
            }
        } catch (error) { message(error.message); }
        finally { button.disabled = false; }
    });
    const current = () => playlist?.tracks[index];
    const highlightAyah = ayah => {
        let changed = false;
        document.querySelectorAll('[data-ayah-number]').forEach(element => {
            const matches = ayah !== null && Number(element.dataset.ayahNumber) === ayah && Number(element.dataset.surahId) === Number(playlist?.surah?.id);
            changed ||= element.classList.contains('is-playing') !== matches;
            element.classList.toggle('is-playing', matches);
            if (matches) element.setAttribute('aria-current', 'true'); else element.removeAttribute('aria-current');
        });
        if (changed && ayah !== null && !audio.paused) {
            document.querySelector('.reader-ayah.is-playing')?.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        }
    };
    const synchronizeAyah = () => {
        const track = current();
        if (!playlist || !track) return;
        let ayah = null;
        if (playlist.mode === 'ayah' && track.ayah_number !== null) {
            ayah = track.ayah_number;
        } else if (playlist.mode === 'surah' && track.timestamps.length) {
            const position = Math.round((Number.isFinite(audio.currentTime) ? audio.currentTime : 0) * 1000);
            ayah = track.timestamps.find(segment => position >= segment.start_ms && position < segment.end_ms)?.ayah_number ?? null;
        }
        highlightAyah(ayah);
    };
    const saveHistory = async () => {
        const track = current(); if (!track || !Number.isFinite(audio.currentTime)) return;
        try { await request(root.dataset.historyUrl, 'POST', { audio_file_id: track.id, position_ms: Math.floor(audio.currentTime * 1000) }); }
        catch { message(t("Tinglash joyi serverga saqlanmadi. Internetni tekshiring.")); }
        try { sessionStorage.setItem(userKey, JSON.stringify({ edition: playlist.edition.id, surah: track.surah_id, from: track.ayah_number || 1, to: playlist.surah.ayah_count, mode: playlist.mode, position: audio.currentTime * 1000, file: track.id })); } catch {}
    };
    const update = () => {
        const track = current(); if (!track) return;
        root.hidden = false; document.body.classList.add('has-quran-audio');
        const title = `${track.surah_name}${track.ayah_number ? ` · ${track.ayah_number}` : ''}`;
        root.querySelector('[data-audio-title]').textContent = title;
        root.querySelector('[data-audio-reciter]').textContent = track.reciter;
        full.querySelector('[data-full-title]').textContent = title;
        full.querySelector('[data-full-reciter]').textContent = track.reciter;
        full.querySelector('[data-full-ayah]').textContent = track.text_arabic || '';
        full.querySelector('[data-full-sync]').textContent = playlist.mode === 'ayah'
            ? t("Sinxronlash oyat chegaralari bo‘yicha. So‘zma-so‘z vaqt belgilari mavjud emas.")
            : track.timestamps.length
                ? t("Tasdiqlangan oyat vaqt belgilari ishlatilmoqda.")
                : t("Bu faylda tasdiqlangan oyat vaqt belgilari mavjud emas. Noto‘g‘ri belgilar ko‘rsatilmaydi.");
        synchronizeAyah();
        full.querySelector('[data-audio-repeat-status]').textContent = `${repeats + 1} / ${Number(full.querySelector('[data-audio-repeat]').value)}`;
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({ title, artist: track.reciter, album: 'Qur’on AI Ustoz' });
        }
    };
    const play = async () => { try { message(''); await audio.play(); } catch { message(t("Ijro boshlanmadi. Tinglash tugmasini bosing yoki internetni tekshiring.")); } };
    const select = async (next, autoplay = true, position = 0) => {
        if (!playlist?.tracks[next]) return;
        clearTimeout(timer); generation++; index = next; repeats = 0;
        audio.pause(); audio.src = current().url; audio.load(); update();
        document.querySelector('.reader-ayah.is-playing')?.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        audio.addEventListener('loadedmetadata', () => { if (Number.isFinite(audio.duration)) audio.currentTime = Math.min(position / 1000, audio.duration); }, { once: true });
        if (autoplay) await play();
    };
    const start = async (edition, surah, from = 1, to, mode = 'ayah', fileId, position = 0, autoplay = true) => {
        await saveHistory(); clearTimeout(timer); audio.pause(); const serial = ++generation;
        const params = new URLSearchParams({ from, ...(to ? { to } : {}), mode });
        const data = await request(`${root.dataset.playlistBase}/${edition}/${surah}?${params}`);
        if (serial !== generation) return;
        if (!data.tracks.length) throw new Error(mode === 'surah' ? t("Bu nashrda to‘liq sura fayli mavjud emas. Oyatma-oyat tinglashni tanlang.") : t("Tanlangan oraliq uchun audio mavjud emas."));
        playlist = data;
        await select(Math.max(0, data.tracks.findIndex(track => track.id === fileId)), autoplay, position);
        if (!data.complete) message(`${data.available_count} / ${data.expected_count} audio mavjud. Yetishmayotgan oyatlar o‘tkazib yuboriladi.`);
        if (mode === 'surah') {
            message(data.synchronization === 'verified_segments'
                ? t("To‘liq sura ijrosi tasdiqlangan oyat vaqt belgilari bilan sinxronlangan.")
                : t("To‘liq sura ijrosi. Bu faylda tasdiqlangan oyat vaqt belgilari mavjud emas."));
        }
    };
    audio.addEventListener('ended', () => {
        void saveHistory(); const serial = generation;
        const repeatCount = Number(full.querySelector('[data-audio-repeat]').value);
        if (repeats + 1 < repeatCount) {
            repeats++; audio.currentTime = 0; update();
            timer = setTimeout(() => { if (serial === generation && !pauseForReading) void play(); }, gap * 1000);
        } else {
            let next = index + 1;
            if (next >= playlist.tracks.length) { if (!loop) return; next = 0; }
            timer = setTimeout(() => { if (serial === generation) void select(next, !pauseForReading); }, gap * 1000);
        }
    });
    audio.addEventListener('play', () => { root.querySelector('[data-audio-toggle]').textContent = 'Ⅱ'; if ('mediaSession' in navigator) navigator.mediaSession.playbackState = 'playing'; });
    audio.addEventListener('pause', () => { root.querySelector('[data-audio-toggle]').textContent = '▶'; if ('mediaSession' in navigator) navigator.mediaSession.playbackState = 'paused'; });
    audio.addEventListener('error', () => message(t("Audio ochilmadi. Fayl vaqtincha mavjud bo‘lmasligi mumkin; boshqa qori yoki oyatni tanlang.")));
    audio.addEventListener('timeupdate', () => {
        root.querySelector('[data-audio-time]').textContent = clock(audio.currentTime);
        root.querySelector('[data-audio-duration]').textContent = clock(audio.duration);
        root.querySelector('[data-audio-seek]').value = Number.isFinite(audio.duration) ? String(audio.currentTime / audio.duration * 1000) : '0';
        synchronizeAyah();
        if (Date.now() - lastSaved > 15000 && !audio.paused) { lastSaved = Date.now(); void saveHistory(); }
    });
    root.querySelector('[data-audio-seek]').addEventListener('input', event => { if (Number.isFinite(audio.duration)) { audio.currentTime = Number(event.target.value) / 1000 * audio.duration; synchronizeAyah(); } });
    document.querySelectorAll('[data-audio-toggle]').forEach(button => button.addEventListener('click', () => { clearTimeout(timer); generation++; if (audio.paused) void play(); else { audio.pause(); void saveHistory(); } }));
    const stepAyah = async offset => {
        const track = current();
        if (!track) return;
        if (playlist.mode === 'surah' && track.timestamps.length) {
            const position = Math.round(audio.currentTime * 1000);
            const ordered = [...track.timestamps].sort((a, b) => a.start_ms - b.start_ms);
            const currentIndex = ordered.findIndex(segment => position >= segment.start_ms && position < segment.end_ms);
            const nextIndex = currentIndex >= 0
                ? Math.min(ordered.length - 1, Math.max(0, currentIndex + offset))
                : offset > 0 ? 0 : ordered.length - 1;
            audio.currentTime = ordered[nextIndex].start_ms / 1000;
            synchronizeAyah();
            return;
        }
        void saveHistory();
        void select(Math.min(playlist.tracks.length - 1, Math.max(0, index + offset)));
    };
    root.querySelector('[data-audio-previous]').addEventListener('click', () => { void stepAyah(-1); });
    root.querySelector('[data-audio-next]').addEventListener('click', () => { void stepAyah(1); });
    root.querySelector('[data-audio-close]').addEventListener('click', () => { void saveHistory(); clearTimeout(timer); generation++; audio.pause(); root.hidden = true; document.body.classList.remove('has-quran-audio'); });
    root.querySelector('[data-audio-expand]').addEventListener('click', () => full.showModal());
    full.querySelector('[data-audio-collapse]').addEventListener('click', () => full.close());
    const persistPreferences = async () => {
        try { await request(root.dataset.preferencesUrl, 'PUT', { ...(playlist ? { edition_id: playlist.edition.id } : {}), speed: audio.playbackRate, volume: audio.volume, repeat_count: Number(full.querySelector('[data-audio-repeat]').value), gap_seconds: gap }); }
        catch { message(t("Tinglash sozlamalari saqlanmadi.")); }
    };
    full.querySelector('[data-audio-speed]').addEventListener('change', event => { audio.playbackRate = Number(event.target.value); void persistPreferences(); });
    full.querySelector('[data-audio-volume]').addEventListener('change', event => { audio.volume = Number(event.target.value); void persistPreferences(); });
    full.querySelector('[data-audio-repeat]').addEventListener('change', () => { repeats = 0; void persistPreferences(); });
    full.querySelector('[data-audio-gap]').addEventListener('change', event => { gap = Math.max(0, Math.min(60, Number(event.target.value))); void persistPreferences(); });
    full.querySelector('[data-audio-loop]').addEventListener('change', event => loop = event.target.checked);
    full.querySelector('[data-audio-listen-read]').addEventListener('change', event => pauseForReading = event.target.checked);
    full.querySelectorAll('[data-surah-step]').forEach(button => button.addEventListener('click', async () => {
        if (!playlist) return;
        try { const data = await loadCatalog(), number = playlist.surah.number + Number(button.dataset.surahStep), surah = data.surahs.find(item => item.number === number); if (surah) await start(playlist.edition.id, surah.id, 1, surah.ayah_count); }
        catch (error) { message(error.message); }
    }));
    document.addEventListener('click', async event => {
        const target = event.target.closest('[data-play-ayah],[data-library-play],[data-select-edition],[data-library-favorite],[data-resume-file]');
        if (!target) return;
        try {
            const data = await loadCatalog(), editionSelect = document.querySelector('[data-library-edition]');
            if (target.hasAttribute('data-play-ayah')) {
                const edition = playlist?.edition.id || data.preferences?.edition_id || data.editions[0]?.id;
                if (!edition) throw new Error(t("Audio nashri hali mavjud emas."));
                const surah = data.surahs.find(s => s.id === Number(target.dataset.surah));
                await start(edition, surah.id, Number(target.dataset.playAyah), surah.ayah_count);
            } else if (target.hasAttribute('data-library-play')) {
                const surah = Number(document.querySelector('[data-library-surah]').value), from = Number(document.querySelector('[data-library-from]').value), to = Number(document.querySelector('[data-library-to]').value);
                await start(Number(editionSelect.value), surah, from, to, target.dataset.libraryPlay); await persistPreferences();
            } else if (target.hasAttribute('data-select-edition')) {
                editionSelect.value = target.dataset.selectEdition; editionSelect.focus();
            } else if (target.hasAttribute('data-library-favorite')) {
                const id = Number(editionSelect.value), favorite = !data.favorites.includes(id);
                await request(`/audio/favorites/${id}`, 'POST', { favorite }); data.favorites = favorite ? [...data.favorites, id] : data.favorites.filter(value => value !== id); message(favorite ? t("Qori sevimlilarga qo‘shildi.") : t("Qori sevimlilardan olindi."));
            } else if (target.hasAttribute('data-resume-file')) {
                await start(Number(target.dataset.resumeEdition), Number(target.dataset.resumeSurah), Number(target.dataset.resumeAyah), undefined, target.dataset.resumeMode, Number(target.dataset.resumeFile), Number(target.dataset.resumePosition));
            }
        } catch (error) { message(error.message); }
    });
    document.addEventListener('change', event => {
        if (event.target.matches('[data-library-surah]')) {
            document.querySelector('[data-library-from]').value = 1;
            document.querySelector('[data-library-to]').value = event.target.selectedOptions[0].dataset.ayahCount;
        }
    });
    document.addEventListener('play', event => { if (event.target instanceof HTMLAudioElement && event.target !== audio) { audio.pause(); generation++; clearTimeout(timer); } }, true);
    if ('mediaSession' in navigator) {
        for (const [action, handler] of Object.entries({ play: () => void play(), pause: () => audio.pause(), previoustrack: () => void select(Math.max(0, index - 1)), nexttrack: () => void select(index + 1), seekto: details => { if (Number.isFinite(details.seekTime)) audio.currentTime = details.seekTime; } })) {
            try { navigator.mediaSession.setActionHandler(action, handler); } catch {}
        }
    }
    // Keep the audio element mounted while following authenticated in-app GET links.
    const navigate = async (url, push = true) => {
        const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html'), main = doc.querySelector('#main-content');
        if (!response.ok || !main || !doc.querySelector('#quran-mini-player')) { location.href = url; return; }
        document.querySelector('#main-content').replaceWith(main); document.title = doc.title;
        document.querySelectorAll('.sidebar-nav a').forEach(link => { const active = new URL(link.href).pathname === new URL(url, location.origin).pathname; link.classList.toggle('is-active', active); if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current'); });
        document.body.classList.remove('menu-open'); document.querySelector('[data-menu-toggle]')?.setAttribute('aria-expanded', 'false');
        if (push) history.pushState({ quranAudio: true }, '', url); window.scrollTo(0, 0); update(); document.dispatchEvent(new Event('quran:page'));
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!current() || root.hidden || !link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || link.target || link.download) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || url.hash || !/^\/(dashboard|quran|courses|groups|lessons|schedules|assignments|homework|my-submissions|parents|notifications|administration|learning-path)(\/|$)/.test(url.pathname)) return;
        event.preventDefault(); void navigate(url.href).catch(() => location.href = url.href);
    });
    window.addEventListener('popstate', () => { if (current() && !root.hidden) void navigate(location.href, false).catch(() => location.reload()); });
    window.addEventListener('pagehide', () => {
        if (!current()) return;
        const data = new FormData(); data.set('_token', csrf); data.set('audio_file_id', current().id); data.set('position_ms', Math.floor(audio.currentTime * 1000));
        navigator.sendBeacon?.(root.dataset.historyUrl, data);
    });
    loadCatalog().then(data => {
        renderLists();
        const prefs = data.preferences;
        if (prefs) { audio.playbackRate = prefs.speed; audio.volume = prefs.volume; gap = prefs.gap_seconds; full.querySelector('[data-audio-speed]').value = prefs.speed; full.querySelector('[data-audio-volume]').value = prefs.volume; full.querySelector('[data-audio-repeat]').value = prefs.repeat_count; full.querySelector('[data-audio-gap]').value = gap; }
    }).catch(() => {});
}
