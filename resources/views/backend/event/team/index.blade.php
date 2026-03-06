<div class="row g-3">

  {{-- TEAM MANAGEMENT --}}
  <div class="col-xl-4 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-users-group ti-md text-primary"></i>
        <h5 class="mb-0">Team Management</h5>
      </div>

      <div class="card-body d-grid gap-2">

        <a href="{{ route('admin.events.teams', $event) }}"
           class="btn btn-primary">
          <i class="ti ti-users me-1"></i>
          Teams & Regions
        </a>

        {{-- Fixtures HQ (admin) --}}
        <a href="{{ route('headOffice.show', $event) }}"
           class="btn btn-outline-primary">
          <i class="ti ti-calendar-meet me-1"></i>
          Fixtures HQ
        </a>

        <a href="{{ route('admin.events.transactions', $event) }}"
           class="btn btn-outline-success">
          <i class="ti ti-credit-card me-1"></i>
          Team Payments
        </a>

        <a href="{{ route('admin.events.finances', $event) }}"
           class="btn btn-outline-warning">
          <i class="ti ti-report-money me-1"></i>
          Convenor Finances
        </a>

        <a href="{{ route('admin.events.draws', $event) }}"
           class="btn btn-outline-secondary">
          <i class="ti ti-tournament me-1"></i>
          Draws
        </a>

        <a href="{{ route('backend.scoreboard.team.show', $event) }}"
           class="btn btn-outline-secondary">
          <i class="ti ti-trophy me-1"></i>
          Team Scoreboard
        </a>

      </div>
    </div>
  </div>

  {{-- TEAM SETUP --}}
  <div class="col-xl-4 col-md-6">
    <div class="card h-100 border-start border-info border-3">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-adjustments ti-md text-info"></i>
        <h5 class="mb-0">Team Setup</h5>
      </div>

      <div class="card-body d-grid gap-2">

        <a href="{{ route('admin.events.settings', $event) }}"
           class="btn btn-outline-info">
          <i class="ti ti-sliders me-1"></i>
          Event Settings
        </a>

        <a href="{{ route('admin.events.categories', $event) }}"
           class="btn btn-outline-primary">
          <i class="ti ti-list-details me-1"></i>
          Manage Categories
        </a>

        <a href="{{ route('admin.events.announcements', $event) }}"
           class="btn btn-outline-warning">
          <i class="ti ti-megaphone me-1"></i>
          Team Announcements
        </a>

        @if($event->series)
          <a href="{{ route('series.show', $event->series) }}"
             class="btn btn-outline-secondary">
            <i class="ti ti-layers me-1"></i>
            {{ $event->series->name }}
          </a>
        @endif

      </div>
    </div>
  </div>

  {{-- TEAM STATS --}}
  <div class="col-xl-4 col-md-12">
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


