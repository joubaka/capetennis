@if((int) $event->eventType !== 3 && !($rosterOnly ?? false) && !($centralTeamWorkspace ?? false))
  @include('backend.adminPage.admin_show.tabs.players-legacy')
@else
@php($regionsInEvent = $event->regions ?? collect())
<div class="tab-pane fade show active" id="tab-players" role="tabpanel" aria-labelledby="players-tab">
  @forelse($regionsInEvent as $k => $region)
    <section id="players-region-{{ $region->id }}" data-roster-panel="players" data-region-id="{{ $region->id }}" @if($rosterOnly ?? false)data-loaded="true"
@endif
 @if($k !== 0) hidden
@endif
>
      @if(((int) $event->eventType === 3 || ($centralTeamWorkspace ?? false)) && !($rosterOnly ?? false))<div class="roster-panel-placeholder p-4" role="status">Select a region to load its roster.</div>
      @else @include('backend.adminPage.admin_show.tabs.players-region')
@endif

    </section>
  @empty <div class="alert alert-light border mt-3">No regions have been added to this event.</div> @endforelse
</div>

@endif
