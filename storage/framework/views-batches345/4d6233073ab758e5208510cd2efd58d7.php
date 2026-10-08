

<?php $__env->startSection('title', $event->name . ' – Announcements'); ?>

<?php $__env->startSection('content'); ?>
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="container-xl">
  <?php echo $__env->make('backend.event.partials.header', [
    'eventWorkspaceActive' => 'more',
    'eventWorkspaceIcon' => 'ti-megaphone',
    'eventWorkspaceSubtitle' => 'Event announcements',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Event announcements</h2><p class="text-muted mb-0"><?php echo e($event->isTeam() ? 'Publish updates and optionally email every roster player and their linked parents.' : 'Publish updates and optionally email nominated and registered players.'); ?></p></div>
      <button type="button" class="btn btn-primary btn-sm" id="newAnnouncementBtn">
        <i class="ti ti-plus me-1"></i>New Announcement
      </button>
  </div>

  
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>Title</th>
            <th>Date</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
       <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr
  data-id="<?php echo e($announcement->id); ?>"
  data-hidden="<?php echo e($announcement->trashed() ? 1 : 0); ?>"
  class="<?php echo e($announcement->trashed() ? 'table-secondary' : ''); ?>"
>
  <td>
    <strong><?php echo e($announcement->title); ?></strong>

    <div class="mt-2 small text-muted">
      <?php echo $announcement->message; ?>

    </div>
  </td>

  <td class="align-top">
    <?php echo e($announcement->created_at->format('d M Y H:i')); ?>

  </td>

  <td class="text-end align-top">
    <button type="button" class="btn btn-outline-secondary btn-sm edit-announcement-btn">
      Edit
    </button>

    <button type="button"
      class="btn btn-sm toggle-announcement-btn
        <?php echo e($announcement->trashed() ? 'btn-outline-success' : 'btn-outline-danger'); ?>">
      <?php echo e($announcement->trashed() ? 'Show' : 'Hide'); ?>

    </button>
  </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr>
  <td colspan="3" class="text-center text-muted py-3">
    No announcements yet.
  </td>
</tr>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </tbody>
      </table>
    </div>
  </div>

</div>


<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="announcementForm" class="modal-content">
      <?php echo csrf_field(); ?>

      <input type="hidden" id="announcement_id">

      <div class="modal-header">
        <h5 class="modal-title" id="announcementModalTitle">Create announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label" for="announcement_title">Title</label>
          <input type="text" id="announcement_title" name="title" class="form-control" maxlength="255" required>
        </div>

        <div class="mb-3">
          <label class="form-label" id="announcementMessageLabel">Message</label>
          <div id="announcement_message" style="height: 200px;" aria-labelledby="announcementMessageLabel"></div>
          <div class="form-text">This appears on the public event page. Add the practical detail participants need next.</div>
        </div>

        <div class="form-check" id="announcementEmailOption">
          <input class="form-check-input" type="checkbox" id="announcement_send_email">
          <label class="form-check-label" for="announcement_send_email">
            <?php echo e($event->isTeam() ? 'Email all team roster players and linked parents' : 'Email all nominated and active paid registered players'); ?>

          </label>
          <div class="form-text">Email is queued when you save. Leaving this clear only publishes the announcement online.</div>
        </div>

        <div id="announcementRecipientReview" class="border rounded p-3 mt-3 d-none">
          <div class="d-flex justify-content-between gap-3 mb-2">
            <strong>Exact email recipients</strong>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($announcementExcluded->isNotEmpty()): ?>
              <div class="alert alert-warning mt-2 mb-2"><?php echo e($announcementExcluded->count()); ?> player(s) have no valid contact address and will be recorded as skipped.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <span class="badge bg-primary"><?php echo e($announcementRecipients->count()); ?></span>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($announcementRecipients->isEmpty()): ?>
            <p class="text-muted mb-0"><?php echo e($event->isTeam() ? 'No valid roster player or parent email addresses are currently available.' : 'No valid nominated or registered player email addresses are currently available.'); ?></p>
          <?php else: ?>
            <div class="small overflow-auto mb-3" style="max-height: 220px;">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $announcementRecipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="py-1 border-bottom">
                  <span><?php echo e($recipient['name'] ?: 'Player'); ?></span>
                  <span class="text-muted">&lt;<?php echo e($recipient['email']); ?>&gt;</span>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="announcement_confirm_recipients">
              <label class="form-check-label" for="announcement_confirm_recipients">
                I confirm this exact recipient list
              </label>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <input type="hidden" id="announcement_recipient_hash" value="<?php echo e($announcementRecipientHash); ?>">
        </div>

        <div id="announcementFormFeedback" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"></div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" id="saveAnnouncementBtn">
          <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>
          <span class="save-label">Publish announcement</span>
        </button>
      </div>
    </form>
  </div>
</div>
<script>
  const toggleAnnouncementUrlTemplate =
    <?php echo json_encode(route('admin.announcements.toggle', ['announcement' => '__ID__']), 512) ?>;
</script>


<script>
  const storeAnnouncementUrl = <?php echo json_encode(route('admin.events.announcements.store', $event), 512) ?>;
  const updateAnnouncementUrlTemplate = <?php echo json_encode(route('admin.announcements.update', ['announcement' => '__ID__']), 512) ?>;
  const showAnnouncementUrlTemplate   = <?php echo json_encode(route('admin.announcements.show', ['announcement' => '__ID__']), 512) ?>;
  const deleteAnnouncementUrlTemplate = <?php echo json_encode(route('admin.announcements.destroy', ['announcement' => '__ID__']), 512) ?>;
</script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const csrf  = document.querySelector('meta[name="csrf-token"]').content;
const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
const announcementForm = document.getElementById('announcementForm');
const announcementId = document.getElementById('announcement_id');
const announcementTitle = document.getElementById('announcement_title');
const announcementSendEmail = document.getElementById('announcement_send_email');
const announcementEmailOption = document.getElementById('announcementEmailOption');
const announcementRecipientReview = document.getElementById('announcementRecipientReview');
const announcementConfirmRecipients = document.getElementById('announcement_confirm_recipients');
const announcementRecipientHash = document.getElementById('announcement_recipient_hash');
const modalTitle = document.getElementById('announcementModalTitle');
const saveButton = document.getElementById('saveAnnouncementBtn');
const saveLabel = saveButton.querySelector('.save-label');
const saveSpinner = saveButton.querySelector('.spinner-border');
const formFeedback = document.getElementById('announcementFormFeedback');

function setFormFeedback(message = '', type = 'danger') {
  formFeedback.textContent = message;
  formFeedback.className = `alert alert-${type} mt-3 mb-0${message ? '' : ' d-none'}`;
  formFeedback.setAttribute('role', type === 'danger' ? 'alert' : 'status');
}

function setSaving(saving) {
  saveButton.disabled = saving;
  saveSpinner.classList.toggle('d-none', !saving);
  announcementForm.setAttribute('aria-busy', saving ? 'true' : 'false');
  if (saving) saveLabel.textContent = announcementId.value ? 'Saving changes…' : 'Publishing…';
}

function syncRecipientReview() {
  const reviewing = !announcementId.value && announcementSendEmail.checked;
  announcementRecipientReview.classList.toggle('d-none', !reviewing);
}

announcementSendEmail.addEventListener('change', () => {
  if (!announcementSendEmail.checked && announcementConfirmRecipients) {
    announcementConfirmRecipients.checked = false;
  }
  syncRecipientReview();
});

// Initialize Quill editor
const quill = new Quill('#announcement_message', {
  theme: 'snow',
  modules: {
    toolbar: [
      ['bold', 'italic', 'underline'],
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      ['link'],
      ['clean']
    ]
  },
  placeholder: 'Write your announcement...'
});

/* NEW */
document.getElementById('newAnnouncementBtn').addEventListener('click', () => {
  announcementForm.reset();
  announcementId.value = '';
  quill.root.innerHTML = '';
  announcementSendEmail.checked = false;
  announcementEmailOption.classList.remove('d-none');
  syncRecipientReview();
  modalTitle.textContent = 'Create announcement';
  saveLabel.textContent = 'Publish announcement';
  setFormFeedback();
  modal.show();
  document.getElementById('announcementModal').addEventListener('shown.bs.modal', () => announcementTitle.focus(), { once: true });
});

/* EDIT */
document.addEventListener('click', async e => {
  const btn = e.target.closest('.edit-announcement-btn');
  if (!btn) return;

  const id  = btn.closest('tr').dataset.id;
  const url = showAnnouncementUrlTemplate.replace('__ID__', id);

  btn.disabled = true;
  try {
    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!response.ok) throw await AppFeedback.responseError(response, 'Failed to load the announcement.');
    const announcement = await response.json();
    announcementId.value = announcement.id;
    announcementTitle.value = announcement.title;
    quill.root.innerHTML = announcement.message;
    announcementSendEmail.checked = false;
    announcementEmailOption.classList.add('d-none');
    announcementRecipientReview.classList.add('d-none');
    modalTitle.textContent = 'Edit announcement';
    saveLabel.textContent = 'Save changes';
    setFormFeedback('Editing changes the public announcement only. Previously queued emails are not resent.', 'info');
    modal.show();
  } catch (error) {
    AppFeedback.fromError(error, 'Failed to load the announcement.');
  } finally {
    btn.disabled = false;
  }
});

/* SAVE */
announcementForm.addEventListener('submit', async e => {
  e.preventDefault();

  const id  = announcementId.value;
  const url = id
    ? updateAnnouncementUrlTemplate.replace('__ID__', id)
    : storeAnnouncementUrl;

  setFormFeedback();
  if (!announcementTitle.value.trim()) {
    setFormFeedback('Enter an announcement title before saving.');
    announcementTitle.focus();
    return;
  }
  if (!quill.getText().trim()) {
    setFormFeedback('Enter an announcement message before saving.');
    quill.focus();
    return;
  }
  if (!id && announcementSendEmail.checked && (!announcementConfirmRecipients || !announcementConfirmRecipients.checked)) {
    setFormFeedback('Review and confirm the exact recipient list before queueing email.');
    announcementConfirmRecipients?.focus();
    return;
  }

  setSaving(true);
  try {
    const response = await fetch(url, {
      method: id ? 'PATCH' : 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        title: announcementTitle.value.trim(),
        message: quill.root.innerHTML,
        sendMail: !id && announcementSendEmail.checked ? 1 : 0,
        confirm_recipients: !id && announcementSendEmail.checked && announcementConfirmRecipients?.checked ? 1 : 0,
        recipient_hash: !id && announcementSendEmail.checked ? announcementRecipientHash?.value : null
      })
    });
    if (!response.ok) throw await AppFeedback.responseError(response, 'Could not save the announcement.');
    const result = await response.json();
    AppFeedback.afterReload(result.message || (id ? 'Announcement updated.' : 'Announcement published.'), result.mail_level || 'success');
    modal.hide();
    if (result.report_url) window.location.assign(result.report_url);
    else location.reload();
  } catch (error) {
    AppFeedback.fromError(error, 'Could not save the announcement.');
    setFormFeedback(error.messages?.[0] || error.message || 'Could not save the announcement.');
    setSaving(false);
  }
});

/* HIDE (SOFT DELETE) */
document.addEventListener('click', async e => {
  const btn = e.target.closest('.toggle-announcement-btn');
  if (!btn) return;

  const row = btn.closest('tr');
  const id  = row.dataset.id;

  const url = toggleAnnouncementUrlTemplate.replace('__ID__', id);

  btn.disabled = true;
  try {
    const response = await fetch(url, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    if (!response.ok) throw await AppFeedback.responseError(response, 'Could not change announcement visibility.');
    const res = await response.json();
    const hidden = res.hidden;

    row.dataset.hidden = hidden ? 1 : 0;
    row.classList.toggle('table-secondary', hidden);

    btn.textContent = hidden ? 'Show' : 'Hide';
    btn.classList.toggle('btn-outline-danger', !hidden);
    btn.classList.toggle('btn-outline-success', hidden);
    AppFeedback.success(res.message || (hidden ? 'Announcement hidden.' : 'Announcement is visible again.'));
  } catch (error) {
    AppFeedback.fromError(error, 'Could not change announcement visibility.');
  } finally {
    btn.disabled = false;
  }
});
</script>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\announcements.blade.php ENDPATH**/ ?>