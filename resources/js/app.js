import './prayer-times';
const storage = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch {} },
};
const mobile = matchMedia('(max-width: 767px)');
const toggle = document.querySelector('[data-menu-toggle]');
function closeMenu() {
    document.body.classList.remove('menu-open');
    toggle?.setAttribute('aria-expanded', 'false');
}
if (!mobile.matches && storage.get('quran-sidebar') === 'collapsed') document.body.classList.add('sidebar-collapsed');
toggle?.addEventListener('click', () => {
    if (mobile.matches) {
        const open = document.body.classList.toggle('menu-open');
        toggle.setAttribute('aria-expanded', String(open));
        if (open) document.querySelector('.sidebar-nav a')?.focus();
    } else {
        const collapsed = document.body.classList.toggle('sidebar-collapsed');
        storage.set('quran-sidebar', collapsed ? 'collapsed' : 'expanded');
    }
});
document.querySelector('[data-close-menu]')?.addEventListener('click', closeMenu);
mobile.addEventListener('change', () => {
    closeMenu();
    if (mobile.matches) document.body.classList.remove('sidebar-collapsed');
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        closeMenu();
        document.querySelectorAll('details[open]').forEach(element => element.removeAttribute('open'));
        toggle?.focus();
    }
    if (event.key === 'Tab' && document.body.classList.contains('menu-open')) {
        const nodes = [...document.querySelectorAll('.app-sidebar a, .app-sidebar button')];
        const first = nodes[0], last = nodes.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
});
document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    storage.set('quran-theme', theme);
}));
document.querySelectorAll('[data-dismiss-alert]').forEach(button => button.addEventListener('click', () => button.parentElement.remove()));
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    if (form.method.toLowerCase() !== 'post') return;
    form.querySelectorAll('button:not([type=button]):not([type=reset])').forEach(button => {
        setTimeout(() => { button.disabled = true; }, 0);
        button.setAttribute('aria-busy', 'true');
    });
}));
window.addEventListener('pageshow', () => document.querySelectorAll('button[aria-busy]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); }));
function initializeReader() {
const reader = document.querySelector('[data-reader]');
initReadingSync(reader);
if (reader) {
    let size = Number(storage.get('quran-reader-size')) || 30;
    const apply = () => reader.style.setProperty('--reader-size', `${size}px`);
    apply();
    document.querySelectorAll('[data-font-step]').forEach(button => button.addEventListener('click', () => {
        size = Math.max(22, Math.min(48, size + Number(button.dataset.fontStep)));
        storage.set('quran-reader-size', String(size));
        apply();
    }));
    const key = `quran-bookmarks-${reader.dataset.reader}`;
    let bookmarks;
    try { bookmarks = JSON.parse(storage.get(key) || '[]'); } catch { bookmarks = []; }
    if (!Array.isArray(bookmarks)) bookmarks = [];
    reader.querySelectorAll('[data-bookmark]').forEach(button => {
        const update = () => { const saved = bookmarks.includes(button.dataset.bookmark); button.setAttribute('aria-pressed', String(saved)); button.textContent = saved ? '★ Saqlangan' : '☆ Belgilash'; };
        update();
        button.addEventListener('click', () => {
            bookmarks = bookmarks.includes(button.dataset.bookmark) ? bookmarks.filter(id => id !== button.dataset.bookmark) : [...bookmarks, button.dataset.bookmark];
            storage.set(key, JSON.stringify(bookmarks)); update();
        });
    });
}

}
initializeReader();
document.addEventListener('quran:page', initializeReader);

document.querySelector('[data-locale-select]')?.addEventListener('change', event => event.target.form.requestSubmit());
document.querySelectorAll('[data-audio-studio]').forEach(studio => {
    const audio = studio.querySelector('audio');
    studio.querySelectorAll('[data-audio-skip]').forEach(button => button.addEventListener('click', () => {
        if (Number.isFinite(audio.duration)) audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + Number(button.dataset.audioSkip)));
    }));
    studio.querySelector('[data-playback-rate]')?.addEventListener('change', event => audio.playbackRate = Number(event.target.value));
    studio.querySelector('[data-load-waveform]')?.addEventListener('click', async event => {
        const button = event.currentTarget, status = studio.querySelector('[data-waveform-status]');
        button.disabled = true; status.textContent = 'Audio tahlil qilinmoqda…';
        let context;
        try {
            const response = await fetch(audio.currentSrc || audio.src, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Audio yuklanmadi.');
            context = new AudioContext();
            const buffer = await context.decodeAudioData(await response.arrayBuffer());
            const samples = buffer.getChannelData(0), canvas = studio.querySelector('canvas');
            canvas.width = 900;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, 900, 96); ctx.fillStyle = '#398c66';
            const bars = 180, block = Math.max(1, Math.floor(samples.length / bars));
            for (let index = 0; index < bars; index++) {
                let peak = 0;
                for (let sample = index * block; sample < Math.min((index + 1) * block, samples.length); sample++) peak = Math.max(peak, Math.abs(samples[sample]));
                const height = Math.max(1, peak * 88);
                ctx.fillRect(index * 5, (96 - height) / 2, 3, height);
            }
            canvas.onclick = event => {
                const rect = canvas.getBoundingClientRect();
                if (Number.isFinite(audio.duration)) audio.currentTime = (event.clientX - rect.left) / rect.width * audio.duration;
            };
            status.textContent = 'Audio to‘lqini tayyor. Vaqt bo‘yicha o‘tish uchun audio pleyerdan foydalaning.';
        } catch { status.textContent = 'To‘lqin ko‘rinishi ochilmadi. Audio pleyerdan foydalanishingiz mumkin.'; button.disabled = false; }
        finally { if (context) await context.close(); }
    });
});
document.querySelectorAll('[data-seek-audio]').forEach(button => button.addEventListener('click', () => {
    const audio = document.querySelector('[data-audio-studio] audio');
    if (audio && Number.isFinite(audio.duration)) { audio.currentTime = Math.min(Number(button.dataset.seekAudio), audio.duration); audio.play().catch(() => {}); }
}));

const confirmation = document.querySelector('#confirmation-dialog');
let pendingForm = null;
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || !confirmation) return;
    if (form.dataset.confirmed === 'true') { delete form.dataset.confirmed; return; }
    event.preventDefault();
    pendingForm = form;
    confirmation.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    confirmation.showModal();
}, true);
confirmation?.addEventListener('close', () => {
    const form = pendingForm; pendingForm = null;
    if (confirmation.returnValue === 'confirm' && form) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
});
const reviewAction = document.querySelector('#review-action');
reviewAction?.addEventListener('change', () => document.querySelector('#review-score').required = reviewAction.value === 'approve');

import './quran-audio';
import { initReadingSync } from './reading-sync';

// Alpine.js — AI voice chat sahifasi uchun
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
