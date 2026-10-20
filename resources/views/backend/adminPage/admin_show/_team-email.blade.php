<div class="modal fade" id="team-email-purpose" tabindex="-1" aria-labelledby="team-email-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="team-email-title">Email</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p data-team-email-context class="fw-semibold"></p>
      <div data-team-email-error class="alert alert-warning" role="alert" hidden></div>
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-outline-primary text-start d-flex flex-column align-items-start gap-1" data-team-email-purpose="custom"><strong>Custom message</strong><span class="d-block small">Start with a blank subject and message for these players.</span></button>
        <button type="button" class="btn btn-outline-primary text-start d-flex flex-column align-items-start gap-1" data-team-email-purpose="invitation"><strong>Invitation email</strong><span class="d-block small" data-invitation-purpose-copy>Prepare the regional campaign, or review this player’s saved invitation.</span></button>
        <button type="button" class="btn btn-outline-primary text-start d-flex flex-column align-items-start gap-1" data-team-email-purpose="reminder"><strong>Reminder to register</strong><span class="d-block small">Choose active players who still need to register; retain each player’s personal link.</span></button>
        <button type="button" class="btn btn-outline-primary text-start d-flex flex-column align-items-start gap-1" data-team-email-purpose="pending"><strong>Newly added invitations</strong><span class="d-block small" data-pending-purpose-copy>Check players added since the regional campaign was sent.</span></button>
      </div>
      <div class="border rounded p-3 mt-3" data-team-email-invitation-picker hidden>
        <label class="form-label" for="team-email-invitation-player">Choose an active player</label>
        <select id="team-email-invitation-player" class="form-select mb-2" data-team-email-invitation-player></select>
        <button type="button" class="btn btn-primary" data-team-email-review-invitation>Review invitation</button>
      </div>
      <p class="small text-muted mt-3 mb-0">Invitations and reminders use their own templates and registration links. A custom message does not send or activate an invitation.</p>
    </div>
  </div></div>
</div>
