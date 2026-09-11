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
        <tbody class="imported-roster-players" data-team-id="{{ $regionTeam->id }}">
          @forelse($importedRoster as $slot)
            @php($linkedProfile = $slot->player_profile ? $slot->profile : null)
            @php($linkedEmail = $linkedProfile ? $teamSelectionContacts->primaryEmail($linkedProfile) : null)
            @php($contactEmail = $linkedEmail ?: $slot->email)
            @php($contactCell = $linkedProfile?->cellNr ?: $slot->cell_nr)
            <tr data-slot-id="{{ $slot->id }}">
              <td><span class="badge bg-label-primary">Rank {{ $slot->rank }}</span></td>
              <td style="min-width:320px">
                <form method="POST" action="{{ route('backend.team-selection.imported-players.update', [$event, $eventRegion, $regionTeam, $slot]) }}" class="row g-1 align-items-center imported-name-form" data-slot-id="{{ $slot->id }}">
                  @csrf @method('PATCH')
                  <div class="col"><label class="visually-hidden" for="imported-name-{{ $slot->id }}">First name</label><input id="imported-name-{{ $slot->id }}" name="name" value="{{ $slot->name }}" class="form-control form-control-sm" maxlength="100" required></div>
                  <div class="col"><label class="visually-hidden" for="imported-surname-{{ $slot->id }}">Surname</label><input id="imported-surname-{{ $slot->id }}" name="surname" value="{{ $slot->surname }}" class="form-control form-control-sm" maxlength="100" required></div>
                  <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Save name</button></div>
                </form>
                @if(!$linkedProfile)
                  <form method="POST" action="{{ route('backend.team-selection.imported-players.email.update', [$event, $eventRegion, $regionTeam, $slot]) }}" class="d-flex align-items-center gap-1 mt-2 imported-email-form" data-slot-id="{{ $slot->id }}">
                    @csrf @method('PATCH')
                    <span class="badge bg-label-info text-nowrap">No-profile email</span>
                    <label class="visually-hidden" for="imported-email-{{ $slot->id }}">No-profile email</label>
                    <input id="imported-email-{{ $slot->id }}" type="email" name="email" value="{{ $slot->email }}" class="form-control form-control-sm" maxlength="255" placeholder="Click to add email" required>
                    <button class="btn btn-sm btn-outline-primary text-nowrap">Save email</button>
                  </form>
                @endif
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
              <td data-effective-contact>
                <div data-effective-email>{{ $contactEmail ?: 'No email' }}</div>
                <span class="badge {{ $linkedEmail ? 'bg-label-success' : 'bg-label-info' }}" data-effective-email-source>{{ $linkedEmail ? 'Linked profile email' : ($linkedProfile ? 'No-profile fallback email' : 'No-profile email') }}</span>
                <div class="small text-muted mt-1">{{ $contactCell ?: 'No cell number' }}</div>
                @if($contactCell)<span class="badge {{ $linkedProfile?->cellNr ? 'bg-label-success' : 'bg-label-info' }}">{{ $linkedProfile?->cellNr ? 'Linked profile cell' : 'No-profile cell' }}</span>@endif
              </td>
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
        <tbody class="imported-roster-sortable" data-reorder-url="{{ route('backend.team-selection.imported-players.reorder', [$event, $eventRegion, $regionTeam]) }}">
          @foreach($importedRoster as $slot)
            <tr draggable="true" data-slot-id="{{ $slot->id }}">
              <td><span class="badge bg-label-primary">Rank {{ $slot->rank }}</span></td>
              <td><span class="drag-handle me-2" title="Drag to reorder"><i class="ti ti-grip-vertical"></i></span><strong data-imported-player-name>{{ trim($slot->name.' '.$slot->surname) }}</strong></td>
              <td><span class="badge {{ $slot->player_profile ? 'bg-label-success' : 'bg-label-info' }}">{{ $slot->player_profile ? 'Imported · Linked' : 'Imported · Unlinked' }}</span></td>
              <td><span class="small text-muted"><i class="ti ti-grip-vertical me-1"></i>Drag row</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
