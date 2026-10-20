<div class="roster-toolbar mb-3" data-team-context data-roster-url="{{ route('admin.events.teams', $event) }}" data-selection-url="{{ ($rosterOnly ?? false) ? '' : route('backend.team-selection.index', [$event, 'workspace_fragment' => 1]) }}" data-default-mode="{{ $centralTeamMode ?? 'players' }}">
  <div class="row g-3">
    <div class="col-md-5"><label class="form-label" for="roster-region">Region</label><select id="roster-region" class="form-select" data-roster-region>
      @foreach($event->regions as $contextRegion)<option value="{{ $contextRegion->id }}" data-event-region-id="{{ ($eventRegions ?? collect())->firstWhere('region_id', $contextRegion->id)?->id }}">{{ $contextRegion->region_name }}</option>@endforeach
    </select></div>
    <div class="col-md-7"><label class="form-label" for="workspace-team">Team</label><select id="workspace-team" class="form-select" data-workspace-team>
      <option value="">All teams in this region</option>
      @foreach($event->regions as $contextRegion)
        @foreach($contextRegion->teams as $contextTeam)<option value="{{ $contextTeam->id }}" data-region="{{ $contextRegion->id }}" data-managed="{{ ($teamSelectionInvitations ?? collect())->has($contextTeam->id) ? '1' : '0' }}">{{ $contextTeam->name }}</option>@endforeach
      @endforeach
    </select></div>
  </div>
  <p class="small text-muted mb-0 mt-2">{{ ($rosterOnly ?? false) ? 'Keep this region and team while viewing players and setting order.' : 'Keep this region and team while viewing players, setting order or managing invitations.' }}</p>
</div>
