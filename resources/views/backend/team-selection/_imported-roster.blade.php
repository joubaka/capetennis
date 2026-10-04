@php($importedRoster = $regionTeam->team_players_no_profile->sortBy('rank')->values())
@php($transferOrders = auth()->user()->hasRole('super-user') ? \App\Models\TeamPaymentOrder::query()->where('event_id', $event->id)->where('team_id', $regionTeam->id)->whereNull('withdrawn_at')->where('pay_status', true)->get()->keyBy('effective_player_id') : collect())

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
                @if($linkedProfile && auth()->user()->hasRole('super-user') && ($coverageOrder = $transferOrders->get($slot->player_profile)))
                  <details class="mt-2">
                    <summary class="small text-primary">Move payment to another player</summary>
                    <form method="POST" action="{{ route('backend.team-selection.imported-players.payment.transfer', [$event, $eventRegion, $regionTeam, $slot]) }}" class="mt-2" style="min-width:220px">
                      @csrf
                      <input type="hidden" name="order_id" value="{{ $coverageOrder->id }}">
                      <input type="hidden" name="expected_player_id" value="{{ $slot->player_profile }}">
                      <label class="small" for="transfer-target-{{ $slot->id }}">Unpaid player in this team</label>
                      <select id="transfer-target-{{ $slot->id }}" name="target_player_id" class="form-select form-select-sm" required>
                        <option value="">Choose player</option>
                        @foreach($importedRoster as $candidate)
                          @if($candidate->player_profile && $candidate->player_profile != $slot->player_profile && !$transferOrders->has($candidate->player_profile) && !$candidate->pay_status)
                            <option value="{{ $candidate->player_profile }}" data-rank-slot-id="{{ $candidate->id }}" data-rank-player-name="{{ $candidate->profile?->full_name ?: trim($candidate->name.' '.$candidate->surname) }}">{{ $candidate->profile?->full_name ?: trim($candidate->name.' '.$candidate->surname) }} · Rank {{ $candidate->rank }}</option>
                          @endif
                        @endforeach
                      </select>
                      <label class="small mt-2" for="transfer-reason-{{ $slot->id }}">Reason</label>
                      <input id="transfer-reason-{{ $slot->id }}" name="reason" maxlength="1000" class="form-control form-control-sm" required>
                      <p class="small text-muted mt-2">Use this payment for the selected player. Any later refund goes to the original payer.</p>
                      <label class="small d-flex gap-2 my-2"><input type="checkbox" name="confirm_transfer" value="1" required><span>Confirm this player becomes unpaid and the selected player becomes paid.</span></label>
                      <button class="btn btn-sm btn-outline-primary">Move payment</button>
                    </form>
                  </details>
                @endif
                @if($linkedProfile)
                  <span class="badge bg-label-success">Imported · Linked</span>
                  <div class="small text-muted mt-1">{{ $linkedProfile->full_name }}</div>
                @else
                  <span class="badge bg-label-info">Imported · Unlinked</span>
                  <div class="small text-muted mt-1">Profile can be linked from the public team page.</div>
                @endif
                @if(app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event))
                  @if($linkedProfile && !(int) $slot->pay_status)
                    <details class="mt-2">
                      <summary class="small text-danger">Unlink unpaid profile / reset</summary>
                      <form method="POST" action="{{ route('backend.team-selection.imported-players.profile.destroy', [$event, $eventRegion, $regionTeam, $slot]) }}" class="mt-2" style="min-width:220px">
                        @csrf @method('DELETE')
                        <input type="hidden" name="expected_player_id" value="{{ (int) $slot->player_profile }}">
                        <input type="hidden" name="expected_rank" value="{{ $slot->rank }}">
                        <p class="small text-muted">Keeps the imported name, contact and rank and releases unpaid wallet reservations. Paid, in-flight or previously participating profiles cannot be reset here.</p>
                        <label class="small d-flex gap-2 my-2"><input type="checkbox" name="confirm_unlink" value="1" required><span>Confirm unlinking {{ $linkedProfile->full_name }}.</span></label>
                        <button class="btn btn-sm btn-outline-danger">Unlink profile and reset</button>
                      </form>
                    </details>
                  @endif
                  <details class="mt-2">
                    <summary class="small text-primary">{{ $linkedProfile ? 'Replace linked profile' : 'Link player profile' }}</summary>
                    <form method="POST" action="{{ route('backend.team-selection.imported-players.profile.update', [$event, $eventRegion, $regionTeam, $slot]) }}" class="mt-2" style="min-width:220px">
                      @csrf @method('PATCH')
                      <input type="hidden" name="expected_player_id" value="{{ (int) $slot->player_profile }}">
                      <input type="hidden" name="expected_rank" value="{{ $slot->rank }}">
                      <label class="small" for="relink-player-{{ $slot->id }}">Replacement profile</label>
                      <select id="relink-player-{{ $slot->id }}" name="player_id" class="form-select team-player-select" data-placeholder="Search player name…" data-search-url="{{ route('backend.team-selection.imported-players.profiles.search', [$event, $eventRegion, $regionTeam, $slot]) }}" required><option value=""></option></select>
                      <label class="small d-flex gap-2 my-2"><input type="checkbox" name="confirm_replacement" value="1" required><span>Confirm replacing {{ $linkedProfile?->full_name ?: 'the unlinked position' }} with the selected profile.</span></label>
                      <p class="small text-muted mb-2">Imported names stay unchanged. Profiles with registration, payment or fixture history cannot be relinked here.</p>
                      <button class="btn btn-sm btn-outline-primary">Save profile link</button>
                    </form>
                  </details>
                @endif
              </td>
              <td data-effective-contact>
                <div data-effective-email>{{ $contactEmail ?: 'No email' }}</div>
                <span class="badge {{ $linkedEmail ? 'bg-label-success' : 'bg-label-info' }}" data-effective-email-source>{{ $linkedEmail ? 'Linked profile email' : ($linkedProfile ? 'No-profile fallback email' : 'No-profile email') }}</span>
                <div class="small text-muted mt-1">{{ $contactCell ?: 'No cell number' }}</div>
                @if($contactCell)<span class="badge {{ $linkedProfile?->cellNr ? 'bg-label-success' : 'bg-label-info' }}">{{ $linkedProfile?->cellNr ? 'Linked profile cell' : 'No-profile cell' }}</span>@endif
                <button class="btn btn-sm btn-outline-secondary roster-email-button mt-2 {{ filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? '' : 'd-none' }}" data-imported-email-action type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="imported_player" data-team-id="{{ $regionTeam->id }}" data-slot-id="{{ $slot->id }}" data-recipient="{{ trim($slot->name.' '.$slot->surname) }} · {{ $contactEmail }}"><i class="ti ti-mail me-1" aria-hidden="true"></i>Email player</button>
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
        <tbody class="imported-roster-order roster-order-sortable" data-reorder-url="{{ route('backend.team-selection.imported-players.reorder', [$event, $eventRegion, $regionTeam]) }}" data-reorder-field="slot_ids">
          @foreach($importedRoster as $slot)
            <tr draggable="true" data-order-id="{{ $slot->id }}" data-slot-id="{{ $slot->id }}">
              <td><span class="badge bg-label-primary">Rank {{ $slot->rank }}</span></td>
              <td><span class="drag-handle me-2" title="Drag to reorder"><i class="ti ti-grip-vertical"></i></span><strong data-imported-player-name>{{ trim($slot->name.' '.$slot->surname) }}</strong></td>
              <td><span class="badge {{ $slot->player_profile ? 'bg-label-success' : 'bg-label-info' }}">{{ $slot->player_profile ? 'Imported · Linked' : 'Imported · Unlinked' }}</span></td>
              <td><div class="d-flex gap-1">
                <form method="POST" action="{{ route('backend.team-selection.imported-players.move', [$event, $eventRegion, $regionTeam, $slot]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" aria-label="Move {{ trim($slot->name.' '.$slot->surname) }} up" @disabled($loop->first)><i class="ti ti-arrow-up"></i></button></form>
                <form method="POST" action="{{ route('backend.team-selection.imported-players.move', [$event, $eventRegion, $regionTeam, $slot]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" aria-label="Move {{ trim($slot->name.' '.$slot->surname) }} down" @disabled($loop->last)><i class="ti ti-arrow-down"></i></button></form>
              </div></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
