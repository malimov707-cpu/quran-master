for (const button of document.querySelectorAll('[data-certificate-share]')) {
    button.addEventListener('click', async () => {
        const status = button.parentElement.querySelector('[data-share-status]');
        button.disabled = true;
        try {
            const response = await fetch(button.dataset.certificateShare, { headers: { Accept: 'image/png' } });
            if (!response.ok) throw new Error('Rasmni yuklab bo‘lmadi. Qayta urinib ko‘ring.');
            const blob = await response.blob();
            const file = new File([blob], 'quran-ai-ustoz-certificate.png', { type: 'image/png' });
            if (navigator.canShare?.({ files: [file] })) {
                await navigator.share({ title: 'Qur’on AI Ustoz — Sertifikat', files: [file] });
                status.textContent = '';
            } else {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url; link.download = file.name; link.click();
                setTimeout(() => URL.revokeObjectURL(url), 10000);
                status.textContent = 'Rasm yuklandi. Uni Telegram yoki Instagram’ga yuklashingiz mumkin.';
            }
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = error.message;
        } finally { button.disabled = false; }
    });
}
