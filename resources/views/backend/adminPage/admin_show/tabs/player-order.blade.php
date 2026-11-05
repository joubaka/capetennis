@if((int) $event->eventType !== 3 && !($rosterOnly ?? false) && !($centralTeamWorkspace ?? false))
  @include('backend.adminPage.admin_show.tabs.player-order-legacy')
@else
<div class="tab-pane fade" id="tab-order" role="tabpanel" aria-labelledby="order-tab">
  <div class="roster-toolbar order-filter-bar mb-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <label class="visually-hidden" for="order-team-search">Find a team</label>
      <input type="search" id="order-team-search" class="form-control" placeholder="Find a team" data-order-search>
      <button type="button" class="btn btn-outline-secondary" data-order-clear>Clear search</button>
    </div>
    <p class="small text-muted mb-0">{{ ($isRegionalPreview ?? false) ? 'Preview controls do not save changes.' : (($rosterOnly ?? false) ? 'Move players up or down within their team.' : 'Moves save immediately. Ranking-managed teams use selection order. Fixture locks appear beside each team.') }}</p>
  </div>
  @forelse($event->regions as $k => $region)
    <section id="order-region-{{ $region->id }}" data-roster-panel="order" data-region-id="{{ $region->id }}" @if($rosterOnly ?? false)data-loaded="true"
@endif
 @if($k !== 0) hidden
@endif
>
      @if($rosterOnly ?? false)
        @php($orderLocked = $region->teams->contains(fn ($team) => app(\App\Services\TeamRosterService::class)->isLocked($event, $team)))
        @include('backend.adminPage.admin_show.tabs.order-region')
      @else<div class="p-4" role="status">Select a region to load player order.</div>
@endif

    </section>
  @empty <div class="alert alert-light border">No regions have been added.</div> @endforelse
</div>

@endif
