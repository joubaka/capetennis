

<div class="modal fade" id="addAnouncement" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <h5 class="card-header">Create Announcements</h5>
        <div class="sk-chase sk-primary mySpinner d-none m-5">…</div>
        <div class="card-body">
          <div id="full-editor"></div>
        </div>

        <label class="switch mt-3">
          <input type="checkbox" class="switch-input is-valid sendEmail" name="sendMail">
          <span class="switch-toggle-slider">
            <option value="1" class="switch-on"></option>
            <option value="0" class="switch-off"></option>
          </span>
          <span class="switch-label">Send announcement email to all players in event</span>
        </label>

        <div class="btn btn-primary btn-sm mt-4" id="addAnnouncementButton">Create Announcement</div>
        <input type="hidden" id="announcement_event_id" value="{{ $event->id }}">
      </div>
    </div>
  </div>
</div>
