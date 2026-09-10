@php($importedRoster = $regionTeam->team_players_no_profile->sortBy('rank')->values())

<ul class="nav nav-tabs px-3 pt-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team-players-{{ $regionTeam->id }}" type="button"><i class="ti ti-users me-1"></i>Players</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-order-{{ $regionTeam->id }}" type="button"><i class="ti ti-list-numbers me-1"></i>Player order</button></li>
</ul>
<div class="tab-content p-0">
  <div class="tab-pane fade show active" id="team-players-{{ $regionTeam->id }}">
    <div class="p-3 border-bottom small text-muted">Imported roster names remain visible even before a Cape Tennis player profile is linked. Correcting a name does not create, unlink or edit a player profile.</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Rank</th><th>Imported roster name</th><th>Profile status</th><th>Contact</th></tr></thead>
        <tbody>
          @forelse($importedRoster as $slot)
            @php($linkedProfile = $slot->player_profile ? $slot->profile : null)
            @php($contactEmail = $linkedProfile ? ($teamSelectionContacts->primaryEmail($linkedProfile) ?: $slot->email) : $slot->email)
            @php($contactCell = $linkedProfile?->cellNr ?: $slot->cell_nr)
            <tr>
              <td><span class="badge bg-label-primary">Rank {{ $slot->rank }}</span></td>
              <td style="min-width:320px">
                <form method="POST" action="{{ route('backend.team-selection.imported-players.update', [$event, $eventRegion, $regionTeam, $slot]) }}" class="row g-1 align-items-center">
                  @csrf @method('PATCH')
                  <div class="col"><label class="visually-hidden" for="imported-name-{{ $slot->id }}">First name</label><input id="imported-name-{{ $slot->id }}" name="name" value="{{ $slot->name }}" class="form-control form-control-sm" maxlength="100" required></div>
                  <div class="col"><label class="visually-hidden" for="imported-surname-{{ $slot->id }}">Surname</label><input id="imported-surname-{{ $slot->id }}" name="surname" value="{{ $slot->surname }}" class="form-control form-control-sm" maxlength="100" required></div>
                  <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Save name</button></div>
                </form>
              </td>
              <td>
                @if($linkedProfile)
                  <span class="badge bg-label-success">Imported · Linked</span>
                  <div class="small text-muted mt-1">{{ $linkedProfile->full_name }}</div>
                @else
                  <span class="badge bg-label-info">Imported · Unlinked</span>
                  <div class="small text-muted mt-1">Profile can be linked from the public team page.</div>
                @endif
              </td>
              <td><div>{{ $contactEmail ?: 'No email' }}</div><div class="small text-muted">{{ $contactCell ?: 'No cell number' }}</div></td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No imported roster players have been added to this team yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="tab-pane fade" id="team-order-{{ $regionTeam->id }}">
    <div class="p-3 border-bottom small text-muted">Change the playing order without changing names, linked profiles or payment state. Every move is audited.</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Roster rank</th><th>Player</th><th>Profile status</th><th>Move</th></tr></thead>
        <tbody>
          @foreach($importedRoster as $slot)
            <tr>
              <td><span class="badge bg-label-primary">Rank {{ $slot->rank }}</span></td>
              <td><strong>{{ trim($slot->name.' '.$slot->surname) }}</strong></td>
              <td><span class="badge {{ $slot->player_profile ? 'bg-label-success' : 'bg-label-info' }}">{{ $slot->player_profile ? 'Imported · Linked' : 'Imported · Unlinked' }}</span></td>
              <td><div class="d-flex gap-1">
                <form method="POST" action="{{ route('backend.team-selection.imported-players.move', [$event, $eventRegion, $regionTeam, $slot]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" @disabled($loop->first)><i class="ti ti-arrow-up"></i></button></form>
                <form method="POST" action="{{ route('backend.team-selection.imported-players.move', [$event, $eventRegion, $regionTeam, $slot]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" @disabled($loop->last)><i class="ti ti-arrow-down"></i></button></form>
              </div></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
