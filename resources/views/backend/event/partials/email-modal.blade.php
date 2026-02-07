{{-- EMAIL MODAL --}}
<div class="modal fade" id="sendMailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="sendMailForm" class="modal-content">
      @csrf

      <input type="hidden" name="event_id" value="{{ $event->id }}">
      <input type="hidden" name="scope" id="mail_scope">
      <input type="hidden" name="category_event_id" id="mail_category">
      <input type="hidden" name="registration_id" id="mail_registration">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Send Email</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="row mb-2">
          <div class="col-md-6">
            <label class="form-label fw-semibold">From Name</label>
            <input type="text" name="from_name" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Reply To</label>
            <input type="email"
                   name="reply_to"
                   class="form-control"
                   value="{{ $event->email ?? config('mail.from.address') }}"
                   required>
          </div>
        </div>

        <input class="form-control mb-2" name="subject" placeholder="Subject" required>

        <div class="mb-2">
          <label class="form-label fw-semibold">Message</label>
          <div class="quill-wrapper">
            <div id="messageEditor" style="height:220px;"></div>
          </div>
          <textarea name="message" id="emailMessage" class="d-none"></textarea>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">
          <i class="ti ti-send me-1"></i>Send Email
        </button>
      </div>
    </form>
  </div>
</div>
