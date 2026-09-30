@extends('layouts.backend')

@section('title', $event->name . ' – Announcements')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="container-xl">
  @include('backend.event.partials.header', [
    'eventWorkspaceActive' => 'more',
    'eventWorkspaceIcon' => 'ti-megaphone',
    'eventWorkspaceSubtitle' => 'Event announcements',
  ])
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Event announcements</h2><p class="text-muted mb-0">Publish updates and optionally email nominated and registered players.</p></div>
      <button type="button" class="btn btn-primary btn-sm" id="newAnnouncementBtn">
        <i class="ti ti-plus me-1"></i>New Announcement
      </button>
  </div>

  {{-- LIST --}}
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
       @forelse($event->announcements as $announcement)
<tr
  data-id="{{ $announcement->id }}"
  data-hidden="{{ $announcement->trashed() ? 1 : 0 }}"
  class="{{ $announcement->trashed() ? 'table-secondary' : '' }}"
>
  <td>
    <strong>{{ $announcement->title }}</strong>

    <div class="mt-2 small text-muted">
      {!! $announcement->message !!}
    </div>
  </td>

  <td class="align-top">
    {{ $announcement->created_at->format('d M Y H:i') }}
  </td>

  <td class="text-end align-top">
    <button type="button" class="btn btn-outline-secondary btn-sm edit-announcement-btn">
      Edit
    </button>

    <button type="button"
      class="btn btn-sm toggle-announcement-btn
        {{ $announcement->trashed() ? 'btn-outline-success' : 'btn-outline-danger' }}">
      {{ $announcement->trashed() ? 'Show' : 'Hide' }}
    </button>
  </td>
</tr>
@empty
<tr>
  <td colspan="3" class="text-center text-muted py-3">
    No announcements yet.
  </td>
</tr>
@endforelse

        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- MODAL --}}
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="announcementForm" class="modal-content">
      @csrf

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
            Email all nominated and active paid registered players
          </label>
          <div class="form-text">Email is queued when you save. Leaving this clear only publishes the announcement online.</div>
        </div>

        <div id="announcementRecipientReview" class="border rounded p-3 mt-3 d-none">
          <div class="d-flex justify-content-between gap-3 mb-2">
            <strong>Exact email recipients</strong>
            <span class="badge bg-primary">{{ $announcementRecipients->count() }}</span>
          </div>
          @if($announcementRecipients->isEmpty())
            <p class="text-muted mb-0">No valid nominated or registered player email addresses are currently available.</p>
          @else
            <div class="small overflow-auto mb-3" style="max-height: 220px;">
              @foreach($announcementRecipients as $recipient)
                <div class="py-1 border-bottom">
                  <span>{{ $recipient['name'] ?: 'Player' }}</span>
                  <span class="text-muted">&lt;{{ $recipient['email'] }}&gt;</span>
                </div>
              @endforeach
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="announcement_confirm_recipients">
              <label class="form-check-label" for="announcement_confirm_recipients">
                I confirm this exact recipient list
              </label>
            </div>
          @endif
          <input type="hidden" id="announcement_recipient_hash" value="{{ $announcementRecipientHash }}">
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
    @json(route('admin.announcements.toggle', ['announcement' => '__ID__']));
</script>

{{-- ROUTE TEMPLATES --}}
<script>
  const storeAnnouncementUrl = @json(route('admin.events.announcements.store', $event));
  const updateAnnouncementUrlTemplate = @json(route('admin.announcements.update', ['announcement' => '__ID__']));
  const showAnnouncementUrlTemplate   = @json(route('admin.announcements.show', ['announcement' => '__ID__']));
  const deleteAnnouncementUrlTemplate = @json(route('admin.announcements.destroy', ['announcement' => '__ID__']));
</script>
@endsection


@section('page-script')
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
    AppFeedback.afterReload(result.message || (id ? 'Announcement updated.' : 'Announcement published.'));
    modal.hide();
    location.reload();
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
@endsection

