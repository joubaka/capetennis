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

      @can('superUser')
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
      @endcan
    </ul>

    {{-- ================= TAB CONTENT ================= --}}
    <div class="tab-content">

      {{-- EVENTS --}}
      <div class="tab-pane fade show active" id="tab-events" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">Event List</h5>
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

    </div>
  </div>
</div>
