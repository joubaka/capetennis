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

      

        <a href="{{ route('admin.events.transactions', $event) }}"
           class="btn btn-outline-success">
          <i class="ti ti-credit-card me-1"></i>
          Team Payments
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

       

        <a href="{{ route('admin.events.announcements', $event) }}"
           class="btn btn-outline-warning">
          <i class="ti ti-megaphone me-1"></i>
          Team Announcements
        </a>

      </div>
    </div>
  </div>

  {{-- TEAM STATS --}}
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
      <ul class="list-unstyled mb-0 d-grid gap-1">

        {{-- Regions --}}
        @if($event->isTeam())
        <li>
          Regions
          <span class="fw-semibold float-end">
            {{ $event->regions->count() }}
          </span>
        </li>
        @endif

        {{-- Teams --}}
        @if($event->isTeam())
        <li>
          Teams
          <span class="fw-semibold float-end">
            {{ $event->regions->sum(fn ($r) => $r->teams->count()) }}
          </span>
        </li>
        @endif

        {{-- Players --}}
        <li>
          Players
          <span class="fw-semibold float-end">
            {{ $event->isTeam() ? $stats['players'] : $stats['entries'] }}
          </span>
        </li>

        {{-- Matches --}}
        <li>
          Matches
          <span class="fw-semibold float-end">
            {{ $stats['matchesPlayed'] }} / {{ $stats['matchesTotal'] }}
          </span>
        </li>

      </ul>
    </div>
  </div>
</div>

</div>
