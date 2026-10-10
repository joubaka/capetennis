(function () {
    'use strict';
    const config = window.CTLeaderboardRefresh;
    if (!config) return;
    const message = document.querySelector('[data-ratings-refresh-message]');
    const retry = document.querySelector('[data-ratings-refresh-retry]');
    let started;
    let reference;
    let timer;
    let busy = false;
    function stop(text) {
        message.textContent = text;
        retry.hidden = false;
    }
    function failure(kind, reference) { const error = new Error(kind); error.kind = kind; error.reference = reference; return error; }
    function explain(error, action) {
        const text = {
            forbidden: 'Your account cannot update these ratings.',
            session: 'Your session expired. Reload this page and sign in again before retrying.',
            server: `The server could not ${action} the ratings update. Retry shortly.`,
            invalid: 'The server returned an unexpected response. Reload this page before retrying.',
            network: `Unable to reach the server to ${action} the ratings update. Check your connection and retry.`
        };
        return (text[error.kind] || text.server) + (error.reference ? ` Reference: ${error.reference}.` : '');
    }
    async function read(url, method) {
        let response;
        try {
            response = await fetch(url, { method, credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', ...(method === 'POST' ? { 'X-CSRF-TOKEN': config.csrf } : {}) } });
        } catch (_) { throw failure('network'); }
        if (response.status === 401 || response.status === 419 || response.redirected) throw failure('session');
        if (response.status === 403) throw failure('forbidden');
        let data;
        try { data = await response.json(); }
        catch (_) { throw failure(response.ok ? 'invalid' : 'server'); }
        const reference = data?.error?.reference_id;
        const safeReference = typeof reference === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(reference) ? reference : undefined;
        if (!response.ok) throw failure('server', safeReference);
        if (!data || !data.status || typeof data.status.pending !== 'boolean' || typeof data.status.failed !== 'boolean' || typeof data.refreshing !== 'boolean') throw failure('invalid');
        if (data.launch_failure && !data.refreshing) {
            const id = data.launch_failure.reference_id;
            throw failure('server', typeof id === 'string' && /^[0-9a-f-]{36}$/i.test(id) ? id : undefined);
        }
        return data;
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
                stop('The ratings update could not finish. Recorded players remain visible; retry the update.' + (reference ? ` Reference: ${reference}.` : ''));
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
        } catch (error) {
            stop(explain(error, 'check'));
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
            const data = await read(config.requestUrl, 'POST');
            reference = typeof data.reference_id === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(data.reference_id) ? data.reference_id : undefined;
            timer = window.setTimeout(poll, 5000);
        } catch (error) {
            stop(explain(error, 'start'));
        } finally { busy = false; }
    }
    retry.addEventListener('click', start);
    start();
}());
