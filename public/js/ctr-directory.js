(() => {
    const selector = '[data-ctr-directory]';
    let directory = document.querySelector(selector);
    const status = document.querySelector('[data-ctr-status]');
    if (!directory || !status || !window.fetch || !window.AbortController || !window.history.pushState) return;
    let pending;
    let renderedUrl = new URL(location.href);

    async function load(url, { push = true, pagination = false } = {}) {
        pending?.abort();
        const request = new AbortController();
        pending = request;
        directory.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating player results…';

        try {
            const response = await fetch(url, {
                headers: { 'X-CTR-Directory': '1', Accept: 'text/html' },
                signal: request.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Directory request failed');
            const html = await response.text();
            const next = new DOMParser().parseFromString(html, 'text/html').querySelector(selector);
            if (!next) throw new Error('Directory response missing');
            if (request.signal.aborted) return;

            const active = document.activeElement;
            const focusId = directory.contains(active) ? active.id : '';
            const optionsWereOpen = directory.querySelector('.ctr-sort-options')?.open;
            if (optionsWereOpen && next.querySelector('.ctr-sort-options')) {
                next.querySelector('.ctr-sort-options').open = true;
            }
            directory.replaceWith(next);
            directory = next;
            if (push && url.href !== location.href) history.pushState(null, '', url);
            renderedUrl = url;
            status.textContent = 'Player results updated.';

            if (pagination) {
                const heading = url.hash ? document.getElementById(url.hash.slice(1)) : null;
                const target = heading || directory.querySelector('#ctr-results');
                target.tabIndex = -1;
                target.focus();
            } else if (focusId) {
                document.getElementById(focusId)?.focus({ preventScroll: true });
            }
        } catch {
            if (request.signal.aborted) return;
            if (push) location.assign(url.href);
            else location.reload();
        } finally {
            if (pending === request) {
                directory.removeAttribute('aria-busy');
                pending = null;
            }
        }
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('.ctr-filters') || !directory.contains(form) || event.defaultPrevented) return;
        event.preventDefault();
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form)).toString();
        url.hash = '';
        load(url);
    });

    document.addEventListener('change', event => {
        if (event.target.id === 'players-per-page' && directory.contains(event.target)) {
            event.target.form?.requestSubmit?.();
        }
    });

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('.pagination a[href]');
        if (!link || !directory.contains(link) || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || url.pathname !== location.pathname) return;
        event.preventDefault();
        load(url, { pagination: true });
    });

    window.addEventListener('popstate', () => {
        const url = new URL(location.href);
        if (url.pathname === renderedUrl.pathname && url.search !== renderedUrl.search) {
            load(url, { push: false });
        } else if (pending) {
            pending.abort();
            status.textContent = '';
        }
    });
})();
