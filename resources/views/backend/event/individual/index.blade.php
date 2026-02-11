<div class="row g-3">

  {{-- EVENT MANAGEMENT --}}
  <div class="col-xl-4 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-settings ti-md text-primary"></i>
        <h5 class="mb-0">Event Management</h5>
      </div>

      <div class="card-body d-grid gap-2">
        <a href="{{ route('admin.events.entries.new', $event) }}"
           class="btn btn-primary">
          <i class="ti ti-users me-1"></i>
          Manage Categories & Entries
        </a>

        <a href="{{ route('admin.events.transactions', $event) }}"
           class="btn btn-outline-secondary">
          <i class="ti ti-credit-card me-1"></i>
          Transactions
        </a>

        <a href="{{ route('admin.events.results.individual', $event) }}"
           class="btn btn-outline-success">
          <i class="ti ti-trophy me-1"></i>
          Results
        </a>

        {{-- 🔹 SERIES LINK (ONLY IF EVENT BELONGS TO SERIES) --}}
       @if($event->series)
  <a href="{{ route('series.show', $event->series) }}"
     class="btn btn-outline-info">
    <i class="ti ti-layers me-1"></i>
    {{ $event->series->name }}
  </a>
@endif

      </div>
    </div>
  </div>

  {{-- EVENT SETUP --}}
  <div class="col-xl-4 col-md-6">
    <div class="card h-100 border-start border-warning border-3">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-adjustments ti-md text-warning"></i>
        <h5 class="mb-0">Event Setup</h5>
      </div>

      <div class="card-body d-grid gap-2">
        <a href="{{ route('admin.events.settings', $event) }}"
           class="btn btn-outline-warning">
          <i class="ti ti-sliders me-1"></i>
          Event Settings
        </a>

        <a href="{{ route('admin.events.categories', $event) }}"
           class="btn btn-outline-primary">
          <i class="ti ti-list-details me-1"></i>
          Manage Categories
        </a>

        <a href="{{ route('admin.events.announcements', $event) }}"
           class="btn btn-outline-info">
          <i class="ti ti-megaphone me-1"></i>
          Announcements
        </a>
      </div>
    </div>
  </div>

  {{-- QUICK STATS --}}
  <div class="col-xl-4 col-md-12">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-chart-bar ti-md text-info"></i>
        <h5 class="mb-0">Quick Stats</h5>
      </div>

      <div class="card-body">
        <ul class="list-unstyled mb-0 d-grid gap-1">
          <li>
            Categories:
            <span class="fw-semibold float-end">{{ $stats['categories'] }}</span>
          </li>
          <li>
            Entries:
            <span class="fw-semibold float-end">{{ $stats['entries'] }}</span>
          </li>
          <li>
            Matches:
            <span class="fw-semibold float-end">
              {{ $stats['matchesPlayed'] }} / {{ $stats['matchesTotal'] }}
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>

</div>
