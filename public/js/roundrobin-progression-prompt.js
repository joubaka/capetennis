(function () {
    'use strict';
    // All qualification comes from the authorized canonical review.
    let revision = 0;
    let prompted = false;
    let blockedMessage = '';
    async function afterModal(current, callback) {
        const offer = async () => { if (current === revision) await callback(); };
        const modal = document.getElementById('rrScoreModal');
        if (modal && (modal.classList.contains('show') || modal.style?.display === 'block')) window.jQuery(modal).one('hidden.bs.modal', offer);
        else await offer();
    }
    async function refreshProgression(event, deleted = false) {
        const section = document.querySelector('[data-rr-progression]');
        const button = section?.querySelector('.progress-draw');
        if (!button || (!deleted && event.detail?.mode !== 'RR')) return;
        const current = ++revision;
        const disable = () => {
            button.disabled = true;
            if (section.dataset.progressKeepVisible !== '1') button.classList.add('d-none');
        };
        try {
            const response = await window.jQuery.get(button.dataset.reviewUrl);
            if (current !== revision || !response.success || !response.review?.groups?.length) return;
            const destination = response.review.destination || section.dataset.progressDestination || 'playoffs';
            const message = section.querySelector('[data-progress-message]');
            if (response.review.pending === false) {
                disable();
                if (message) message.textContent = 'Second-stage progression is already confirmed. Existing results and fixtures remain unchanged.';
                return;
            }
            button.classList.remove('d-none');
            button.disabled = false;
            blockedMessage = '';
            if (section.dataset.hasQualifiers === '1') return;
            if (message) message.textContent = 'All group results are complete. Review the standings and confirm progression to the ' + destination + '.';
            if (deleted || prompted) return;
            await afterModal(current, async () => {
                if (prompted) return;
                prompted = true;
                const result = await Swal.fire({
                    titleText: 'Group stage complete',
                    text: 'The score is saved. Review the final group order and progress to the ' + destination + '?',
                    icon: 'question', showCancelButton: true,
                    confirmButtonText: 'Review & progress', cancelButtonText: 'Not now'
                });
                if (result.isConfirmed) await window.CTDrawProgression.review({
                    url: button.dataset.url, reviewUrl: button.dataset.reviewUrl,
                    drawName: button.dataset.drawName,
                    onSuccess: () => window.location.reload()
                });
            });
        } catch (error) {
            if (current !== revision) return;
            disable();
            if (error.status === 422 || error.status === 409) {
                prompted = false;
                const data = error.responseJSON || {};
                const message = section.querySelector('[data-progress-message]');
                if (message && data.message) message.textContent = data.message;
                const counts = data.round_robin;
                if (deleted || data.pending === false || !counts || counts.total < 1 || counts.completed !== counts.total) {
                    blockedMessage = '';
                    return;
                }
                const reason = data.message || 'Review the group results before progressing.';
                if (blockedMessage === reason) return;
                await afterModal(current, async () => {
                    if (blockedMessage === reason) return;
                    blockedMessage = reason;
                    const result = await Swal.fire({titleText: 'Group results need review', text: reason, icon: 'warning',
                        showCancelButton: true, confirmButtonText: 'Review standings', cancelButtonText: 'Not now'});
                    if (result.isConfirmed) document.getElementById('standings-tab')?.click();
                });
            }
        }
    }
    window.jQuery(function () {
        if (!window.AdminState) return;
        window.AdminState.on('score:saved', event => refreshProgression(event));
        window.AdminState.on('score:deleted', event => refreshProgression(event, true));
    });
})();
