<style>
  .select2-container {
    z-index: 100000;
  }
  .draggable-player {
    cursor: grab;
    transition: 0.2s;
  }
  .draggable-player:hover {
    background-color: #f8f9fa;
    transform: scale(1.02);
  }
  .sortable-team .list-group-item {
    cursor: grab;
  }
</style>

<div class="card">
  <div class="card-header event-header">
    <h3 class="text-center">{{ $event->name }}</h3>
  </div>

  <div class="row mt-4">
    <div class="card-body m-4">
      <!-- Navigation -->
      <div class="col-lg-12 col-md-12 col-12 mb-md-0 mb-3">
        <div class="d-flex justify-content-between flex-column mb-2 mb-md-0">
          <div class="row">
            <ul class="nav nav-pills mb-3">
              <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#payment">
                  <i class="ti ti-credit-card me-1 ti-sm"></i>
                  <span class="align-middle fw-semibold">Entries</span>
                </button>
              </li>
              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#results">
                  <i class="ti ti-list-details me-1 ti-sm"></i>
                  <span class="align-middle fw-semibold">Results</span>
                </button>
              </li>
              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teams">
                  <i class="ti ti-users me-1 ti-sm"></i>
                  <span class="align-middle fw-semibold">Teams</span>
                </button>
              </li>
              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#transactions">
                  <i class="ti ti-currency-rand me-1 ti-sm"></i>
                  <span class="align-middle fw-semibold">Transactions</span>
                </button>
              </li>

              @auth
                @if (auth()->user()->id == 584)
                  <li class="nav-item">
                    <a href="{{ route('event.admin.main', $event->id) }}" class="nav-link">
                      <i class="ti ti-trophy me-1 ti-sm"></i>
                      <span class="align-middle fw-semibold">Tournament Admin</span>
                    </a>
                  </li>
                @endif
              @endauth
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="col-lg-12 col-md-12 col-12">
      <div class="tab-content py-0">

        {{-- 🟩 Entries --}}
        <div class="tab-pane fade show active" id="payment" role="tabpanel">
          @include('backend.adminPage.admin_show.tabs.entries')
        </div>

        {{-- 🟨 Results --}}
        <div class="tab-pane fade" id="results" role="tabpanel">
          @include('backend.adminPage.admin_show.tabs.results')
        </div>

        {{-- 🟦 Teams --}}
        <div class="tab-pane fade" id="teams" role="tabpanel">
          @include('backend.adminPage.admin_show.tabs.teams')
        </div>

        {{-- 🟧 Transactions --}}
        <div class="tab-pane fade" id="transactions" role="tabpanel">
          @include('backend.adminPage.admin_show.tabs.transactions')
        </div>

        {{-- 🏆 Tournament Admin --}}
        <div class="tab-pane fade" id="tournament-admin" role="tabpanel">
          @include('backend.adminPage.admin_show.tabs.tournament_admin')
        </div>
      </div>
    </div>
  </div>
</div>

@include('backend.adminPage.admin_show.modals.generateDrawOptionsModal')

{{-- ===================================================== --}}
{{-- 🧠 SCRIPT SECTION --}}
{{-- ===================================================== --}}

