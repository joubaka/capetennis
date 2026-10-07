<div class="modal fade" id="sendMailModal" tabindex="-1" aria-labelledby="sendMailLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white py-2">
        <h5 class="modal-title" id="sendMailLabel">
          <i class="ti ti-mail me-50"></i> Review Email
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form id="sendMailForm">
        @csrf
      <input type="hidden" name="campaign_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        {{-- Event context --}}
        <input type="hidden" name="event_id" id="event_id" value="{{ $event->id }}">
        <input type="hidden" name="target_type" id="target_type" value="">

        {{-- ✅ Added: category_event_id stash for "Send email to category" button --}}
        <input type="hidden" name="catEvent" id="catEvent" value="">

        <div class="modal-body">
          <div id="emailAudienceSummary" class="alert alert-info d-none" role="status"></div>
          {{-- ✅ Recipient Type --}}
          <div class="mb-3">
            <label class="form-label fw-bold">Send To</label>
            <select id="emailRecipientSelect" name="to" class="form-select select2" style="width:100%">
              <option value="">Select recipient group...</option>

              {{-- Must match controller switch cases EXACTLY --}}
              <option value="All players in event">All players in Event</option>
              <option value="All players in region">All players in Region</option>
              <option value="All players in category">All players in Category</option>
              <option value="All players in team">All players in Team</option>

              <option value="All players in nominations">All players in Nominations</option>
              <option value="All nominated players">All nominated players</option>

              <option value="All Unregistered players in Event">All Unregistered players in Event</option>
              <option value="All Unregistered players in Region">All Unregistered players in Region</option>
              <option value="All Unregistered players in Team">All Unregistered players in Team</option>
            </select>
          </div>

          {{-- ✅ Conditional Selectors --}}
          <div class="mb-3 d-none" id="regionSelectWrapper">
            <label class="form-label fw-bold">Select Region</label>
            <select id="emailRegionSelect" name="region_id" class="form-select select2"></select>
          </div>

          <div class="mb-3 d-none" id="teamSelectWrapper">
            <label class="form-label fw-bold">Select Team</label>
            <select id="emailTeamSelect" name="team_id" class="form-select select2"></select>
          </div>

          <div class="mb-3 d-none" id="categorySelectWrapper">
            <label class="form-label fw-bold">Select Category</label>
            {{-- Uses same field name as hidden catEvent; dropdown will override hidden if used --}}
            <select id="emailCategorySelect" name="catEvent" class="form-select select2"></select>
          </div>

          {{-- ✅ Sender Info --}}
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label fw-bold">From Name</label>
              <input type="text"
                     class="form-control"
                     name="fromName"
                     id="fromName"
                     value="{{ auth()->user()->name ?? 'Cape Tennis Admin' }}">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label fw-bold">Reply-To</label>
              <input type="email"
                     class="form-control"
                     name="replyTo"
                     id="replyTo"
                     value="{{ auth()->user()->email ?? 'info@capetennis.co.za' }}">
            </div>
          </div>
          <input type="hidden" name="player_id" id="emailPlayerId" value="">
           <input type="hidden" name="team_id" id="emailTeamId" value="">
          {{-- ✅ Subject --}}
          <div class="mb-2">
            <label class="form-label fw-bold">Subject</label>
            <input type="text" class="form-control" id="emailSubject" name="emailSubject" required>
          </div>
          <input type="hidden" name="to" id="emailToHidden" value="">

          {{-- ✅ Message (Quill) --}}
          <div class="mb-3">
            <label class="form-label fw-bold">Message</label>
            <div id="messageEditor" style="height: 250px;"></div>
            <textarea name="message" id="emailMessage" class="d-none"></textarea>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-list-check me-1"></i> Review recipients & message
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('sendMailModal');
  modal?.addEventListener('show.bs.modal', function () {
    const input = modal.querySelector('[name="campaign_key"]');
    if (input) input.value = window.crypto?.randomUUID ? window.crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const value = Math.floor(Math.random() * 16); return (c === 'x' ? value : (value & 3) | 8).toString(16); });
  });
});
</script>
