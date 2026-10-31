(function () {
    'use strict';
    const prompted = new Set();

    function state(drawId) {
        const data = document.querySelector('.scoring-shell')?.dataset.playoffProgression;
        return data ? JSON.parse(data)[drawId] : undefined;
    }

    async function afterSave(match, before, refresh) {
        const after = state(match.drawId);
        if (match.engine !== 'standard' || match.stage !== 'RR' || match.hasResult !== '0'
            || !before || !after || !after.pending || before.total < 1
            || before.completed !== before.total - 1 || after.completed !== after.total
            || prompted.has(match.drawId)) return;

        prompted.add(match.drawId);
        const result = await Swal.fire({
            titleText: 'Group stage complete',
            text: 'The score is saved. All group results for ' + after.drawName + ' are complete. Review the standings and progress to the playoffs?',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Review & progress', cancelButtonText: 'Not now'
        });
        if (result.isConfirmed) {
            await window.CTDrawProgression.review({...after, onSuccess: refresh});
        }
    }

    window.CTScoringPlayoffs = {state, afterSave};
})();
