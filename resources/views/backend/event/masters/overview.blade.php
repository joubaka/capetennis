@extends('layouts.backend')

@section('title', $event->name)

@section('page-style')
<style>
  .masters-page { padding-top: 3.5rem; }
  .masters-page .card { border:1px solid #ebeaf0; border-radius:.45rem; box-shadow:0 .25rem 1rem rgba(47,43,61,.05); }
  .masters-page .card-header { background:#fff; border-bottom:1px solid #ebeaf0; padding:1.1rem 1.2rem; }
  .masters-page .card-header h5 { color:#625f6d; font-size:1rem; font-weight:600; }
  .masters-page .card-body { padding:1.1rem 1.2rem; }
  .masters-page .status { border-radius:999px; padding:.35rem .7rem; font-size:.75rem; font-weight:600; }
  .masters-page .status.ready { background:#e8f8ef; color:#198754; }
  .masters-page .status.warning { background:#fff4dd; color:#9a6700; }
  .masters-page .status.blocked { background:#fde8e7; color:#b42318; }
  .masters-page .metric { border:1px solid #ebeaf0; border-radius:.5rem; padding:.8rem; height:100%; }
  .masters-page .metric strong { display:block; font-size:1.4rem; margin-top:.2rem; }
  .masters-page .category-row { border-bottom:1px solid #ebeaf0; padding:.7rem 0; }
  .masters-page .category-row:last-child { border-bottom:0; }
  .masters-page .stepper { display:inline-flex; align-items:center; border:1px solid #d9d7e2; border-radius:.4rem; overflow:hidden; }
  .masters-page .stepper button { border:0; background:#f7f6fa; width:2rem; height:2rem; color:#5f596d; }
  .masters-page .stepper output { min-width:2.2rem; text-align:center; font-weight:600; }
  .masters-page .category-state { min-width:4.6rem; text-align:center; }
  .masters-page .dashboard-action { display:flex; align-items:center; justify-content:flex-start; gap:.4rem; font-weight:500; }
  .masters-page .panel-icon { font-size:1.15rem; }
  .masters-page .management-panel .card-header .panel-icon { color:#7651e8; }
  .masters-page .setup-panel .dashboard-action { justify-content:flex-start; }
  .masters-page .stats-panel .card-body { padding-top:.9rem; }
  .masters-page .stats-panel li { color:#625f6d; font-size:.9rem; }
  .masters-page .stats-panel li + li { margin-top:.35rem; }
</style>
@endsection

@section('content')
<div class="container-xl masters-page">
  @include('backend.event.partials.header', ['event' => $event])

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Masters Dashboard</h4><p class="text-muted mb-0">Manage invitations, payments, reserves, replacements and event readiness.</p></div>
    <div class="d-flex gap-2"><a href="{{ route('backend.events.edit', $event) }}" class="btn btn-outline-secondary">Event settings</a>@if($event->series_id)<a href="{{ route('series.events', $event->series_id) }}" class="btn btn-outline-primary">Back to series</a>@endif</div>
  </div>

  @php($overall = $mastersReadiness['status'] ?? 'blocked')
  @php($canPublishInvitations = $mastersBatch && $mastersBatch->status !== 'sent' && $mastersBatch->response_deadline && $mastersBatch->payment_deadline && $mastersBatch->replacement_payment_deadline)
  <div class="alert {{ $overall === 'ready' ? 'alert-success' : ($overall === 'warning' ? 'alert-warning' : 'alert-danger') }} d-flex justify-content-between align-items-center">
    <span><strong>Masters readiness: {{ ucfirst($overall) }}</strong> — @if(!$mastersBatch)Generate an invitation batch before inviting players. @elseif($mastersBatch->status === 'sent')Live invitation management is active. @else Invitation batch is available for review before sending. @endif</span>
    @if($mastersBatch)<div class="d-flex gap-2"><a href="{{ route('backend.masters.show', $mastersBatch) }}" class="btn btn-sm btn-outline-dark">Manage invitations</a>@if($mastersBatch->status === 'sent')<form method="POST" action="{{ route('backend.masters.public-list.toggle', $mastersBatch) }}">@csrf<input type="hidden" name="published" value="{{ $mastersBatch->public_list_published ? 0 : 1 }}"><button class="btn btn-sm {{ $mastersBatch->public_list_published ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $mastersBatch->public_list_published ? 'Unpublish player list' : 'Publish player list' }}</button></form>@if(!$mastersBatch->auto_replacement_enabled)<form method="POST" action="{{ route('backend.masters.toggle-auto', $mastersBatch) }}">@csrf<input type="hidden" name="enabled" value="1"><button class="btn btn-sm btn-outline-success">Enable auto-replacement</button></form>@endif @elseif($mastersBatch->status !== 'sent')<form method="POST" action="{{ route('backend.masters.restart', $mastersBatch) }}" onsubmit="return confirm('Restart this invitation batch? All generated invitations will be removed so you can generate a new batch. This cannot be undone.');">@csrf<button class="btn btn-sm btn-outline-danger">Restart batch</button></form>@endif</div>@endif
  </div>

  @if($mastersBatch && $mastersBatch->status !== 'sent' && !$mastersBatch->public_list_published)
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <span>Publish the invited player names publicly before sending emails?</span>
      <form method="POST" action="{{ route('backend.masters.publish-names', $mastersBatch) }}" onsubmit="return confirm('Publish player names publicly without sending invitation emails?');">
        @csrf
        <button class="btn btn-sm btn-outline-info"><i class="ti ti-world me-1"></i>Publish invitation list</button>
      </form>
    </div>
  @elseif($mastersBatch && $mastersBatch->public_list_published)
    <div class="alert alert-success d-flex justify-content-between align-items-center">
      <span>Player names are currently published publicly. Invitation emails have not been sent.</span>
      <form method="POST" action="{{ route('backend.masters.public-list.toggle', $mastersBatch) }}" onsubmit="return confirm('Unpublish the player names from the public event page?');">
        @csrf
        <input type="hidden" name="published" value="0">
        <button class="btn btn-sm btn-outline-warning"><i class="ti ti-eye-off me-1"></i>Unpublish names</button>
      </form>
    </div>
  @endif

  @if($mastersBatch)
    <div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h6 class="mb-1">Public invitation list</h6><p class="text-muted mb-0">Show or hide the selected Masters player names on the public event page.</p></div><form method="POST" action="{{ route('backend.masters.public-list.toggle', $mastersBatch) }}" onsubmit="return confirm('{{ $mastersBatch->public_list_published ? 'Unpublish the Masters player list?' : 'Publish the Masters player list publicly?' }}');">@csrf<input type="hidden" name="published" value="{{ $mastersBatch->public_list_published ? 0 : 1 }}"><button class="btn btn-sm {{ $mastersBatch->public_list_published ? 'btn-outline-warning' : 'btn-outline-success' }}"><i class="ti ti-world me-1"></i>{{ $mastersBatch->public_list_published ? 'Unpublish invitation list' : 'Publish invitation list' }}</button></form></div></div>
  @endif

  @if($mastersBatch && $mastersBatch->status !== 'sent')
    <div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div class="flex-grow-1"><h6 class="mb-1">Automatic replacement <span class="text-info" title="Enable this now and it will remain enabled when invitations are sent. After a confirmed decline or paid withdrawal, the next reserve player will be invited automatically." data-bs-toggle="tooltip"><i class="ti ti-info-circle"></i></span></h6><p class="text-muted mb-1">Set this before sending. Once invitations are sent, confirmed declines and paid withdrawals can automatically invite the next reserve.</p><div class="small text-warning"><i class="ti ti-alert-triangle me-1"></i>Check the ranking order, reserve list, contact details, deadlines, and payment settings before sending.</div></div><form method="POST" action="{{ route('backend.masters.toggle-auto', $mastersBatch) }}" onsubmit="return confirm('{{ $mastersBatch->auto_replacement_enabled ? 'Disable automatic replacement?' : 'Enable automatic replacement now? It will be active automatically once invitations are sent.' }}');">@csrf<input type="hidden" name="enabled" value="{{ $mastersBatch->auto_replacement_enabled ? 0 : 1 }}"><button class="btn {{ $mastersBatch->auto_replacement_enabled ? 'btn-outline-danger' : 'btn-outline-success' }}"><i class="ti ti-arrows-shuffle me-1"></i>{{ $mastersBatch->auto_replacement_enabled ? 'Disable auto-replacement' : 'Enable auto-replacement' }}</button></form></div></div>
  @elseif($mastersBatch && $mastersBatch->status === 'sent')
    <div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div class="flex-grow-1"><h6 class="mb-1">Automatic replacement <span class="text-info" title="When enabled, a confirmed decline or paid withdrawal can automatically invite the next reserve player." data-bs-toggle="tooltip"><i class="ti ti-info-circle"></i></span></h6><p class="text-muted mb-1">When a player declines and confirms their unavailability, or a paid player withdraws, the next reserve is invited automatically.</p><div class="small text-warning"><i class="ti ti-alert-triangle me-1"></i>Only enable this when the ranking order, reserve list, player contact details, deadlines, and payment settings are correct. The system will send the replacement invitation without another approval step.</div></div><form method="POST" action="{{ route('backend.masters.toggle-auto', $mastersBatch) }}" onsubmit="return confirm('{{ $mastersBatch->auto_replacement_enabled ? 'Disable automatic replacement?' : 'Enable automatic replacement? This will automatically invite the next reserve player and send the invitation without another approval step.' }}');">@csrf<input type="hidden" name="enabled" value="{{ $mastersBatch->auto_replacement_enabled ? 0 : 1 }}"><button class="btn {{ $mastersBatch->auto_replacement_enabled ? 'btn-outline-danger' : 'btn-success' }}"><i class="ti ti-arrows-shuffle me-1"></i>{{ $mastersBatch->auto_replacement_enabled ? 'Disable automatic replacement' : 'Enable automatic replacement' }}</button></form></div></div>
  @endif

  @php($sendLabel = $canPublishInvitations ? ($mastersBatch->public_list_published ? 'Send Invitations' : 'Send & Publish Invitations') : 'Prepare invitations')
  <div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6"><div class="card h-100 management-panel"><div class="card-header d-flex align-items-center gap-2"><i class="ti ti-settings ti-md text-primary"></i><h5 class="mb-0">Masters Management</h5></div><div class="card-body d-grid gap-2"><a href="{{ $mastersBatch ? route('backend.masters.show', $mastersBatch) : '#masters-setup' }}" class="btn btn-primary dashboard-action"><i class="ti ti-users me-1"></i>Manage Invitations</a>@if($mastersBatch && $mastersBatch->status !== 'sent')<a href="{{ $canPublishInvitations ? route('backend.masters.review', $mastersBatch) : route('backend.masters.show', $mastersBatch) }}" class="btn btn-outline-success dashboard-action"><i class="ti ti-send me-1"></i>{{ $canPublishInvitations ? 'Publish invitations' : 'Prepare invitations' }}</a>@elseif($mastersBatch && $mastersBatch->status === 'sent')<form method="POST" action="{{ route('backend.masters.public-list.toggle', $mastersBatch) }}">@csrf<input type="hidden" name="published" value="{{ $mastersBatch->public_list_published ? 0 : 1 }}"><button class="btn {{ $mastersBatch->public_list_published ? 'btn-outline-warning' : 'btn-outline-success' }} w-100 dashboard-action"><i class="ti ti-world me-1"></i>{{ $mastersBatch->public_list_published ? 'Unpublish player list' : 'Publish player list' }}</button></form><form method="POST" action="{{ route('backend.masters.registration.toggle', $mastersBatch) }}" onsubmit="return confirm('{{ $mastersBatch->registration_open ? 'Close Masters registration now?' : 'Open Masters registration now?' }}');">@csrf<input type="hidden" name="open" value="{{ $mastersBatch->registration_open ? 0 : 1 }}"><button class="btn {{ $mastersBatch->registration_open ? 'btn-outline-danger' : 'btn-outline-success' }} dashboard-action"><i class="ti ti-lock{{ $mastersBatch->registration_open ? '-open' : '' }} me-1"></i>{{ $mastersBatch->registration_open ? 'Close Masters registration' : 'Open Masters registration' }}<span class="text-info ms-auto" title="Controls whether invited players may register and pay." data-bs-toggle="tooltip"><i class="ti ti-info-circle"></i></span></button></form>@endif<a href="{{ route('admin.events.transactions', $event) }}" class="btn btn-outline-secondary dashboard-action"><i class="ti ti-credit-card me-1"></i>Transactions</a><a href="{{ route('headOffice.show', $event) }}" class="btn btn-outline-primary dashboard-action"><i class="ti ti-calendar-meet me-1"></i>Fixtures HQ</a></div></div></div>
    <div class="col-xl-4 col-md-6"><div class="card h-100 border-start border-warning border-3 setup-panel"><div class="card-header d-flex align-items-center gap-2"><i class="ti ti-adjustments ti-md text-warning"></i><h5 class="mb-0">Event Setup</h5></div><div class="card-body d-grid gap-2"><a href="{{ route('admin.events.settings', $event) }}" class="btn btn-outline-warning dashboard-action"><i class="ti ti-sliders me-1"></i>Event Settings</a><a href="{{ route('backend.masters.setup', $event) }}" class="btn btn-outline-primary dashboard-action"><i class="ti ti-list-details me-1"></i>Masters setup</a><a href="{{ route('admin.events.announcements', $event) }}" class="btn btn-outline-info dashboard-action"><i class="ti ti-megaphone me-1"></i>Announcements</a>@if($event->series)<a href="{{ route('series.show', $event->series) }}" class="btn btn-outline-secondary dashboard-action">{{ $event->series->name }}</a><a href="{{ route('ranking.series.list', $event->series) }}" class="btn btn-outline-dark dashboard-action" target="_blank"><i class="ti ti-printer me-1"></i>Print Series Rankings</a>@endif</div></div></div>
    <div class="col-xl-4 col-md-12"><div class="card h-100 stats-panel"><div class="card-header d-flex align-items-center gap-2"><i class="ti ti-chart-bar ti-md text-info"></i><h5 class="mb-0">Quick Stats</h5></div><div class="card-body"><ul class="list-unstyled mb-0 d-grid gap-1"><li>Categories: <span class="fw-semibold float-end">{{ $rankingCategoryLinks->where('enabled', true)->count() }}</span></li><li>Invitees: <span class="fw-semibold float-end">{{ $mastersBatch?->invitations()->where('status','invited')->count() ?? 0 }}</span></li><li>Paid confirmations: <span class="fw-semibold float-end">{{ $mastersBatch?->invitations()->where('status','paid_confirmed')->count() ?? 0 }}</span></li><li>Payment pending: <span class="fw-semibold float-end">{{ $mastersBatch?->invitations()->where('status','accepted_pending_payment')->count() ?? 0 }}</span></li><li>Reserves: <span class="fw-semibold float-end">{{ $mastersBatch?->invitations()->where('status','reserve')->count() ?? 0 }}</span></li><li>Declined / withdrawn: <span class="fw-semibold float-end">{{ $mastersBatch?->invitations()->whereIn('status',['declined','withdrawn'])->count() ?? 0 }}</span></li><li>Player list: <span class="fw-semibold float-end">{{ $mastersBatch?->public_list_published ? 'Published' : 'Unpublished' }}</span></li><li>Registration: <span class="fw-semibold float-end">{{ $mastersBatch?->registration_open ? 'Open' : 'Closed' }}</span></li></ul></div></div></div>
  </div>

  <div class="card"><div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="mb-1">Masters setup</h5><p class="text-muted mb-0">Configure the Series rankings and invitation categories from the Event Setup link.</p></div><a href="{{ route('backend.masters.setup', $event) }}" class="btn btn-outline-primary">Open Masters setup</a></div></div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[action*="public-list"]').forEach(function (form) {
    if (form.closest('.alert-success')) form.style.display = 'none';
  });
  const autoForms = Array.from(document.querySelectorAll('form[action*="toggle-auto"]'));
  const management = document.querySelector('.management-panel .card-body');
  if (management && autoForms.length) {
    const source = autoForms.find(form => form.closest('.card')) || autoForms[0];
    autoForms.forEach(form => { if (form !== source) form.style.display = 'none'; });
    const sourceCard = source.closest('.card');
    if (sourceCard) sourceCard.style.display = 'none';
    source.className = 'm-0';
    source.querySelector('button').className = 'btn ' + ({{ $mastersBatch?->auto_replacement_enabled ? 'true' : 'false' }} ? 'btn-outline-danger' : 'btn-outline-success') + ' dashboard-action';
    source.querySelector('button').innerHTML = '<i class="ti ti-arrows-shuffle me-1"></i>' + ({{ $mastersBatch?->auto_replacement_enabled ? 'true' : 'false' }} ? 'Disable auto-replacement' : 'Enable auto-replacement') + ' <span class="text-info ms-auto" title="When enabled, a confirmed decline or paid withdrawal automatically invites the next reserve player and sends the replacement invitation." data-bs-toggle="tooltip"><i class="ti ti-info-circle"></i></span>';
    management.appendChild(source);
    if (window.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }
  const sendLabel = @json($sendLabel);
  document.querySelectorAll('.management-panel .dashboard-action').forEach(function (button) {
    if (button.textContent.trim() === 'Publish invitations' || button.textContent.trim() === 'Prepare invitations') {
      button.innerHTML = '<i class="ti ti-send me-1"></i>' + sendLabel;
    }
  });
  const manager = document.getElementById('masters-category-manager');
  if (!manager) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const save = async (row, payload) => {
    row.classList.add('opacity-50');
    const url = manager.dataset.updateUrl.replace('__LINK__', row.dataset.linkId);
    try {
      const response = await fetch(url, { method: 'PATCH', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Could not save category settings.');
      row.dataset.saved = '1';
    } catch (error) { alert(error.message); window.location.reload(); }
    finally { row.classList.remove('opacity-50'); }
  };
  manager.querySelectorAll('.category-row').forEach(row => {
    const output = row.querySelector('[data-top-x]');
    row.querySelectorAll('[data-step]').forEach(button => button.addEventListener('click', () => { const value = Math.max(1, Math.min(100, Number(output.textContent) + Number(button.dataset.step))); output.textContent = value; save(row, {top_x: value}); }));
    row.querySelector('.category-toggle').addEventListener('click', function () { const enabled = this.dataset.enabled !== '1'; this.dataset.enabled = enabled ? '1' : '0'; this.classList.toggle('btn-success', enabled); this.classList.toggle('btn-outline-secondary', !enabled); this.querySelector('.category-state').textContent = enabled ? 'Enabled' : 'Off'; save(row, {enabled}); });
  });
});
</script>
@endsection
