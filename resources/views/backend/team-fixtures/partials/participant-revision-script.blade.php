<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('.edit-score-btn, .open-score-modal');
    if (!button || !button.dataset.participantRevision) return;
    const form = document.getElementById(button.classList.contains('open-score-modal') ? 'scoreForm' : 'editScoreForm');
    if (!form) return;
    let field = form.querySelector('[name="participant_revision"]');
    if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'participant_revision'; form.append(field); }
    field.value = button.dataset.participantRevision;
}, true);
</script>
