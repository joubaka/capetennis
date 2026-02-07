@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Announcements')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Event Announcements</h4>

      <button class="btn btn-primary btn-sm" id="newAnnouncementBtn">
        <i class="ti ti-plus me-1"></i>New Announcement
      </button>
    </div>
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
    <button class="btn btn-outline-secondary btn-sm edit-announcement-btn">
      Edit
    </button>

    <button
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
<div class="modal fade" id="announcementModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="announcementForm" class="modal-content">
      @csrf

      <input type="hidden" id="announcement_id">

      <div class="modal-header">
        <h5 class="modal-title">Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" id="announcement_title" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Message</label>
          <textarea id="announcement_message" class="form-control" rows="6" required></textarea>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="announcement_send_email">
          <label class="form-check-label">
            Send announcement email to all players in this event
          </label>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button class="btn btn-primary">Save Announcement</button>
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
<script>
const csrf  = document.querySelector('meta[name="csrf-token"]').content;
const modal = new bootstrap.Modal(document.getElementById('announcementModal'));

console.log('[ANNOUNCEMENTS] Script loaded');

/* NEW */
document.getElementById('newAnnouncementBtn').addEventListener('click', () => {
  console.log('[ANNOUNCEMENTS] New announcement clicked');
  announcementForm.reset();
  announcement_id.value = '';
  announcement_send_email.checked = false;
  modal.show();
});

/* EDIT */
document.addEventListener('click', e => {
  const btn = e.target.closest('.edit-announcement-btn');
  if (!btn) return;

  const id  = btn.closest('tr').dataset.id;
  const url = showAnnouncementUrlTemplate.replace('__ID__', id);

  console.log('[ANNOUNCEMENTS] Edit click', { id, url });

  fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(r => {
      console.log('[ANNOUNCEMENTS] Edit response status', r.status);
      return r.json();
    })
    .then(a => {
      console.log('[ANNOUNCEMENTS] Edit payload', a);
      announcement_id.value = a.id;
      announcement_title.value = a.title;
      announcement_message.value = a.message;
      announcement_send_email.checked = false;
      modal.show();
    })
    .catch(err => {
      console.error('[ANNOUNCEMENTS] Edit failed', err);
      alert('Failed to load announcement');
    });
});

/* SAVE */
announcementForm.addEventListener('submit', e => {
  e.preventDefault();

  const id  = announcement_id.value;
  const url = id
    ? updateAnnouncementUrlTemplate.replace('__ID__', id)
    : storeAnnouncementUrl;

  console.log('[ANNOUNCEMENTS] Save submit', { id, url });

  fetch(url, {
    method: id ? 'PATCH' : 'POST',
    headers: {
      'X-CSRF-TOKEN': csrf,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title: announcement_title.value,
      message: announcement_message.value,
      sendMail: announcement_send_email.checked ? 1 : 0
    })
  })
  .then(r => {
    console.log('[ANNOUNCEMENTS] Save response status', r.status);
    if (!r.ok) throw r;
    return r.json();
  })
  .then(res => {
    console.log('[ANNOUNCEMENTS] Save success', res);
    modal.hide();
    location.reload();
  })
  .catch(err => {
    console.error('[ANNOUNCEMENTS] Save failed', err);
    alert('Save failed');
  });
});

/* HIDE (SOFT DELETE) */
document.addEventListener('click', e => {
  const btn = e.target.closest('.toggle-announcement-btn');
  if (!btn) return;

  const row = btn.closest('tr');
  const id  = row.dataset.id;

  const url = toggleAnnouncementUrlTemplate.replace('__ID__', id);

  console.log('[ANNOUNCEMENTS] Toggle click', { id, url });

  fetch(url, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': csrf,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ _method: 'PATCH' })
  })
  .then(r => {
    console.log('[ANNOUNCEMENTS] Toggle response status', r.status);
    if (!r.ok) throw r;
    return r.json();
  })
  .then(res => {
    console.log('[ANNOUNCEMENTS] Toggle response payload', res);

    const hidden = res.hidden;

    row.dataset.hidden = hidden ? 1 : 0;
    row.classList.toggle('table-secondary', hidden);

    btn.textContent = hidden ? 'Show' : 'Hide';
    btn.classList.toggle('btn-outline-danger', !hidden);
    btn.classList.toggle('btn-outline-success', hidden);
  })
  .catch(err => {
    console.error('[ANNOUNCEMENTS] Toggle failed', err);
    alert('Failed to toggle announcement');
  });
});
</script>
@endsection

