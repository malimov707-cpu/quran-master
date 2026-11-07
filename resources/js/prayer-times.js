import { t } from './ui-translations';

for (const widget of document.querySelectorAll('[data-prayer-widget]')) {
    const city = widget.querySelector('[data-prayer-city]');
    const status = widget.querySelector('[data-prayer-status]');
    const rows = widget.querySelector('[data-prayer-times]');
    const next = widget.querySelector('[data-prayer-next]');
    const source = widget.querySelector('[data-prayer-source]');
    const retry = widget.querySelector('[data-prayer-retry]');
    let request;
    let countdown;
    let data;
    let receivedAt;
    try {
        const saved = localStorage.getItem('quran-prayer-city');
        if ([...city.options].some(option => option.value === saved)) city.value = saved;
    } catch {}
    function updateCountdown() {
        if (!data?.next) return;
        const seconds = Math.max(0, Math.ceil(data.next.remaining_seconds - (performance.now() - receivedAt) / 1000));
        const clock = [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60].map(value => String(value).padStart(2, '0')).join(':');
        next.textContent = `${t('Keyingi namoz')}: ${data.next.tomorrow ? `${t('Ertaga')} · ` : ''}${t(data.next.name)} ${data.next.time}${data.next.source_type === 'calculated' ? ` · ${t('Hisobiy')}` : ''} · ${clock}`;
    }
    async function load() {
        request?.abort();
        clearInterval(countdown);
        request = new AbortController();
        const current = request;
        status.hidden = false;
        status.textContent = t('Namoz vaqtlari yuklanmoqda…');
        rows.hidden = next.hidden = source.hidden = retry.hidden = true;
        try {
            const url = new URL(widget.dataset.endpoint, location.href);
            url.searchParams.set('city', city.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: current.signal });
            if (!response.ok) throw new Error('request');
            const result = await response.json();
            if (request !== current) return;
            data = result;
            receivedAt = performance.now();
            widget.querySelector('[data-prayer-date]').textContent = `· ${data.date}`;
            if (!data.available) throw new Error('unavailable');
            rows.replaceChildren();
            for (const time of data.times) {
                const cell = document.createElement('div');
                cell.className = 'rounded-xl bg-cream p-3 text-center';
                if (time.key === data.next?.key && !data.next.tomorrow) cell.classList.add('ring-2', 'ring-gold');
                const label = document.createElement('span');
                label.className = 'block text-xs text-graysoft';
                label.textContent = t(time.name);
                const value = document.createElement('strong');
                value.className = 'mt-2 block text-lg text-forest';
                value.textContent = time.time;
                cell.append(label, value);
                rows.append(cell);
            }
            status.hidden = true;
            rows.hidden = source.hidden = false;
            if (data.next) {
                next.hidden = false;
                updateCountdown();
                countdown = setInterval(updateCountdown, 1000);
            }
            widget.querySelector('[data-prayer-note]').textContent = data.source.type === 'calculated'
                ? t('Hisobiy jadval · Hanafiy. Mahalliy masjid taqvimidan farq qilishi mumkin.')
                : t('Mahalliy namoz taqvimi.');
            const link = widget.querySelector('[data-prayer-source-link]');
            link.textContent = `${t('Manba')}: ${data.source.name}`;
            link.href = data.source.url;
        } catch (error) {
            if (error.name === 'AbortError' || request !== current) return;
            data = null;
            status.textContent = t('Namoz vaqtlarini hozir olib bo‘lmadi. Keyinroq qayta urinib ko‘ring.');
            retry.hidden = false;
        }
    }
    city.addEventListener('change', () => {
        try { localStorage.setItem('quran-prayer-city', city.value); } catch {}
        void load();
    });
    retry.addEventListener('click', load);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) void load(); });
    setInterval(() => { if (!document.hidden) void load(); }, 60000);
    void load();
}
