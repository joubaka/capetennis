<div class="roster-toolbar team-context-toolbar mb-2" data-team-context data-roster-url="{{ route('admin.events.teams', $event) }}" data-selection-url="{{ ($rosterOnly ?? false) ? '' : route('backend.team-selection.index', [$event, 'workspace_fragment' => 1]) }}" data-default-mode="{{ $centralTeamMode ?? 'players' }}">
  <div class="team-context-fields">
    <div class="team-context-field team-context-region"><label class="form-label" for="roster-region"><i class="ti ti-map-pin" aria-hidden="true"></i> Region</label><select id="roster-region" class="form-select" data-roster-region>
      @foreach($event->regions as $contextRegion)<option value="{{ $contextRegion->id }}" data-event-region-id="{{ ($eventRegions ?? collect())->firstWhere('region_id', $contextRegion->id)?->id }}">{{ $contextRegion->region_name }}</option>@endforeach
    </select></div>
    <div class="team-context-field"><label class="form-label" for="workspace-team">Team</label><select id="workspace-team" class="form-select" data-workspace-team>
      <option value="">All teams in this region</option>
      @foreach($event->regions as $contextRegion)
        @foreach($contextRegion->teams as $contextTeam)<option value="{{ $contextTeam->id }}" data-region="{{ $contextRegion->id }}" data-managed="{{ ($teamSelectionInvitations ?? collect())->has($contextTeam->id) ? '1' : '0' }}">{{ $contextTeam->name }}</option>@endforeach
      @endforeach
    </select></div>
    @if($rosterOnly ?? false)
      <div class="team-context-field" data-regional-search><label class="form-label" for="roster-search">Find a player or team</label><input id="roster-search" class="form-control" type="search" placeholder="Name or team" data-roster-search></div>
    @endif
  </div>
  @unless($rosterOnly ?? false)
    @if((int) $event->eventType === 3 || ($centralTeamWorkspace ?? false))
      @include('backend.adminPage.admin_show._roster-filters')
    @endif
    @can('event.manage', $event)@include('backend.adminPage.admin_show._region-organizer')@endcan
  @endunless
</div>
