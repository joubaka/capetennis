(function () {
    'use strict';
    const config = window.CTLeaderboardRefresh;
    if (!config) return;
    const message = document.querySelector('[data-ratings-refresh-message]');
    const retry = document.querySelector('[data-ratings-refresh-retry]');
    let started;
    let timer;
    let busy = false;
    function stop(text) {
        message.textContent = text;
        retry.hidden = false;
    }
    async function read(url, method) {
        const response = await fetch(url, { method, credentials: 'same-origin', cache: 'no-store',
            headers: { Accept: 'application/json', ...(method === 'POST' ? { 'X-CSRF-TOKEN': config.csrf } : {}) } });
        if (!response.ok) throw new Error('Refresh unavailable');
        return response.json();
    }
    async function poll() {
        if (Date.now() - started >= 600000) {
            stop('The calculation is taking longer than expected. You can retry checking for an update.');
            return;
        }
        try {
            const data = await read(config.statusUrl, 'GET');
            if (data.status.snapshot_version && data.status.snapshot_version !== config.snapshotVersion && !data.status.failed) {
                window.location.reload();
                return;
            }
            if (data.status.version !== config.version && !data.status.pending && !data.status.failed) {
                window.location.reload();
                return;
            }
            if (!data.refreshing && data.status.failed) {
                stop('The ratings update could not finish. Recorded players remain visible; retry the update.');
                return;
            }
            if (!data.refreshing && !data.status.pending) {
                window.location.reload();
                return;
            }
            if (!data.refreshing && data.status.pending) {
                stop('The ratings calculation has not finished. Retry the update in a minute.');
                return;
            }
            timer = window.setTimeout(poll, 5000);
        } catch (_) {
            stop('Unable to check the ratings update. Retry when your connection is available.');
        }
    }
    async function start() {
        if (busy) return;
        busy = true;
        window.clearTimeout(timer);
        retry.hidden = true;
        message.textContent = 'Updating ratings. This page will refresh automatically when the calculation is ready.';
        started = Date.now();
        try {
            await read(config.requestUrl, 'POST');
            timer = window.setTimeout(poll, 5000);
        } catch (_) {
            stop('Unable to start the ratings update. Retry when your connection is available.');
        } finally { busy = false; }
    }
    retry.addEventListener('click', start);
    start();
}());
