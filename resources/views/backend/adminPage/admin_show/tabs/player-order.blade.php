@if((int) $event->eventType !== 3 && !($rosterOnly ?? false) && !($centralTeamWorkspace ?? false))
  @include('backend.adminPage.admin_show.tabs.player-order-legacy')
@else
<div class="tab-pane fade" id="tab-order" role="tabpanel" aria-labelledby="order-tab">
  <p class="alert alert-info">{{ ($rosterOnly ?? false) ? 'Use Move up and Move down to review the player order.' : 'Set player order before generating fixtures. Ranking-managed teams use selection order. Generated fixtures lock reordering to preserve match lineups, times and results.' }}</p>
  <div class="roster-toolbar mb-3">
    <div class="row g-3">
      <div class="col-md-7"><label for="order-team-search" class="form-label">Find a team</label><input type="search" id="order-team-search" class="form-control" placeholder="Team name" data-order-search></div>
    </div>
    <button type="button" class="btn btn-outline-secondary mt-3" data-order-clear>Clear search</button>
    <p class="small text-muted mt-2 mb-0">{{ ($isRegionalPreview ?? false) ? 'Preview controls do not save changes.' : (($rosterOnly ?? false) ? 'Move players up or down within their team.' : 'Moves save immediately. Editable teams can be reordered here; ranking-managed teams use selection order.') }}</p>
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
