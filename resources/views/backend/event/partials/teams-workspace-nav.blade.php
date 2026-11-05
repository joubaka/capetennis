@php
  $teamsWorkspaceManager = $teamsWorkspaceRoster || ($isEventManager ?? false);
  $teamsWorkspaceCanInvite = auth()->user() && app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event);
  $teamsWorkspaceItems = [
    'players' => [($rosterOnly ?? false) ? 'Players' : 'Players & invitations', 'ti-users', 'tab-players'],
    'order' => ['Player order', 'ti-list-numbers', 'tab-order'],
    'communications' => ['Communications', 'ti-mail', 'tab-communications'],
    'campaigns' => ['Campaigns & settings', 'ti-speakerphone', 'tab-campaigns'],
    'setup' => ['Setup', 'ti-settings', 'tab-regions'],
    'results' => ['Result ranks', 'ti-award', 'tab-result-rank'],
  ];
@endphp
<style>
  .teams-workspace-nav { display:flex; flex-wrap:wrap; gap:.2rem; margin-bottom:.5rem; border-bottom:1px solid #dbe6f4; background:#fff; }
  .teams-workspace-nav .teams-workspace-link { display:flex; align-items:center; gap:.35rem; min-height:38px; padding:.35rem .65rem; color:#506176; font-size:.875rem; font-weight:600; text-decoration:none; border-bottom:2px solid transparent; }
  .teams-workspace-nav .teams-workspace-link.active { color:#173f78; border-bottom-color:#173f78; background:#f4f7fb; }
  .teams-workspace-nav .teams-workspace-link:focus-visible { outline:3px solid #1f5c9d; outline-offset:-3px; }
  @media(max-width:767.98px) {
    .teams-workspace-nav { gap:.15rem .25rem; }
    .teams-workspace-nav .teams-workspace-link { min-height:44px; padding-inline:.45rem; font-size:.82rem; }
    .teams-workspace-nav .teams-workspace-link i { display:none; }
  }
</style>
<h2 class="h5 mb-1">Teams</h2>
@if($teamsWorkspaceManager)
<nav class="teams-workspace-nav nav tabs-wrap" aria-label="Teams workspace" data-teams-workspace @if($teamsWorkspaceRoster)role="tablist"@endif>
  @foreach($teamsWorkspaceItems as $key => [$label, $icon, $panel])
    @continue(($rosterOnly ?? false) && !in_array($key, ['players', 'order']))
    @continue(in_array($key, ['communications', 'campaigns']) && !app(\App\Services\TeamSelection\RegionManagerAccessService::class)->canAccessWorkspace(auth()->user(), $event))
    @if(($key !== 'invitations' || $teamsWorkspaceCanInvite) && ($key !== 'results' || auth()->user()?->can('event.manage', $event)) && ($key !== 'setup' || app(\App\Services\TeamSelection\RegionManagerAccessService::class)->canAccessWorkspace(auth()->user(), $event)))
      <a class="teams-workspace-link {{ $teamsWorkspaceRoster && $panel ? 'nav-link' : '' }} {{ ($teamsWorkspaceRoster ? $key === 'players' : $key === 'invitations') ? 'active' : '' }}"
         href="{{ route('admin.events.teams', [$event, 'roster_tab' => match($key) { 'setup' => 'regions', 'results' => 'result-rank', default => $key }]).'#'.$panel }}"
         @if($teamsWorkspaceRoster && $panel) data-bs-toggle="tab" data-bs-target="#{{ $panel }}" data-teams-panel="{{ $panel }}" id="{{ match($key) { 'setup' => 'regions-tab', 'results' => 'result-rank-button', default => $key.'-tab' } }}" role="tab" aria-controls="{{ $panel }}" aria-selected="{{ $key === 'players' ? 'true' : 'false' }}"
@endif

         @if($teamsWorkspaceRoster ? $key === 'players' : $key === 'invitations') aria-current="page"
@endif
>
        <i class="ti {{ $icon }}" aria-hidden="true"></i>{{ $label }}
        @if($key === 'players' && isset($playerCount))<span class="badge bg-label-success">{{ $playerCount }}</span>
@endif

      </a>

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
