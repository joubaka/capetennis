@php
  $teamsWorkspaceManager = $teamsWorkspaceRoster || ($isEventManager ?? false);
  $teamsWorkspaceCanInvite = auth()->user() && app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event);
  $teamsWorkspaceItems = [
    'players' => [($rosterOnly ?? false) ? 'Players' : 'Players & invitations', 'ti-users', 'tab-players'],
    'order' => ['Player order', 'ti-list-numbers', 'tab-order'],
    'setup' => ['Setup', 'ti-settings', 'tab-regions'],
    'results' => ['Result ranks', 'ti-award', 'tab-result-rank'],
  ];
@endphp
<style>
  .teams-workspace-nav { display:flex; flex-wrap:wrap; gap:.4rem; padding:.5rem; margin-bottom:1rem; border:1px solid #dbe6f4; border-radius:.75rem; background:#f7faff; }
  .teams-workspace-nav .teams-workspace-link { display:flex; align-items:center; justify-content:center; gap:.4rem; min-height:44px; padding:.65rem 1rem; border-radius:.5rem; color:#506176; font-weight:600; text-decoration:none; }
  .teams-workspace-nav .teams-workspace-link.active { color:#173f78; background:#fff; box-shadow:0 .15rem .5rem rgba(31,57,104,.12); }
  .teams-workspace-nav .teams-workspace-link:focus-visible { outline:3px solid #1f5c9d; outline-offset:2px; }
  @media(max-width:575.98px) { .teams-workspace-nav .teams-workspace-link { flex:1 1 calc(50% - .4rem); min-width:0; padding:.65rem .4rem; font-size:.875rem; } }
</style>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2"><h2 class="h5 mb-0">Teams</h2><span class="text-muted small">{{ ($rosterOnly ?? false) ? 'Players and player order' : ($teamsWorkspaceManager ? 'Players, invitations and team setup' : 'Region-scoped workspace') }}</span></div>
@if($teamsWorkspaceManager)
<nav class="teams-workspace-nav nav tabs-wrap" aria-label="Teams workspace" data-teams-workspace>
  @foreach($teamsWorkspaceItems as $key => [$label, $icon, $panel])
    @continue(($rosterOnly ?? false) && !in_array($key, ['players', 'order']))
    @if(($key !== 'invitations' || $teamsWorkspaceCanInvite) && (!in_array($key, ['setup', 'results']) || auth()->user()?->can('event.manage', $event)))
      @if($key === 'setup')<details class="teams-workspace-more"><summary class="teams-workspace-link">Setup &amp; history</summary><div class="d-flex flex-wrap">@endif
      <a class="teams-workspace-link {{ $teamsWorkspaceRoster && $panel ? 'nav-link' : '' }} {{ ($teamsWorkspaceRoster ? $key === 'players' : $key === 'invitations') ? 'active' : '' }}"
         href="{{ route('admin.events.teams', [$event, 'roster_tab' => match($key) { 'setup' => 'regions', 'results' => 'result-rank', default => $key }]).'#'.$panel }}"
         @if($teamsWorkspaceRoster && $panel) data-bs-toggle="tab" data-bs-target="#{{ $panel }}" data-teams-panel="{{ $panel }}" id="{{ match($key) { 'players' => 'players-tab', 'invitations' => 'invitations-tab', 'order' => 'order-tab', 'results' => 'result-rank-button', default => 'regions-tab' } }}" role="tab" aria-controls="{{ $panel }}" aria-selected="{{ $key === 'players' ? 'true' : 'false' }}"
@endif

         @if($teamsWorkspaceRoster ? $key === 'players' : $key === 'invitations') aria-current="page"
@endif
>
        <i class="ti {{ $icon }}" aria-hidden="true"></i>{{ $label }}
        @if($key === 'players' && isset($playerCount))<span class="badge bg-label-success">{{ $playerCount }}</span>
@endif

      </a>
      @if($key === 'results')</div></details>@endif

@endif

  @endforeach
</nav>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const nav = document.querySelector('[data-teams-workspace]');
  if (!nav) return;
  const regionMap = @json(isset($eventRegions) ? $eventRegions->pluck('region_id', 'id') : []);
  document.addEventListener('click', function (event) {
    const link = event.target.closest('[data-teams-workspace] a');
    if (!link) return;
    const region = document.querySelector('[data-roster-region]')?.value
      || regionMap[document.querySelector('[data-region-select]')?.value];
    const url = new URL(link.href);
    if (region) url.searchParams.set('selected_region', region);
    new URLSearchParams(window.location.search).forEach((value, key) => {
      if (key.startsWith('roster_') && key !== 'roster_tab') url.searchParams.set(key, value);
    });
    link.href = url.toString();
  });
});
</script>
@if($teamsWorkspaceRoster)
<script>
document.addEventListener('DOMContentLoaded', function () {
  const nav = document.querySelector('[data-teams-workspace]');
  const activateRequestedPanel = function () {
    const requestedPanel = window.location.hash.slice(1);
    const link = Array.from(nav.querySelectorAll('[data-teams-panel]')).find(item => item.dataset.teamsPanel === requestedPanel);
    if (link && window.bootstrap?.Tab) bootstrap.Tab.getOrCreateInstance(link).show();
  };
  nav.querySelectorAll('[data-teams-panel]').forEach(function (link) {
    link.addEventListener('shown.bs.tab', function () {
      nav.querySelectorAll('[aria-current]').forEach(item => item.removeAttribute('aria-current'));
      link.setAttribute('aria-current', 'page');
      const url = new URL(link.href);
      const current = new URL(window.location.href);
      ['selected_team', 'selection_task'].forEach(key => { if (current.searchParams.has(key)) url.searchParams.set(key, current.searchParams.get(key)); });
      const region = document.querySelector('[data-roster-region]')?.value;
      const team = document.querySelector('[data-workspace-team]')?.value;
      if (region) url.searchParams.set('selected_region', region);
      if (team) url.searchParams.set('selected_team', team); else url.searchParams.delete('selected_team');
      history.replaceState(null, '', url);
    });
  });
  activateRequestedPanel();
  window.addEventListener('hashchange', activateRequestedPanel);
});
</script>

@endif


@endif
