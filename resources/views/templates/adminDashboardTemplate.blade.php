<div class="col-xl-12">

  <div class="nav-align-top mb-4">

    {{-- ================= TABS ================= --}}
    <ul class="nav nav-pills mb-3" role="tablist">
      <li class="nav-item" role="presentation">
        <button
          class="nav-link active"
          data-bs-toggle="tab"
          data-bs-target="#tab-events"
          type="button"
          role="tab"
          aria-selected="true">
          My Events
        </button>
      </li>

      @if($tabs['rankings'] ?? false)
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            data-bs-toggle="tab"
            data-bs-target="#tab-rankings"
            type="button"
            role="tab"
            aria-selected="false">
            Rankings
          </button>
        </li>
      @endif

      @if($tabs['users'] ?? false)
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            data-bs-toggle="tab"
            data-bs-target="#tab-users"
            type="button"
            role="tab"
            aria-selected="false">
            Users
          </button>
        </li>
      @endif

      @if($tabs['players'] ?? false)
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            data-bs-toggle="tab"
            data-bs-target="#tab-players"
            type="button"
            role="tab"
            aria-selected="false">
            Players
          </button>
        </li>
      @endif

      @if($tabs['activity'] ?? false)
        <li class="nav-item" role="presentation">
          <button
            class="nav-link"
            data-bs-toggle="tab"
            data-bs-target="#tab-activity"
            type="button"
            role="tab"
            aria-selected="false">
            <i class="ti ti-history me-1"></i> Activity Log
          </button>
        </li>
      @endif
    </ul>

    {{-- ================= TAB CONTENT ================= --}}
    <div class="tab-content">

      {{-- EVENTS --}}
      <div class="tab-pane fade show active" id="tab-events" role="tabpanel">
        <div class="mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Event List</h5>
            @can('superUser')
              <button class="btn btn-primary btn-sm"
                      data-bs-toggle="modal"
                      data-bs-target="#addEvent">
                <i class="ti ti-plus me-1"></i> Create Event
              </button>
            @endcan
          </div>
          <div class="table-responsive">
            <table class="table datatable-events border-top w-100">
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Start</th>
                  <th>Entry Fee</th>
                  <th>Entries</th>
                  <th>Dashboard</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      {{-- RANKINGS --}}
      <div class="tab-pane fade" id="tab-rankings" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">Series List</h5>
          <div class="table-responsive">
            <table class="table datatable-series border-top w-100">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Series</th>
                  <th>Setup</th>
                  <th>Publish</th>
                  <th>Action</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      {{-- USERS --}}
      <div class="tab-pane fade" id="tab-users" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">User List</h5>
          <div class="table-responsive">
            <table class="table datatable-users border-top w-100">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Action</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      {{-- PLAYERS --}}
      <div class="tab-pane fade" id="tab-players" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">Player List</h5>
          <div class="table-responsive">
            <table class="table datatable-players border-top w-100">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Profile</th>
                  <th>Results</th>
                  <th>Details</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      {{-- ACTIVITY LOG --}}
      @if($tabs['activity'] ?? false)
      <div class="tab-pane fade" id="tab-activity" role="tabpanel">
        <div class="mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ti ti-history me-1"></i> Activity Log</h5>
            <div class="d-flex align-items-center gap-2">
              <label class="small mb-0 me-2">Filter</label>
              <select id="activity-filter-log" class="form-select form-select-sm me-2">
                <option value="">All</option>
                @foreach($logNames as $ln)
                  <option value="{{ $ln }}">{{ $ln }}</option>
                @endforeach
              </select>

              <div class="form-check form-switch me-2">
                <input class="form-check-input" type="checkbox" id="activity-toggle-view" checked>
                <label class="form-check-label small" for="activity-toggle-view">Grouped</label>
              </div>

              <span class="badge bg-label-secondary">Last 50</span>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-striped border-top w-100" id="datatable-activity">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User</th>
                  <th>Log</th>
                  <th>Action</th>
                  <th>Details</th>
                  <th class="d-none">Log Names</th>
                </tr>
              </thead>
              <tbody>
                @foreach($activityByUser as $row)
                  <tr>
                    <td>{{ optional($row->last_at)->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $row->causer?->userName ?? $row->causer?->name ?? 'System' }}</td>
                    <td><span class="badge bg-label-primary">{{ $row->count }}</span></td>
                    <td>{{ $row->example_description ?? '—' }}</td>
                    <td>
                      @if(!empty($row->properties) && count((array)$row->properties))
                        <button class="btn btn-xs btn-outline-secondary"
                                type="button"
                                data-bs-toggle="popover"
                                data-bs-trigger="focus"
                                data-bs-html="true"
                                data-bs-content="@foreach((array)$row->properties as $key => $val)<strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}<br>@endforeach"
                                data-log-names="{{ implode(',', $row->log_names ?? []) }}">
                          <i class="ti ti-info-circle"></i>
                        </button>
                      @else
                        —
                      @endif
                    </td>
                    <td class="d-none log-names">{{ implode(',', $row->log_names ?? []) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            {{-- Raw activity table (hidden by default) --}}
            <table class="table table-hover table-striped border-top w-100 d-none" id="datatable-activity-raw">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User</th>
                  <th>Log</th>
                  <th>Action</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                @foreach($activityLogs as $log)
                  <tr>
                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $log->causer?->userName ?? $log->causer?->name ?? 'System' }}</td>
                    <td>{{ $log->log_name }}</td>
                    <td>{{ $log->description }}</td>
                    <td>
                      @if($log->properties && $log->properties->count())
                        <button class="btn btn-xs btn-outline-secondary"
                                type="button"
                                data-bs-toggle="popover"
                                data-bs-trigger="focus"
                                data-bs-html="true"
                                data-bs-content="@foreach($log->properties as $key => $val)<strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}<br>@endforeach">
                          <i class="ti ti-info-circle"></i>
                        </button>
                      @else
                        —
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      @endif

    </div>
  </div>
</div>
