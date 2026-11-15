@once
<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('.edit-score-btn, .open-score-modal, .insertResult, .editResult, .set-result-btn');
    if (!button) return;
    const legacy = button.matches('.insertResult, .editResult, .set-result-btn');
    const forms = legacy
        ? document.querySelectorAll('#tennisResultForm, #submit-result-form')
        : [document.getElementById(button.classList.contains('open-score-modal') ? 'scoreForm' : 'editScoreForm')];
    forms.forEach(function (form) {
        if (!form) return;
        [['participant_revision', button.dataset.participantRevision], ['score_revision', button.dataset.scoreRevision]].forEach(function (entry) {
            let field = form.querySelector('[name="' + entry[0] + '"]');
            if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = entry[0]; form.append(field); }
            // Capture the reviewed row when opening; background updates must not upgrade an open draft.
            field.value = entry[1] || '';
        });
    });
}, true);
</script>
@endonce
