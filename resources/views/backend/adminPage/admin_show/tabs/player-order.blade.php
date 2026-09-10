{{-- ✅ Player Order Tab --}}
@php
  $regionsInEvent = $regionsInEvent ?? $event->regions ?? collect();
  $teamWorkspaceRegional = $teamWorkspaceRegional ?? false;
@endphp

<style>
  .drag-handle {
    cursor: grab;
  }
  .drag-handle * {
    pointer-events: none;
  }
</style>

<div class="tab-pane fade {{ $teamWorkspaceRegional ? 'show active' : '' }}" id="tab-order">

  {{-- 🔹 Region Sub Tabs --}}
  <div class="subtabs-sticky">
    <ul class="nav nav-tabs px-2">
      @forelse($regionsInEvent as $k => $region)
        <li class="nav-item">
          <button
            class="nav-link {{ $k === 0 ? 'active' : '' }}"
            data-bs-toggle="tab"
            data-bs-target="#order-region-{{ $region->id }}">
            {{ $region->region_name }}
          </button>
        </li>
      @empty
        <li class="nav-item">
          <span class="nav-link disabled">No regions</span>
        </li>
      @endforelse
    </ul>
  </div>

  <div class="tab-content">

    @foreach($regionsInEvent as $k => $region)
      <div
        class="tab-pane fade {{ $k === 0 ? 'show active' : '' }}"
        id="order-region-{{ $region->id }}">

        <div class="card mt-3">
          <div class="card-header">
            <h5 class="mb-0">Player Order — {{ $region->region_name }}</h5>
          </div>

          <div class="card-body">

            @forelse($region->teams ?? collect() as $team)

              @php
                $rankingManaged = ($teamSelectionInvitations ?? collect())->has($team->id);
                $orderedInvitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect())
                  ->whereIn('status', [
                    \App\Models\TeamSelectionInvitation::INVITED,
                    \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    \App\Models\TeamSelectionInvitation::PAID_CONFIRMED,
                  ])->sortBy('roster_rank')->values();
                $slots = ($team->teamPlayers ?? collect())->sortBy('rank')->values();
                $noProfiles = $team->noProfile
                  ? $team->team_players_no_profile()->orderBy('rank')->get()
                  : collect();

                $maxRows = max($slots->count(), $noProfiles->count());
              @endphp

              <div class="mb-4">

                {{-- Team Header --}}
                <div class="d-flex justify-content-between mb-2">
                  <div>
                    <h5 class="mb-0">{{ $team->name }}</h5>
                    <small class="text-muted">Team ID: {{ $team->id }}</small>
                    @if($rankingManaged)<span class="badge bg-label-info ms-2">Ranking-managed</span>@endif
                  </div>
                  <span class="badge {{ $team->published ? 'bg-label-success' : 'bg-label-danger' }}">
                    {{ $team->published ? 'Published' : 'Not Published' }}
                  </span>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle text-nowrap" style="min-width:1150px;">
                    <thead class="table-light">
                      <tr>
                        <th style="width:40px"></th>
                        <th>#</th>
                        <th>Profile Player</th>
                        @if($team->noProfile)
                          <th>No-Profile Player</th>
                        @endif
                        <th>Email</th>
                        <th>Cell</th>
                        <th>Pay Status</th>
                        @if($teamWorkspaceRegional)<th>Actions</th>@endif
                      </tr>
                    </thead>

                    <tbody class="{{ $rankingManaged ? '' : 'sortablePlayers' }}" data-team-id="{{ $team->id }}">

                      @for($rank = 1; $rank <= $maxRows; $rank++)
                        @php
                          $profileSlot = $slots->firstWhere('rank', $rank);
                          $profile = $profileSlot?->player;

                          $noProfile = $team->noProfile
                            ? $noProfiles->firstWhere('rank', $rank)
                            : null;

                          $pivotId = $profileSlot?->id ?? $noProfile?->id;
                          $rowType = $profile ? 'profile' : 'noprofile';
                          $payStatus = $profileSlot?->pay_status ?? 0;
                          $orderedInvitation = $teamWorkspaceRegional && $profile
                            ? $orderedInvitations->first(fn ($candidate) => (int) $candidate->player_id === (int) $profile->id)
                            : null;
                        @endphp

                        <tr
                          class="drag-item"
                          data-playerteamid="{{ $pivotId }}"
                          data-teamplayerid="{{ $profileSlot?->id }}"
                          data-noprofileid="{{ $noProfile?->id }}"
                          data-type="{{ $rowType }}">

                          <td class="text-center {{ $rankingManaged ? '' : 'drag-handle' }}">
                            <i class="ti {{ $rankingManaged ? 'ti-lock text-muted' : 'ti-grip-vertical text-muted' }}"></i>
                          </td>

                          <td>
                            <span class="badge bg-label-primary">{{ $rank }}</span>
                          </td>

                          <td class="{{ $profile ? 'table-success' : 'table-light' }}">
                            {{ $profile?->name }} {{ $profile?->surname }}
                          </td>

                          @if($team->noProfile)
                            <td class="{{ $noProfile ? 'table-warning' : 'table-light' }}">
                              {{ $noProfile?->name }} {{ $noProfile?->surname }}
                            </td>
                          @endif

                          <td>{{ $profile?->email ?? $noProfile?->email ?? '—' }}</td>
                          <td>{{ $profile?->cellNr ?? $noProfile?->cell_nr ?? '—' }}</td>

                          <td class="payStatus">
                            <span class="badge {{ $payStatus ? 'bg-label-success' : 'bg-label-danger' }}">
                              {{ $payStatus ? 'Paid' : 'Not Paid' }}
                            </span>
                          </td>

                          @if($teamWorkspaceRegional)
                            <td>
                              @if($orderedInvitation)
                                <div class="d-flex gap-1">
                                  <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $orderedInvitation->import_id, $orderedInvitation]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button class="btn btn-sm btn-outline-primary" title="Move up" @disabled($orderedInvitations->first()?->id === $orderedInvitation->id)><i class="ti ti-arrow-up"></i></button>
                                  </form>
                                  <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $orderedInvitation->import_id, $orderedInvitation]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button class="btn btn-sm btn-outline-primary" title="Move down" @disabled($orderedInvitations->last()?->id === $orderedInvitation->id)><i class="ti ti-arrow-down"></i></button>
                                  </form>
                                </div>
                              @else
                                <span class="text-muted">—</span>
                              @endif
                            </td>
                          @endif

                        </tr>
                      @endfor

                    </tbody>
                  </table>
                </div>
                @if($rankingManaged)
                  @if($teamWorkspaceRegional)
                    <div class="alert alert-info py-2 small mt-2 mb-0">Change the playing order without changing the selected players, their payment state, or the original ranking snapshot. Every move is audited.</div>
                  @else
                    <div class="alert alert-info py-2 small mt-2 mb-0">This order is controlled by the ranking selection. Use <a href="{{ route('backend.team-selection.index', $event) }}" class="alert-link">Team Selection & Reserves</a> to replace an unpaid player with the next reserve.</div>
                  @endif
                @endif
              </div>

            @empty
              <div class="alert alert-light text-center">No teams found</div>
            @endforelse

          </div>
        </div>
      </div>
    @endforeach

  </div>
</div>

{{-- 🔹 GLOBAL DEPENDENCIES --}}
<script>
  window.APP_URL = "{{ url('/') }}";
</script>


