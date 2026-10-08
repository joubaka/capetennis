@if((int) $event->eventType !== 3)
  @include('backend.adminPage.admin_show.tabs.player-order-legacy')
@else
<div class="tab-pane fade" id="tab-order" role="tabpanel" aria-labelledby="order-tab">
  <p class="alert alert-info">Set player order before generating fixtures. Ranking-managed teams use selection order. Generated fixtures lock reordering to preserve match lineups, times and results.</p>
  <div class="roster-toolbar mb-3">
    <div class="row g-3">
      <div class="col-md-5"><label for="order-region" class="form-label">Region</label><select id="order-region" class="form-select" data-order-region>
        @foreach($event->regions as $region)<option value="{{ $region->id }}">{{ $region->region_name }}</option>@endforeach
      </select></div>
      <div class="col-md-7"><label for="order-team-search" class="form-label">Find a team</label><input type="search" id="order-team-search" class="form-control" placeholder="Team name" data-order-search></div>
    </div>
    <button type="button" class="btn btn-outline-secondary mt-3" data-order-clear>Clear search</button>
    <p class="small text-muted mt-2 mb-0">Moves save immediately. Editable teams can be reordered here; ranking-managed teams use selection order.</p>
  </div>
  @forelse($event->regions as $k => $region)
    <section id="order-region-{{ $region->id }}" data-roster-panel="order" data-region-id="{{ $region->id }}" @if($k !== 0) hidden @endif><div class="p-4" role="status">Select a region to load player order.</div></section>
  @empty <div class="alert alert-light border">No regions have been added.</div> @endforelse
</div>
@endif
