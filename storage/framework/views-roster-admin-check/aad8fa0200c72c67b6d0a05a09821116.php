
<div class="modal fade" id="sendMailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="sendMailForm" class="modal-content">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="campaign_key" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">

      <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
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
            <input type="text" name="from_name" class="form-control" value="<?php echo e(auth()->user()->name); ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Reply To</label>
            <input type="email"
                   name="reply_to"
                   class="form-control"
                   value="<?php echo e($event->email ?? config('mail.from.address')); ?>"
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
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">
          <i class="ti ti-send me-1"></i>Preview email
        </button>
      </div>
    </form>
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
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\partials\email-modal.blade.php ENDPATH**/ ?>