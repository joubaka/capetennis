@extends('layouts/contentNavbarLayout')

@section('title', 'Masters invitations')

@section('page-style')
<style>
  .masters-player-list { overflow:visible; }
  .masters-player-row { border-top:1px solid #ebeaf0; padding:.55rem 0; }
  .masters-player-row .form-check-input { margin-top:.25rem; }
  .masters-entry-table { width:100%; border:1px solid #ebeaf0; border-radius:.35rem; overflow-x:auto; overflow-y:hidden; }
  .masters-entry-head, .masters-entry-row { display:grid; grid-template-columns:1.75rem minmax(7rem,1.15fr) minmax(8rem,1.5fr) minmax(5.8rem,.85fr) minmax(7.2rem,1fr) 4.8rem; align-items:center; gap:.45rem; padding:.55rem .55rem; }
  .masters-entry-head { background:#e4e4e9; color:#625f6d; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .masters-entry-row { border-top:1px solid #ebeaf0; font-size:.82rem; }
  .masters-entry-row:first-child { border-top:0; }
  .masters-entry-row a { word-break:break-word; }
  .masters-entry-row .contact-cell { min-width:0; }
  .masters-entry-row .contact-cell small { display:block; color:#8b8794; }
  .masters-entry-row .contact-cell .email-link { display:block; overflow-wrap:anywhere; }
  .masters-entry-row .action-cell form { display:inline-block; }
  .masters-entry-row .action-cell { white-space:nowrap; }
  @media (max-width: 900px) { .masters-entry-head { display:none; } .masters-entry-row { grid-template-columns:2rem minmax(9rem,1fr) minmax(7rem,1fr) auto; } .masters-entry-row .email-cell, .masters-entry-row .cell-cell, .masters-entry-row .payment-cell { grid-column:2 / span 2; } }
  .masters-groups { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; }
  .masters-groups .card { margin-bottom:0 !important; }
  .masters-groups .card-body { padding:1rem 1.1rem; }
  .masters-groups h5 { font-size:1rem; margin-bottom:.35rem; }
  .masters-groups p { margin-bottom:.35rem; font-size:.85rem; }
  @media (max-width: 900px) { .masters-groups { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2"><div><h4>Masters invitation batch</h4><p class="text-muted">{{ $batch->event->name ?? 'Event' }} · ranking run {{ $batch->ranking_run_id }}</p></div><div class="d-flex gap-2"><a href="{{ route('admin.events.overview', $batch->event_id) }}" class="btn btn-outline-primary">Back to Masters Dashboard</a>@if($batch->series_id)<a href="{{ route('series.events', $batch->series_id) }}" class="btn btn-outline-secondary">Back to Series</a>@endif</div></div>
  <div class="alert {{ $readiness['status'] === 'blocked' ? 'alert-danger' : ($readiness['status'] === 'warning' ? 'alert-warning' : 'alert-success') }}">
    Readiness: {{ ucfirst($readiness['status']) }}
  </div>
  @if($batch->status !== 'sent')
    <div class="alert alert-light border mb-3"><strong>What to do on this page:</strong> Set the deadlines players will see in their invitation, confirm the selected invitees below, then save these details before sending.</div>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-1">Step 2: review and send invitations</h5><p class="text-muted small mb-0">Review the invitee names below, adjust the invitation wave if needed, then send all selected invitations.</p></div><div class="card-body"><form method="POST" action="{{ route('backend.masters.details.update', $batch) }}">@csrf @method('PATCH')<div class="row g-3"><div class="col-md-4"><label class="form-label">Response deadline</label><input name="response_deadline" type="datetime-local" class="form-control" value="{{ $batch->response_deadline?->format('Y-m-d\\TH:i') }}" required></div><div class="col-md-4"><label class="form-label">Payment deadline</label><input name="payment_deadline" type="datetime-local" class="form-control" value="{{ $batch->payment_deadline?->format('Y-m-d\\TH:i') }}" required></div><div class="col-md-4"><label class="form-label">Replacement payment deadline</label><input name="replacement_payment_deadline" type="datetime-local" class="form-control" value="{{ $batch->replacement_payment_deadline?->format('Y-m-d\\TH:i') }}" required></div></div><button class="btn btn-outline-primary mt-3">Save invitation details</button></form>@if($batch->response_deadline && $batch->payment_deadline && $batch->replacement_payment_deadline)<form method="POST" action="{{ route('backend.masters.send-invitations', $batch) }}" class="mt-3" onsubmit="return confirm('Send invitations to all selected invitees now?');">@csrf<button class="btn btn-primary">Send invitations to all invitees</button></form>@endif</div></div>
  @else
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2"><span>Invitations have been sent. The checked players below are now active invitees in their Masters categories.</span><form method="POST" action="{{ route('backend.masters.public-list.toggle', $batch) }}">@csrf<input type="hidden" name="published" value="{{ $batch->public_list_published ? 0 : 1 }}"><button class="btn btn-sm {{ $batch->public_list_published ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $batch->public_list_published ? 'Unpublish player list' : 'Publish player list' }}</button></form></div>
  @endif
  <div class="masters-groups">
  @foreach($readiness['groups'] as $group)
    <div class="card"><div class="card-body">
      <h5>{{ $group['label'] ?? 'Age group' }}</h5>
      <p>{{ $group['candidate_count'] }} candidates · {{ $group['reserve_count'] }} reserves · {{ ucfirst($group['status']) }}</p>
      @foreach($group['blocking'] as $message)<div class="text-danger">{{ $message }}</div>@endforeach
      @foreach($group['warnings'] as $message)<div class="text-warning">{{ $message }}</div>@endforeach
      @php($groupPlayers = $batch->invitations->where('category_event_id', $group['category_event_id'])->sortBy('queue_position'))
      @php($invitees = $groupPlayers->filter(fn ($invitation) => in_array($invitation->status, [\App\Models\MastersInvitation::INVITED, \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\MastersInvitation::PAID_CONFIRMED], true)))
      @php($reserves = $groupPlayers->filter(fn ($invitation) => $invitation->status === \App\Models\MastersInvitation::RESERVE))
      @php($declined = $groupPlayers->filter(fn ($invitation) => in_array($invitation->status, [\App\Models\MastersInvitation::DECLINED, \App\Models\MastersInvitation::WITHDRAWN, \App\Models\MastersInvitation::ADMIN_REMOVED], true)))
      <div class="d-flex justify-content-between align-items-center mt-3 mb-1"><div class="small fw-semibold">Invitees — visible to be sent</div><span class="small text-muted">{{ $invitees->count() }} entries</span></div>
      <div class="masters-entry-table">
        <div class="masters-entry-head"><span>#</span><span>Player</span><span>Email</span><span>Cell</span><span>Status / payment</span><span>Action</span></div>
          @forelse($invitees as $playerInvitation)
            @php($willInvite = $playerInvitation->status === \App\Models\MastersInvitation::INVITED)
            @php($playerStatus = match ($playerInvitation->status) { \App\Models\MastersInvitation::PAID_CONFIRMED => 'Registered', \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT => 'Payment pending', default => $batch->status === 'sent' ? 'Email queued' : 'Email not yet sent' })
            <div class="masters-entry-row masters-player-row">
              <span class="text-muted">{{ $loop->iteration }}</span><div><strong>{{ $playerInvitation->player?->full_name ?? ('Player '.$playerInvitation->player_id) }}</strong><small class="d-block text-muted">Rank {{ $playerInvitation->ranking_position }}</small></div>
              @php($contactEmail = $playerInvitation->player?->email ?: $playerInvitation->player?->user?->email ?: $playerInvitation->player?->users?->first()?->email)
              <div class="contact-cell email-cell">@if($contactEmail)<a class="email-link" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@else<span class="text-muted">—</span>@endif</div><div class="contact-cell cell-cell">@if($playerInvitation->player?->cellNr)<a href="tel:{{ $playerInvitation->player->cellNr }}">{{ $playerInvitation->player->cellNr }}</a>@else<span class="text-muted">—</span>@endif</div><div class="payment-cell"><span class="badge {{ $playerInvitation->status === \App\Models\MastersInvitation::PAID_CONFIRMED ? 'bg-label-success' : ($playerInvitation->status === \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT ? 'bg-label-warning' : 'bg-label-primary') }}">{{ $playerStatus }}</span></div><div class="action-cell"><form class="js-invitation-wave-form" method="POST" action="{{ route('backend.masters.invitation.update', $playerInvitation) }}" data-player-row data-invited="{{ $willInvite ? 1 : 0 }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $willInvite ? 'reserve' : 'invited' }}"><button class="btn btn-sm {{ $willInvite ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit" title="{{ $willInvite ? 'Move to reserve' : 'Add to invitation wave' }}">{{ $willInvite ? '✓' : '○' }}</button></form></div>
            </div>
          @empty<div class="small text-muted p-3">No players currently selected for invitation.</div>@endforelse
      </div>
      @if($reserves->isNotEmpty())<details class="mt-2"><summary class="small fw-semibold">Show {{ $reserves->count() }} reserve players</summary><div class="masters-player-list mt-2">
          @foreach($reserves as $playerInvitation)
            @php($willInvite = false)
            @php($contactEmail = $playerInvitation->player?->email ?: $playerInvitation->player?->user?->email ?: $playerInvitation->player?->users?->first()?->email)
            <div class="masters-player-row d-flex align-items-start gap-2"><form class="js-invitation-wave-form" method="POST" action="{{ route('backend.masters.invitation.update', $playerInvitation) }}" data-player-row data-invited="0">@csrf @method('PATCH')<input type="hidden" name="status" value="invited"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Add to invitation wave">○</button></form><div class="flex-grow-1"><strong>{{ $playerInvitation->player?->full_name ?? ('Player '.$playerInvitation->player_id) }}</strong><div class="small text-muted">Rank {{ $playerInvitation->ranking_position }} · Reserve — invite if needed</div><div class="small mt-1 d-flex flex-wrap gap-2"><span class="text-muted">Contact:</span>@if($contactEmail)<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@else<span class="text-muted">No email</span>@endif @if($playerInvitation->player?->cellNr)<a href="tel:{{ $playerInvitation->player->cellNr }}">{{ $playerInvitation->player->cellNr }}</a>@else<span class="text-muted">No telephone</span>@endif</div></div><span class="badge bg-label-secondary">Reserve</span></div>
          @endforeach
      </div></details>@endif
      @if($declined->isNotEmpty())<div class="mt-3"><div class="small fw-semibold text-danger mb-1">Declined / unavailable</div><div class="small text-muted mb-1">The next reserve can be invited when automatic replacement is enabled.</div><div class="masters-player-list">
          @foreach($declined as $playerInvitation)
            @php($contactEmail = $playerInvitation->player?->email ?: $playerInvitation->player?->user?->email ?: $playerInvitation->player?->users?->first()?->email)
            <div class="masters-player-row d-flex align-items-start gap-2 bg-light"><div class="flex-grow-1"><strong>{{ $playerInvitation->player?->full_name ?? ('Player '.$playerInvitation->player_id) }}</strong><div class="small text-muted">Rank {{ $playerInvitation->ranking_position }} · {{ $playerInvitation->status === \App\Models\MastersInvitation::WITHDRAWN ? 'Withdrawn after registration' : 'Declined / unavailable' }}</div><div class="small mt-1 d-flex flex-wrap gap-2"><span class="text-muted">Contact:</span>@if($contactEmail)<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@else<span class="text-muted">No email</span>@endif @if($playerInvitation->player?->cellNr)<a href="tel:{{ $playerInvitation->player->cellNr }}">{{ $playerInvitation->player->cellNr }}</a>@else<span class="text-muted">No telephone</span>@endif</div></div><span class="badge bg-label-danger">{{ $playerInvitation->status === \App\Models\MastersInvitation::WITHDRAWN ? 'Withdrawn' : 'Declined' }}</span></div>
          @endforeach
        </div></div>@endif
    </div></div>
  @endforeach
  </div>
  <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Ranking lists in this batch</h5><span class="text-muted small">Remove only before payment starts</span></div><div class="card-body p-0">
    @forelse($batch->invitations->groupBy('ranking_list_id') as $rankingListId => $invitations)
      @php($category = $invitations->first()->categoryEvent?->category?->name ?? 'Ranking list '.$rankingListId)
      <div class="d-flex justify-content-between align-items-center border-bottom p-3"><div><strong>{{ $category }}</strong><div class="small text-muted">{{ $invitations->count() }} invitation records</div></div><form method="POST" action="{{ route('backend.masters.remove-ranking-list', [$batch, $rankingListId]) }}" onsubmit="return confirm('Remove this ranking list and its invitations from the batch so it can be generated again? This cannot be undone.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove &amp; restart</button></form></div>
    @empty
      <div class="p-3 text-muted">No ranking lists remain in this batch.</div>
    @endforelse
  </div></div>
  <div class="d-flex justify-content-center gap-2 py-3"><a href="{{ route('admin.events.overview', $batch->event_id) }}" class="btn btn-primary">Back to Masters Dashboard</a>@if($batch->series_id)<a href="{{ route('series.events', $batch->series_id) }}" class="btn btn-outline-secondary">Back to Series</a>@endif</div>
  <form method="POST" action="{{ route('backend.masters.toggle-auto', $batch) }}">@csrf
    <input type="hidden" name="enabled" value="{{ $batch->auto_replacement_enabled ? 0 : 1 }}">
    <button class="btn {{ $batch->auto_replacement_enabled ? 'btn-outline-danger' : 'btn-success' }}">
      {{ $batch->auto_replacement_enabled ? 'Disable auto-replacement' : 'Enable auto-replacement' }}
    </button>
  </form>
</div>
@endsection

@section('page-script')
<div class="modal fade" id="invitationPreviewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Invitation email preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="invitationPreviewBody"><div class="text-center text-muted py-4">Loading preview…</div></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const deadlineHints = {
    response_deadline: 'The last date and time for the player to accept or decline the invitation.',
    payment_deadline: 'The last date and time for an accepted player to complete payment and secure their place.',
    replacement_payment_deadline: 'The last date and time a replacement player may complete payment after being invited.'
  };
  Object.entries(deadlineHints).forEach(function ([name, hint]) {
    const input = document.querySelector('[name="' + name + '"]');
    if (!input) return;
    const wrapper = input.closest('.col-md-4');
    const label = wrapper?.querySelector('label');
    if (label) { label.setAttribute('title', hint); label.setAttribute('data-bs-toggle', 'tooltip'); label.insertAdjacentHTML('beforeend', ' <span class="text-muted" aria-hidden="true">ⓘ</span>'); }
    input.insertAdjacentHTML('afterend', '<div class="form-text">' + hint + '</div>');
  });
  if (window.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
  const previewModal = document.getElementById('invitationPreviewModal');
  document.querySelectorAll('.masters-entry-row .action-cell').forEach(function (cell) {
    const form = cell.querySelector('.js-invitation-wave-form');
    if (!form) return;
    const remove = document.createElement('button');
    remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger ms-1'; remove.textContent = '×'; remove.title = 'Remove by admin';
    remove.addEventListener('click', async function () {
      if (!confirm('Remove this player from the Masters invitation list?')) return;
      remove.disabled = true;
      try {
        const response = await fetch(form.action, {method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
        if (!response.ok) { const data = await response.json(); throw new Error(data.message || 'Could not remove the player.'); }
        window.location.reload();
      } catch (error) { alert(error.message); remove.disabled = false; }
    });
    cell.appendChild(remove);
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'btn btn-sm btn-outline-info me-1'; button.textContent = 'Preview';
    button.addEventListener('click', async function () {
      const body = document.getElementById('invitationPreviewBody');
      body.innerHTML = '<div class="text-center text-muted py-4">Loading preview…</div>';
      bootstrap.Modal.getOrCreateInstance(previewModal).show();
      try { const response = await fetch(form.action + '/preview', {headers: {'Accept': 'text/html'}}); if (!response.ok) throw new Error('Could not load the preview.'); body.innerHTML = await response.text(); }
      catch (error) { body.innerHTML = '<div class="alert alert-danger mb-0">' + error.message + '</div>'; }
    });
    cell.insertBefore(button, form);
  });
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  document.querySelectorAll('.js-invitation-wave-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('button');
      const statusInput = form.querySelector('[name="status"]');
      const row = form.closest('.masters-player-row');
      const targetStatus = statusInput.value;
      button.disabled = true;
      try {
        const response = await fetch(form.action, {method: 'PATCH', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({status: targetStatus})});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not update the invitation wave.');
        const invited = data.status === 'invited';
        form.dataset.invited = invited ? '1' : '0';
        statusInput.value = invited ? 'reserve' : 'invited';
        button.textContent = invited ? '✓' : '○';
        button.title = invited ? 'Move to reserve' : 'Add to invitation wave';
        button.classList.toggle('btn-primary', invited);
        button.classList.toggle('btn-outline-secondary', !invited);
        const description = row.querySelector('.small.text-muted');
        if (description && description.classList.contains('player-status')) {
          description.textContent = invited ? 'Email not yet sent' : 'Reserve — invite if needed';
        }
        const badge = row.querySelector('.badge');
        badge.textContent = invited ? 'Invited' : 'Reserve';
        badge.classList.toggle('bg-label-primary', invited);
        badge.classList.toggle('bg-label-secondary', !invited);
      } catch (error) { alert(error.message); }
      finally { button.disabled = false; }
    });
  });
});
</script>
@endsection
