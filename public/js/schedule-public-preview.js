(() => {
    const page = document.querySelector('[data-schedule-preview-page]');
    if (!page) return;

    let loading = false;
    page.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-preview-url]');
        if (!button || loading || button.getAttribute('aria-pressed') === 'true') return;
        loading = true;
        const buttons = [...page.querySelectorAll('button[data-preview-url]')];
        const content = page.querySelector('[data-public-schedule-preview]');
        const status = page.querySelector('[data-preview-status]');
        buttons.forEach(control => { control.disabled = true; });
        content.setAttribute('aria-busy', 'true');
        status.classList.remove('text-danger');
        status.textContent = 'Loading preview…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(button.dataset.previewUrl, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: controller.signal
            });
            if (!response.ok || response.redirected) throw new Error('Preview unavailable');
            const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextPage = documentFragment.querySelector('[data-schedule-preview-page]');
            if (!nextPage) throw new Error('Preview unavailable');
            // Keep existing disclosure choices when replacing the schedule.
            const disclosures = new Map([...page.querySelectorAll('section[aria-label] details[data-live-key]')]
                .map(detail => [detail.closest('section').getAttribute('aria-label') + ':' + detail.dataset.liveKey, detail.open]));
            nextPage.querySelectorAll('section[aria-label] details[data-live-key]').forEach(detail => {
                const key = detail.closest('section').getAttribute('aria-label') + ':' + detail.dataset.liveKey;
                if (disclosures.has(key)) detail.open = disclosures.get(key);
            });
            // Included templates contain initialization scripts; existing delegated handlers remain active.
            nextPage.querySelectorAll('script').forEach(script => script.remove());
            const scrollX = window.scrollX;
            const scrollY = window.scrollY;
            page.replaceChildren(...nextPage.childNodes);
            history.replaceState(history.state, '', button.dataset.previewUrl);
            page.querySelector('[data-preview-status]').textContent = `${button.textContent.trim()} preview loaded.`;
            page.querySelectorAll('button[data-preview-url]').forEach(control => {
                if (control.dataset.previewUrl === button.dataset.previewUrl) control.focus({ preventScroll: true });
            });
            window.scrollTo(scrollX, scrollY);
        } catch (error) {
            status.textContent = 'Could not load this preview. Please try again.';
            status.classList.add('text-danger');
        } finally {
            clearTimeout(timeout);
            loading = false;
            page.querySelectorAll('button[data-preview-url]').forEach(control => { control.disabled = false; });
            page.querySelector('[data-public-schedule-preview]').setAttribute('aria-busy', 'false');
        }
    });
})();
