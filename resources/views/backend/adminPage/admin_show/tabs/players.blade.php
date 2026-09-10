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
  <div class="player-global-actions d-flex align-items-center justify-content-between gap-2">
    <div>
      <div class="fw-semibold">Active-roster exports</div>
      <div class="small text-muted">Downloads contain occupied team places only; reserves remain in the selection queue.</div>
    </div>
    <div class="player-global-actions__buttons d-flex align-items-center gap-2">
      <a href="{{ route('backend.team-selection.index', $event) }}" class="btn btn-sm btn-primary">
        <i class="ti ti-list-check"></i> Team Selection & Reserves
      </a>
      <a href="{{ route('event.players.exportPdf', $event->id) }}"
         class="btn btn-sm btn-outline-danger" target="_blank">
        <i class="ti ti-file-text"></i> Export PDF
      </a>

      <a href="{{ route('event.players.exportExcel', $event->id) }}"
         class="btn btn-sm btn-outline-success" target="_blank">
        <i class="ti ti-file-spreadsheet"></i> Export Excel
      </a>
    </div>
  </div>

  {{-- REGION PANELS --}}
  <div class="tab-content region-tab-content">

    @foreach($regionsInEvent as $k => $region)
      <div class="tab-pane fade {{ $k === 0 ? 'show active' : '' }}"
           id="players-region-{{ $region->id }}">

        <div class="card mt-3">

          {{-- ✅ REGION HEADER (BUTTON IS NOW CORRECTLY SCOPED) --}}
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="m-0">Players — {{ $region->region_name }}</h5>

            <div class="region-email-actions d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary emailRegionBtn"
                      data-regionid="{{ $region->id }}"
                      data-regionname="{{ $region->region_name }}">
                <i class="ti ti-mail"></i> Email Active Roster
              </button>

              {{-- ✅ NEW: Email Unpaid Players in Region --}}
              <button class="btn btn-sm btn-outline-warning emailUnpaidRegionBtn"
                      data-regionid="{{ $region->id }}"
                      data-regionname="{{ $region->region_name }}">
                <i class="ti ti-alert-circle"></i> Email Unpaid Active Roster
              </button>
            </div>
          </div>

          <div class="card-body">

            @forelse($region->teams ?? collect() as $team)

              @php
                $selectionInvitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect());
                $rankingManaged = $selectionInvitations->isNotEmpty();
                $teamReserves = $selectionInvitations
                  ->where('status', \App\Models\TeamSelectionInvitation::RESERVE)
                  ->sortBy('queue_position');
              @endphp

              {{-- TEAM HEADER --}}
              <div class="mb-4">

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <h5 class="mb-0">{{ $team->name }}</h5>
                    <small class="text-muted">Team ID: {{ $team->id }}</small>
                    @if($rankingManaged)
                      <span class="badge bg-label-info ms-2">Ranking-managed</span>
                    @endif
                  </div>

                  <div class="d-flex align-items-center gap-2">
                    <span class="badge {{ $team->published ? 'bg-label-success' : 'bg-label-danger' }} me-2">
                      {{ $team->published ? 'Published' : 'Not Published' }}
                    </span>

                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-dots"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                          <a class="dropdown-item emailTeamBtn" href="#" data-teamid="{{ $team->id }}" data-teamname="{{ $team->name }}">
                            <i class="ti ti-mail me-1"></i> Email Active Roster
                          </a>
                        </li>
                        <!-- team-level 'Email Unpaid Players' removed from dropdown -->
                        <li><hr class="dropdown-divider"></li>
                        @if($rankingManaged)
                          <li><a class="dropdown-item" href="{{ route('backend.team-selection.index', $event) }}"><i class="ti ti-list-check me-1"></i> Manage Selection & Reserves</a></li>
                        @else
                          <li><a class="dropdown-item editRosterBtn" href="#" data-teamid="{{ $team->id }}"><i class="ti ti-users me-1"></i> Edit Roster</a></li>
                        @endif
                        <li>
                          <a class="dropdown-item" href="{{ route('backend.region.clothing.edit', $region->id) }}">
                            <i class="ti ti-settings me-1"></i> Clothing Setup
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item" href="{{ route('backend.region.clothing.orders', $region->id) }}" target="_blank">
                            <i class="ti ti-shirt me-1"></i> Clothing Orders
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                {{-- TEAM TABLE --}}
                <div class="table-responsive">
                  <table class="table table-sm table-bordered text-nowrap team-player-table">
                    <colgroup>
                      <col style="width: 7%">
                      <col style="width: 22%">
                      <col style="width: 31%">
                      <col style="width: 16%">
                      <col style="width: 14%">
                      <col style="width: 10%">
                    </colgroup>
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
                              <button type="button" class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                              </button>

                              <div class="dropdown-menu dropdown-menu-end">
                                @unless($rankingManaged)
                                <a class="dropdown-item replacePlayerBtn"
                                   data-slotid="{{ $slot->id }}"
                                   data-teamid="{{ $team->id }}"
                                   data-playername="{{ $name }}">
                                  <i class="ti ti-refresh me-1"></i> Replace Player
                                </a>
                                @endunless

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

                                @unless($rankingManaged)
                                <a class="dropdown-item changePayStatus"
                                   data-pivot="{{ $slot->id }}">
                                  <i class="ti ti-credit-card me-1"></i> Change Pay Status
                                </a>

                                <a class="dropdown-item refundToWallet"
                                   data-pivot="{{ $slot->id }}">
                                  <i class="ti ti-cash me-1"></i> Refund to Wallet
                                </a>
                                @else
                                  <a class="dropdown-item" href="{{ route('backend.team-selection.index', $event) }}"><i class="ti ti-list-check me-1"></i> Manage in Team Selection</a>
                                @endunless
                              </div>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>

                @if($rankingManaged)
                  <div class="border rounded bg-light p-3 mt-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                      <div><strong>Reserve queue</strong><div class="small text-muted">Held back from the active roster, draws, exports and active-roster email until promoted.</div></div>
                      <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.team-selection.index', $event) }}">Manage selection</a>
                    </div>
                    @forelse($teamReserves as $reserve)
                      <div class="d-flex flex-wrap gap-2 justify-content-between border-top py-2 small">
                        <span><strong>#{{ $loop->iteration }}</strong> {{ $reserve->player?->full_name ?? 'Missing player' }}</span>
                        <span class="text-muted">Ranking #{{ $reserve->ranking_position ?? '—' }}</span>
                      </div>
                    @empty
                      <div class="small text-muted">No reserves remain for this team.</div>
                    @endforelse
                  </div>
                @endif

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
<div class="modal fade" id="replaceRosterModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="replaceRosterModalLabel">
          Edit Roster
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="replaceRosterModalBody">
        {{-- AJAX content --}}
      </div>
    </div>
  </div>
</div>





<div class="modal fade" id="replacePlayerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Replace Player</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="replacePlayerModalBody">
        {{-- AJAX loads here --}}
      </div>
    </div>
  </div>
</div>


