(function () {
    'use strict';
    // Readiness must come from the authorized review, never the matrix's set count.
    let revision = 0;
    let prompted = false;
    async function refreshProgression(event, deleted = false) {
        const section = document.querySelector('[data-rr-progression]');
        const button = section?.querySelector('.progress-draw');
        if (!button || (!deleted && event.detail?.mode !== 'RR')) return;
        const current = ++revision;
        try {
            const response = await window.jQuery.get(button.dataset.reviewUrl);
            if (current !== revision || !response.success || !response.review?.groups?.length) return;
            button.classList.remove('d-none');
            button.disabled = false;
            if (section.dataset.hasQualifiers === '1') return;
            const message = section.querySelector('[data-progress-message]');
            if (message) message.textContent = 'All group results are complete. Review the standings and confirm progression to the playoffs.';
            if (deleted || prompted) return;
            prompted = true;
            const offer = async () => {
                if (current !== revision) { prompted = false; return; }
                const result = await Swal.fire({
                    titleText: 'Group stage complete',
                    text: 'The score is saved. Review the final group order and progress to the playoffs?',
                    icon: 'question', showCancelButton: true,
                    confirmButtonText: 'Review & progress', cancelButtonText: 'Not now'
                });
                if (result.isConfirmed) await window.CTDrawProgression.review({
                    url: button.dataset.url, reviewUrl: button.dataset.reviewUrl,
                    drawName: button.dataset.drawName,
                    onSuccess: () => window.location.reload()
                });
            };
            const modal = document.getElementById('rrScoreModal');
            if (modal && (modal.classList.contains('show') || modal.style?.display === 'block')) window.jQuery(modal).one('hidden.bs.modal', offer);
            else await offer();
        } catch (error) {
            if (current !== revision) return;
            button.disabled = true;
            button.classList.add('d-none');
            if (error.status === 422) {
                prompted = false;
                const message = section.querySelector('[data-progress-message]');
                if (message && error.responseJSON?.message) message.textContent = error.responseJSON.message;
            }
        }
    }
    window.jQuery(function () {
        if (!window.AdminState) return;
        window.AdminState.on('score:saved', event => refreshProgression(event));
        window.AdminState.on('score:deleted', event => refreshProgression(event, true));
    });
})();
