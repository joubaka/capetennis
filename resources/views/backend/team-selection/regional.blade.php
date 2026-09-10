@extends('layouts.backend')

@section('title', 'Regional Team Administration')

@section('page-style')
<link rel="stylesheet" href="{{ asset('css/team-admin-workspace.css') }}?v={{ filemtime(public_path('css/team-admin-workspace.css')) }}">
@endsection

@section('content')
@include('backend.event.partials.header', [
  'event' => $event,
  'eventWorkspaceActive' => 'entries',
  'eventWorkspaceRegionalOnly' => true,
  'eventWorkspaceShowHome' => false,
])

@php
  $teamWorkspaceActive = request('view') === 'order' ? 'order' : 'players';
  $regionCount = $workspaceRegions->count();
  $teamCount = $workspaceRegions->sum(fn ($region) => $region->teams->count());
  $categoryCount = 0;
  $playerCount = $workspaceRegions->sum(
    fn ($region) => $region->teams->sum(fn ($team) => $team->teamPlayers->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count())
  );
  $reserveCount = $teamSelectionInvitations->flatten(1)
    ->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count();
  $teamWorkspaceRegional = true;
@endphp

<div class="container-xxl flex-grow-1 container-p-y">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="team-admin-workspace" data-backend-wide data-regional-team-workspace>
    <div class="nav-tabs-shadow mb-4">
      @include('backend.event.partials.team-workspace-nav', [
        'teamWorkspaceMode' => 'regional',
        'teamWorkspaceShowAdminTabs' => false,
      ])

      <div class="tab-content p-3" data-regional-workspace-content aria-live="polite">
        @include('backend.team-selection.partials.regional-content')
      </div>
    </div>
  </div>

  @foreach($eventRegions as $eventRegion)
    <div class="modal fade" id="roster-email-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('backend.team-selection.roster-email.send', [$event, $eventRegion]) }}" class="modal-content" data-regional-email-form>
          @csrf
          <input type="hidden" name="target_type" value="team" data-roster-email-target>
          <input type="hidden" name="team_id" data-roster-email-team>
          <input type="hidden" name="invitation_id" data-roster-email-invitation>
          <div class="modal-header"><div><h5 class="modal-title">Email selected roster</h5><div class="small text-muted" data-roster-email-recipient></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="180" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="20000" required></textarea></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-roster-email-{{ $eventRegion->id }}" required><label class="form-check-label" for="confirm-roster-email-{{ $eventRegion->id }}">I confirm the recipient details above are correct</label></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="ti ti-send me-1"></i>Queue email</button></div>
        </form>
      </div>
    </div>
  @endforeach
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const content = document.querySelector('[data-regional-workspace-content]');
  const tabs = Array.from(document.querySelectorAll('[data-regional-workspace-tab]'));
  let workspaceRequest = null;

  const showWorkspaceNotice = function (message, type) {
    if (!content) return;
    content.querySelectorAll('[data-regional-workspace-notice]').forEach(function (notice) { notice.remove(); });
    const notice = document.createElement('div');
    notice.className = `alert alert-${type}`;
    notice.dataset.regionalWorkspaceNotice = '';
    notice.setAttribute('role', type === 'success' ? 'status' : 'alert');
    notice.textContent = message;
    content.prepend(notice);
  };

  const applyRosterFilters = function () {
    if (!content) return;
    const search = content.querySelector('[data-regional-roster-search]');
    const payment = content.querySelector('[data-regional-payment-filter]');
    if (!search || !payment) return;
    const query = search.value.trim().toLocaleLowerCase();
    const status = payment.value;
    let visibleTeams = 0;
    let visiblePlayers = 0;

    content.querySelectorAll('[data-regional-team]').forEach(function (team) {
      const teamMatches = !query || (team.dataset.teamName || '').includes(query);
      let teamPlayerMatches = 0;
      team.querySelectorAll('[data-regional-player-row]').forEach(function (row) {
        const statusMatches = status === 'all' || row.dataset.payStatus === status;
        const textMatches = teamMatches || !query || row.textContent.toLocaleLowerCase().includes(query);
        const visible = statusMatches && textMatches;
        row.classList.toggle('d-none', !visible);
        if (visible) teamPlayerMatches++;
      });
      const reserveMatches = query && team.textContent.toLocaleLowerCase().includes(query);
      const visible = teamPlayerMatches > 0 || (status === 'all' && reserveMatches);
      team.classList.toggle('d-none', !visible);
      if (visible) visibleTeams++;
      visiblePlayers += teamPlayerMatches;
    });

    const count = content.querySelector('[data-regional-filter-count]');
    if (count) count.textContent = `${visiblePlayers} active player${visiblePlayers === 1 ? '' : 's'} in ${visibleTeams} team${visibleTeams === 1 ? '' : 's'}`;
    content.querySelector('[data-regional-filter-empty]')?.classList.toggle('d-none', visibleTeams > 0);
  };

  const setActiveTab = function (url) {
    const view = new URL(url, window.location.origin).searchParams.get('view') === 'order' ? 'order' : 'players';
    tabs.forEach(function (tab) {
      const active = tab.dataset.regionalWorkspaceTab === view;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      if (active) tab.setAttribute('aria-current', 'page');
      else tab.removeAttribute('aria-current');
    });
  };

  const loadWorkspace = async function (url, pushState) {
    if (!content) return;
    if (workspaceRequest) workspaceRequest.abort();
    const controller = new AbortController();
    workspaceRequest = controller;
    content.setAttribute('aria-busy', 'true');
    tabs.forEach(function (tab) { tab.setAttribute('aria-disabled', 'true'); });
    try {
      const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
        signal: controller.signal
      });
      if (!response.ok) throw new Error('Unable to load the regional workspace.');
      content.innerHTML = await response.text();
      setActiveTab(url);
      if (pushState) window.history.pushState({ regionalWorkspace: true }, '', url);
    } catch (error) {
      if (error.name === 'AbortError') return;
      window.location.assign(url);
    } finally {
      if (workspaceRequest === controller) {
        workspaceRequest = null;
        content.removeAttribute('aria-busy');
        tabs.forEach(function (tab) { tab.removeAttribute('aria-disabled'); });
      }
    }
  };

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function (event) {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      loadWorkspace(tab.href, true);
    });
  });

  window.addEventListener('popstate', function () {
    loadWorkspace(window.location.href, false);
  });

  content?.addEventListener('submit', async function (event) {
    const form = event.target.closest('[data-regional-order-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) button.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Unable to update the player order.');
      await loadWorkspace(result.content_url || window.location.href, false);
      showWorkspaceNotice(result.message, 'success');
    } catch (error) {
      showWorkspaceNotice(error.message, 'danger');
      if (button) button.disabled = false;
    }
  });

  content?.addEventListener('input', function (event) {
    if (event.target.matches('[data-regional-roster-search]')) applyRosterFilters();
  });

  content?.addEventListener('change', function (event) {
    if (event.target.matches('[data-regional-payment-filter]')) applyRosterFilters();
  });

  content?.addEventListener('click', function (event) {
    const reset = event.target.closest('[data-regional-filter-reset]');
    if (!reset) return;
    const search = content.querySelector('[data-regional-roster-search]');
    const payment = content.querySelector('[data-regional-payment-filter]');
    if (search) search.value = '';
    if (payment) payment.value = 'all';
    applyRosterFilters();
    search?.focus();
  });

  document.addEventListener('submit', async function (event) {
    const form = event.target.closest('[data-regional-email-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) button.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const result = await response.json();
      if (!response.ok) {
        const validationMessage = result.errors ? Object.values(result.errors).flat().join(' ') : null;
        throw new Error(validationMessage || result.message || 'Unable to queue the roster email.');
      }
      const modal = form.closest('.modal');
      if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).hide();
      form.reset();
      showWorkspaceNotice(result.message, 'success');
    } catch (error) {
      const existing = form.querySelector('[data-regional-email-error]');
      if (existing) existing.remove();
      const notice = document.createElement('div');
      notice.className = 'alert alert-danger';
      notice.setAttribute('role', 'alert');
      notice.dataset.regionalEmailError = '';
      notice.textContent = error.message;
      form.querySelector('.modal-body')?.prepend(notice);
    } finally {
      if (button) button.disabled = false;
    }
  });

  document.querySelectorAll('[id^="roster-email-"]').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      modal.querySelector('[data-regional-email-error]')?.remove();
      const button = event.relatedTarget;
      if (!button) return;
      modal.querySelector('[data-roster-email-target]').value = button.dataset.targetType || 'team';
      modal.querySelector('[data-roster-email-team]').value = button.dataset.teamId || '';
      modal.querySelector('[data-roster-email-invitation]').value = button.dataset.invitationId || '';
      modal.querySelector('[data-roster-email-recipient]').textContent = button.dataset.recipient || '';
    });
  });
});
</script>
@endsection
