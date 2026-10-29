<div class="roster-toolbar mb-3" data-team-context data-roster-url="{{ route('admin.events.teams', $event) }}" data-selection-url="{{ ($rosterOnly ?? false) ? '' : route('backend.team-selection.index', [$event, 'workspace_fragment' => 1]) }}" data-default-mode="{{ $centralTeamMode ?? 'players' }}">
  <div class="row g-3">
    <div class="{{ ($rosterOnly ?? false) ? 'col-md-4' : 'col-md-5' }}"><label class="form-label" for="roster-region">Region</label><select id="roster-region" class="form-select" data-roster-region>
      @foreach($event->regions as $contextRegion)<option value="{{ $contextRegion->id }}" data-event-region-id="{{ ($eventRegions ?? collect())->firstWhere('region_id', $contextRegion->id)?->id }}">{{ $contextRegion->region_name }}</option>@endforeach
    </select></div>
    <div class="{{ ($rosterOnly ?? false) ? 'col-md-4' : 'col-md-7' }}"><label class="form-label" for="workspace-team">Team</label><select id="workspace-team" class="form-select" data-workspace-team>
      <option value="">All teams in this region</option>
      @foreach($event->regions as $contextRegion)
        @foreach($contextRegion->teams as $contextTeam)<option value="{{ $contextTeam->id }}" data-region="{{ $contextRegion->id }}" data-managed="{{ ($teamSelectionInvitations ?? collect())->has($contextTeam->id) ? '1' : '0' }}">{{ $contextTeam->name }}</option>@endforeach
      @endforeach
    </select></div>
    @if($rosterOnly ?? false)
      <div class="col-md-4" data-regional-search><label class="form-label" for="roster-search">Find a player or team</label><input id="roster-search" class="form-control" type="search" placeholder="Name or team" data-roster-search></div>
    @endif
  </div>
  <p class="small text-muted mb-0 mt-2">{{ ($rosterOnly ?? false) ? 'Keep this region and team while viewing players and setting order.' : 'Keep this region and team while viewing players, setting order or managing invitations.' }}</p>
  @unless($rosterOnly ?? false)
    @can('event.manage', $event)@include('backend.adminPage.admin_show._region-organizer')@endcan
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">@if($regionalScoped ?? false)<a class="btn btn-primary" href="{{ route('backend.event-communications.index', $event) }}"><i class="ti ti-mail me-1"></i>Email</a>@else<button class="btn btn-primary" type="button" data-team-email-open><i class="ti ti-mail me-1"></i>Email</button>@endif<span class="small text-muted">Choose the message and recipients, then review before sending.</span><a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-communications.index', $event) }}">Email history</a></div>
    @include('backend.adminPage.admin_show._team-email')
  @endunless
</div>
