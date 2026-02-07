@extends('layouts/contentNavbarLayout')

@section('title', 'Round Robin — ' . ($draw->name ?? 'Draw'))

@section('content')
<style>
  .rr-matrix-table {
    border-collapse: collapse !important;
    table-layout: fixed !important;
    background: #ffffff !important;
    width: max-content !important;
  }

  .rr-matrix-scroll {
    overflow-x: auto;
    width: 100%;
    padding-bottom: 5px;
  }

  .rr-matrix-table td,
  .rr-matrix-table td.rr-score-cell {
    padding: 0 !important;
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    max-width: 32px !important;
    text-align: center;
    vertical-align: middle;
    border: 1px solid #dcdcdc !important;
    font-size: 12px !important;
    background: #ffffff !important;
  }

  .rr-matrix-table td.bg-light {
    background: #000 !important;
    border: 1px solid #fff !important;
  }

  .rr-matrix-table thead th {
    padding: 6px 10px !important;
    background: #0a3566 !important;
    color: white !important;
    white-space: nowrap !important;
    width: 200px;
    font-size: 12px;
  }

  .rr-matrix-table tbody th {
    background: #0b722e !important;
    color: #fff !important;
    padding: 6px 12px !important;
    font-size: 13px !important;
    white-space: nowrap !important;
  }

  .rr-win { color: #00a859 !important; font-weight: bold; }
  .rr-loss { color: #d32f2f !important; font-weight: bold; }

  .card-title { margin-bottom: 0; }
</style>

<div class="container py-4" id="round-robin-app"
     data-draw-id="{{ $draw->id }}">

  <!-- HEADER -->
  <div class="mb-4">
    <h3 class="mb-0">🎾 Round Robin — {{ $draw->name }}</h3>
    <small class="text-muted">
      {{ $draw->category->name ?? '' }} @ {{ $draw->event->name ?? '' }}
    </small>
  </div>

  <!-- TABS -->
  <ul class="nav nav-tabs mb-3" id="rrTabs" role="tablist">

    <li class="nav-item">
      <button class="nav-link active"
              data-bs-toggle="tab"
              data-bs-target="#matrix-pane">
        Matrix
      </button>
    </li>

    <li class="nav-item">
      <button class="nav-link"
              data-bs-toggle="tab"
              data-bs-target="#oop-pane">
        Order of Play
      </button>
    </li>

    <li class="nav-item">
      <button class="nav-link"
              data-bs-toggle="tab"
              data-bs-target="#standings-pane">
        Standings
      </button>
    </li>

  </ul>

  <div class="tab-content">

    <!-- ==================== MATRIX ==================== -->
    <div class="tab-pane fade show active" id="matrix-pane">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title">Round Robin Matrix</h5>
        </div>

        <div class="card-body p-0">
          <div id="rr-matrix-wrapper" class="p-2">
            <div id="rr-matrix-loading" class="text-center text-muted py-5">
              <div class="spinner-border spinner-border-sm"></div>
              <div class="mt-2">Loading matrix…</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== ORDER OF PLAY ==================== -->
    <div class="tab-pane fade" id="oop-pane">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title">Order of Play</h5>
        </div>

        <div class="card-body p-0">
          <table class="table table-sm table-hover mb-0" id="rr-order-table">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Player 1</th>
                <th class="text-center">vs</th>
                <th>Player 2</th>
                <th class="text-center">Round</th>
                <th class="text-center">Time</th>
                <th class="text-center">Score</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== STANDINGS ==================== -->
    <div class="tab-pane fade" id="standings-pane">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title">Standings</h5>
        </div>

        <div class="card-body">
          <div id="rr-standings-wrapper">
            <div class="text-center text-muted py-4" id="rr-standings-loading">
              <div class="spinner-border spinner-border-sm"></div>
              <div class="mt-2">Loading standings…</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->

</div><!-- /main-container -->

@endsection

@section('page-script')
<script>
    window.RR_FIXTURES  = @json($rrFixtures);
    window.RR_GROUPS    = @json($groupsJson);
    window.RR_OOP       = @json($oops);
    window.RR_STANDINGS = @json($standings);
    const DRAW_ID = {{ $draw->id }};
</script>


<script src="{{ asset('assets/js/draw-roundrobin1.js') }}"></script>
@endsection
