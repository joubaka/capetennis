(function () {
    'use strict';
    const prompted = new Set();
    const blocked = new Map();
    function state(drawId) {
        const data = document.querySelector('.scoring-shell')?.dataset.playoffProgression;
        return data ? JSON.parse(data)[drawId] : undefined;
    }
    async function afterSave(match, before, refresh) {
        const after = state(match.drawId);
        if (match.engine !== 'standard' || match.stage !== 'RR' || !before || !after || !after.pending) return;
        if (after.total < 1 || after.completed !== after.total) {
            prompted.delete(match.drawId); blocked.delete(match.drawId); return;
        }
        if (after.ready === false) {
            prompted.delete(match.drawId);
            const reason = after.message || 'Review the group results before progressing.';
            if (blocked.get(match.drawId) === reason) return;
            blocked.set(match.drawId, reason);
            const result = await Swal.fire({titleText: 'Group results need review', text: reason, icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Review standings', cancelButtonText: 'Not now'});
            if (result.isConfirmed && after.standingsUrl) window.location.assign(after.standingsUrl);
            return;
        }
        blocked.delete(match.drawId);
        if (prompted.has(match.drawId)) return;
        prompted.add(match.drawId);
        const destination = after.destination || 'playoffs';
        const result = await Swal.fire({
            titleText: 'Group stage complete',
            text: 'The score is saved. All group results for ' + after.drawName + ' are complete. Review the standings and progress to the ' + destination + '?',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Review & progress', cancelButtonText: 'Not now'
        });
        if (result.isConfirmed) await window.CTDrawProgression.review({...after, onSuccess: refresh});
    }
    window.CTScoringPlayoffs = {state, afterSave};
})();
