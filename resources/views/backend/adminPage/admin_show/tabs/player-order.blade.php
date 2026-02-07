{{-- ✅ Player Order Tab --}}
@php
  $regionsInEvent = $event->regions ?? collect();
@endphp

<style>
  .drag-handle {
    cursor: grab;
  }
  .drag-handle * {
    pointer-events: none;
  }
</style>

<div class="tab-pane fade" id="tab-order">

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
                      </tr>
                    </thead>

                    <tbody class="sortablePlayers" data-team-id="{{ $team->id }}">

                      @for($rank = 1; $rank <= $maxRows; $rank++)
                        @php
                          $profileSlot = $slots->firstWhere('rank', $rank);
                          $profile = $profileSlot?->player;

                          $noProfile = $team->noProfile
                            ? $noProfiles->firstWhere('rank', $rank)
                            : null;

                          $pivotId = $profileSlot?->id ?? $noProfile?->id;
                          $rowType = $profile ? 'profile' : 'no-profile';
                          $payStatus = $profileSlot?->pay_status ?? 0;
                        @endphp

                        <tr
                          class="drag-item"
                          data-playerteamid="{{ $pivotId }}"
                          data-type="{{ $rowType }}">

                          <td class="text-center drag-handle">
                            <i class="ti ti-grip-vertical text-muted"></i>
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
                          <td>{{ $profile?->cellNr ?? $noProfile?->cellNr ?? '—' }}</td>

                          <td class="payStatus">
                            <span class="badge {{ $payStatus ? 'bg-label-success' : 'bg-label-danger' }}">
                              {{ $payStatus ? 'Paid' : 'Not Paid' }}
                            </span>
                          </td>

                        </tr>
                      @endfor

                    </tbody>
                  </table>
                </div>
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

{{-- 🔹 SORTABLE LOGIC --}}
<script>
(function () {
  'use strict';

  if (typeof Sortable === 'undefined') {
    console.error('❌ SortableJS not loaded');
    return;
  }

  const api = {
    order: APP_URL + '/backend/team/orderPlayerList'
  };

  function initPlayerOrderSortables(context) {

    $(context).find('tbody.sortablePlayers').each(function () {
      const $tbody = $(this);

      if ($tbody.data('sortable-init')) return;
      $tbody.data('sortable-init', true);

      new Sortable(this, {
        animation: 150,
        draggable: 'tr.drag-item',
        handle: '.drag-handle',
        forceFallback: true,
        fallbackTolerance: 3,

        onEnd: function () {
          const order = $tbody.find('tr.drag-item').map(function (i) {
            return {
              id: $(this).data('playerteamid'),
              type: $(this).data('type'),
              position: i + 1
            };
          }).get();

          // UI feedback
          $tbody.addClass('opacity-50');

          $.post(api.order, {
            team_id: $tbody.data('team-id'),
            order
          })
          .done(() => {
            toastr.success('Player order saved');
          })
          .fail(() => {
            toastr.error('Failed to save player order');
          })
          .always(() => {
            $tbody.removeClass('opacity-50');
          });
        }
      });
    });
  }

  // 🔹 Init when REGION tab opens
  $(document).on('shown.bs.tab', '[data-bs-toggle="tab"]', function (e) {
    const target = $(e.target).data('bsTarget');
    if (target && target.startsWith('#order-region-')) {
      initPlayerOrderSortables(target);
    }
  });

  // 🔹 Init first visible region on load
  $('.tab-pane[id^="order-region-"].show').each(function () {
    initPlayerOrderSortables('#' + this.id);
  });

})();
</script>

