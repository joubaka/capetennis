<div class="row g-3">

  {{-- CONTEXTUAL MUTATION — navigation lives in the shared event header. --}}
  <div class="col-xl-4 col-md-6">
    <div class="card h-100 border-start border-info border-3">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-adjustments ti-md text-info"></i>
        <h5 class="mb-0">Team Setup</h5>
      </div>

      <div class="card-body d-grid gap-2">

        <button type="button"
                class="btn btn-outline-success"
                id="sync-team-categories-btn"
                data-url="{{ url('/backend/event/' . $event->id . '/import-teams') }}">
          <i class="ti ti-upload me-1"></i>
          Sync Categories from Teams
        </button>

      </div>
    </div>
  </div>

  {{-- TEAM STATS --}}
  <div class="col-xl-8 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-chart-pie ti-md text-success"></i>
        <h5 class="mb-0">
          {{ $event->isTeam() ? 'Team Stats' : 'Event Stats' }}
        </h5>
      </div>

      <div class="card-body">
        <ul class="list-unstyled mb-0 d-grid gap-2">

          {{-- Categories --}}
          <li class="d-flex justify-content-between">
            <span>Categories</span>
            <span class="badge bg-label-info rounded-pill">
              {{ $stats['categories'] }}
            </span>
          </li>

          {{-- Regions --}}
          @if($event->isTeam())
            <li class="d-flex justify-content-between">
              <span>Regions</span>
              <span class="badge bg-label-primary rounded-pill">
                {{ $event->regions->count() }}
              </span>
            </li>
          @endif

          {{-- Teams --}}
          @if($event->isTeam())
            <li class="d-flex justify-content-between">
              <span>Teams</span>
              <span class="badge bg-label-primary rounded-pill">
                {{ $event->regions->sum(fn ($r) => $r->teams->count()) }}
              </span>
            </li>
          @endif

          {{-- Players --}}
          <li class="d-flex justify-content-between">
            <span>Players</span>
            <span class="badge bg-label-success rounded-pill">
              {{ $event->isTeam() ? $stats['players'] : $stats['entries'] }}
            </span>
          </li>

          {{-- Draws Locked --}}
          <li class="d-flex justify-content-between">
            <span>Draws Locked</span>
            <span class="badge bg-label-warning rounded-pill">
              {{ $stats['drawsLocked'] }}
            </span>
          </li>

          {{-- Matches Progress --}}
          <li>
            <div class="d-flex justify-content-between mb-1">
              <small>Matches</small>
              <small class="text-muted">
                {{ $stats['matchesPlayed'] }} / {{ $stats['matchesTotal'] }}
              </small>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar bg-success"
                   role="progressbar"
                   style="width: {{ $stats['matchesTotal'] > 0 ? round(($stats['matchesPlayed'] / $stats['matchesTotal']) * 100) : 0 }}%"
                   aria-valuenow="{{ $stats['matchesPlayed'] }}"
                   aria-valuemin="0"
                   aria-valuemax="{{ $stats['matchesTotal'] }}">
              </div>
            </div>
          </li>

        </ul>
      </div>
    </div>
  </div>

</div>

<script>
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#sync-team-categories-btn');
    if (!btn) return;

    const url = btn.getAttribute('data-url');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!url || !token) return;

    if (!confirm('Sync categories from teams for this event?')) return;

    btn.disabled = true;

    fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw data;
        AppFeedback.success(data.message || 'Categories synced successfully.');
      })
      .catch((err) => {
        const msg = err?.message || 'Failed to sync categories.';
        AppFeedback.error(msg);
      })
      .finally(() => {
        btn.disabled = false;
      });
  });
</script>

