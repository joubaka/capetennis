@php
  /** Normalize relations */
  $regionsInEvent = $event->regions ?? collect();
@endphp

<div class="tab-pane fade show active" id="tab-players">

  {{-- REGION SUB TABS --}}
  <div class="subtabs-sticky">
    <ul class="nav nav-tabs px-2">
      @foreach($regionsInEvent as $k => $region)
        <li class="nav-item">
          <button class="nav-link {{ $k === 0 ? 'active' : '' }}"
                  data-bs-toggle="tab"
                  data-bs-target="#players-region-{{ $region->id }}">
            {{ $region->region_name }}
          </button>
        </li>
      @endforeach
    </ul>
  </div>

  {{-- GLOBAL ACTIONS --}}
  <div class="d-flex align-items-center gap-2 mt-2 mb-2">
    <a href="{{ route('event.players.exportPdf', $event->id) }}"
       class="btn btn-sm btn-outline-danger" target="_blank">
      <i class="ti ti-file-text"></i> Export PDF
    </a>

    <a href="{{ route('event.players.exportExcel', $event->id) }}"
       class="btn btn-sm btn-outline-success" target="_blank">
      <i class="ti ti-file-spreadsheet"></i> Export Excel
    </a>
  </div>

  {{-- REGION PANELS --}}
  <div class="tab-content">

    @foreach($regionsInEvent as $k => $region)
      <div class="tab-pane fade {{ $k === 0 ? 'show active' : '' }}"
           id="players-region-{{ $region->id }}">

        <div class="card mt-3">
          <div class="card-header">
            <h5 class="m-0">Players — {{ $region->region_name }}</h5>
          </div>

          <div class="card-body">

            @forelse($region->teams ?? collect() as $team)

              {{-- TEAM HEADER --}}
              <div class="mb-4">

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <h5 class="mb-0">{{ $team->name }}</h5>
                    <small class="text-muted">Team ID: {{ $team->id }}</small>
                  </div>

                  <div class="d-flex align-items-center gap-2">

                    <span class="badge {{ $team->published ? 'bg-label-success' : 'bg-label-danger' }}">
                      {{ $team->published ? 'Published' : 'Not Published' }}
                    </span>

                    <button class="btn btn-sm btn-outline-primary editRosterBtn"
                            data-teamid="{{ $team->id }}">
                      <i class="ti ti-users"></i> Edit Roster
                    </button>

                    {{-- EMAIL TEAM --}}
                    <button class="btn btn-sm btn-outline-secondary emailTeamBtn"
                            data-teamid="{{ $team->id }}"
                            data-teamname="{{ $team->name }}">
                      <i class="ti ti-mail"></i> Email
                    </button>

                    <a href="{{ route('backend.region.clothing.edit', $region->id) }}"
                       class="btn btn-sm btn-outline-warning">
                      <i class="ti ti-settings"></i> Clothing Setup
                    </a>

                    <a href="{{ route('backend.region.clothing.orders', $region->id) }}"
                       class="btn btn-sm btn-outline-info"
                       target="_blank">
                      <i class="ti ti-shirt"></i> Clothing Orders
                    </a>
                  </div>
                </div>

                {{-- TEAM TABLE --}}
                <div class="table-responsive">
                  <table class="table table-sm table-bordered text-nowrap" style="min-width:1200px">
                    <thead class="table-light">
                      <tr>
                        <th>#</th>
                        <th>Player</th>
                        <th>Email</th>
                        <th>Cell</th>
                        <th>Pay Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>

                    <tbody>
                      @foreach($team->teamPlayers ?? [] as $slot)
                        @php
                          $player = ((int)$slot->player_id > 0) ? $slot->player : null;
                          $np     = (!$player) ? $slot->noProfile : null;
                          $name   = $player
                            ? trim($player->name.' '.$player->surname)
                            : ($np ? trim($np->name.' '.$np->surname) : '—');
                          $paid   = (int)($slot->pay_status ?? 0);
                        @endphp

                        <tr data-playerteamid="{{ $slot->id }}">
                          <td>
                            <span class="badge bg-label-primary">{{ $slot->rank }}</span>
                          </td>

                          <td>
                            {{ $name }}
                            @if(!$player)
                              <span class="badge bg-label-warning ms-1">No Profile</span>
                            @endif
                          </td>

                          <td>{{ $player->email ?? '—' }}</td>
                          <td>{{ $player->cellNr ?? '—' }}</td>

                          <td class="payStatus">
                            <span class="badge {{ $paid ? 'bg-label-success' : 'bg-label-danger' }}">
                              {{ $paid ? 'Paid' : 'Unpaid' }}
                            </span>
                          </td>

                          <td>
                            <div class="dropdown">
                              <button class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical"></i>
                              </button>

                              <div class="dropdown-menu">

                                {{-- EMAIL PLAYER (only if profile exists) --}}
                                @if($player)
                                  <a class="dropdown-item emailPlayer"
                                     data-playerid="{{ $player->id }}"
                                     data-name="{{ $name }}">
                                    <i class="ti ti-mail me-1"></i> Email Player
                                  </a>
                                @else
                                  <span class="dropdown-item text-muted">
                                    <i class="ti ti-mail me-1"></i> No email available
                                  </span>
                                @endif

                                <a class="dropdown-item changePayStatus"
                                   data-pivot="{{ $slot->id }}">
                                  <i class="ti ti-credit-card me-1"></i> Change Pay Status
                                </a>

                                <a class="dropdown-item refundToWallet"
                                   data-pivot="{{ $slot->id }}">
                                  <i class="ti ti-cash me-1"></i> Refund to Wallet
                                </a>

                              </div>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>

              </div>

            @empty
              <div class="alert alert-light text-center">
                No teams in this region
              </div>
            @endforelse

          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>

@include('_partials._modals.modal-add-send-email')

<div class="modal fade" id="edit-roster-modal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Team Roster</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="rosterEditor" class="min-vh-25">
          Loading…
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button class="btn btn-primary" id="saveRosterBtn">
          Save Roster
        </button>
      </div>
    </div>
  </div>
</div>
