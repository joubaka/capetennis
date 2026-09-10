@php
  $teamWorkspaceActive = request('view') === 'order' ? 'order' : 'players';
  $teamWorkspaceRegional = true;
@endphp

@if($teamWorkspaceActive === 'players')
  <div class="regional-roster-toolbar" data-regional-roster-toolbar>
    <div>
      <label class="form-label mb-1" for="regional-roster-search">Find a team or player</label>
      <div class="input-group">
        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
        <input id="regional-roster-search" type="search" class="form-control"
               placeholder="Search team, player, email or cell…" autocomplete="off"
               data-regional-roster-search>
      </div>
    </div>
    <div>
      <label class="form-label mb-1" for="regional-payment-filter">Payment status</label>
      <select id="regional-payment-filter" class="form-select" data-regional-payment-filter>
        <option value="all">All active players</option>
        <option value="unpaid">Unpaid only</option>
        <option value="paid">Paid only</option>
      </select>
    </div>
    <div class="regional-roster-toolbar__summary" aria-live="polite">
      <strong data-regional-filter-count>{{ $workspaceRegions->sum(fn ($region) => $region->teams->sum(fn ($team) => $team->teamPlayers->count())) }} active players</strong>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-regional-filter-reset>Clear filters</button>
    </div>
  </div>
  <div class="alert alert-light text-center d-none" data-regional-filter-empty>No teams or players match these filters.</div>
  @include('backend.adminPage.admin_show.tabs.players', ['regionsInEvent' => $workspaceRegions])
@else
  @include('backend.adminPage.admin_show.tabs.player-order', ['regionsInEvent' => $workspaceRegions])
@endif
