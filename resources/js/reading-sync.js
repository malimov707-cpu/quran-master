/**
 * Qur'on bookmark va oxirgi o'qilgan joy — server bilan sinxron.
 * localStorage'da darhol saqlanadi (offline ishlash uchun), online bo'lsa serverga yuboriladi.
 */
const WEB_TOKEN_KEY = 'web_token';

const apiHeaders = () => {
    const token = document.cookie.split('; ').find(c => c.startsWith('web_token='))?.split('=')[1]
        ?? localStorage.getItem(WEB_TOKEN_KEY);
    return token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' };
};

export function initReadingSync(reader) {
    if (!reader) return;

    const surahNumber = Number(reader.dataset.surahNumber);
    if (!surahNumber) return;

    const post = (path, body) => {
        const token = document.cookie.split('; ').find(c => c.startsWith('web_token='))?.split('=')[1]
            ?? localStorage.getItem(WEB_TOKEN_KEY);
        if (!token) return; // mehmon — faqat lokal
        fetch(`/api/v1/${path}`, {
            method: 'POST',
            headers: { ...apiHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        }).catch(() => { /* offline — keyingi safar */ });
    };

    // 1) Bookmark tugmalari: server bilan toggle
    reader.querySelectorAll('[data-bookmark]').forEach(button => {
        const [, ayahPart] = button.dataset.bookmark.split(':');
        const ayahNumber = Number(ayahPart);
        button.addEventListener('click', async () => {
            const pressed = button.getAttribute('aria-pressed') === 'true';
            // optimistik lokal yangilash
            const key = `quran-bookmarks-${reader.dataset.reader}`;
            let bookmarks = [];
            try { bookmarks = JSON.parse(localStorage.getItem(key) || '[]'); } catch { bookmarks = []; }
            const entry = `${surahNumber}:${ayahNumber}`;
            bookmarks = pressed ? bookmarks.filter(item => item !== entry) : [...bookmarks, entry];
            localStorage.setItem(key, JSON.stringify(bookmarks));
            button.setAttribute('aria-pressed', String(!pressed));
            button.textContent = !pressed ? '★ Saqlangan' : '☆ Belgilash';
            // server
            post('reading/bookmarks/toggle', { surah: surahNumber, ayah: ayahNumber });
        });
    });

    // 2) Oxirgi o'qilgan joy: visibility change + oyat scroll orqali
    let currentAyah = 0;
    const ayahBlocks = reader.querySelectorAll('[data-ayah-number]');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                currentAyah = Number(entry.target.dataset.ayahNumber);
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });
    ayahBlocks.forEach(block => observer.observe(block));

    const savePosition = () => {
        if (currentAyah > 0) post('reading/position', { surah: surahNumber, ayah: currentAyah });
    };
    document.addEventListener('visibilitychange', () => { if (document.hidden) savePosition(); });
    window.addEventListener('pagehide', savePosition);

    // 3) Tinglash tarixi: audio player to'xtaganda yozamiz
    const audio = document.querySelector('audio');
    if (audio) {
        let listened = 0;
        audio.addEventListener('timeupdate', () => { listened += 0.25; });
        audio.addEventListener('pause', () => {
            if (listened < 3) { listened = 0; return; }
            post('reading/playback', { surah: surahNumber, seconds: Math.round(listened) });
            listened = 0;
        });
    }
}
