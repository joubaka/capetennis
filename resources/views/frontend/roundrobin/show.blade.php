@extends('layouts/contentNavbarLayout')

@section('title', 'Round Robins — ' . ($draw->name ?? 'Draw'))

@section('content')
<style>
  /* ==============================================
     BASE TABLE STYLE
     ============================================== */
  .rr-matrix-table {
    border-collapse: collapse !important;
    table-layout: fixed !important;
    background: #ffffff !important;
    width: max-content !important;
  }

  /* Scroll wrapper */
  .rr-matrix-scroll {
    overflow-x: auto !important;
    overflow-y: hidden;
    width: 100%;
    padding-bottom: 5px;
    -webkit-overflow-scrolling: touch;
    position: relative;
  }

  .rr-matrix-scroll::after {
    content: "";
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 20px;
    background: linear-gradient(to right, transparent, rgba(0,0,0,0.15));
    pointer-events: none;
  }

  /* ==============================================
     SMALLER CELLS (new compact mode)
     ============================================== */
  .rr-matrix-table td,
  .rr-matrix-table td.rr-score-cell {
    padding: 0 !important;
    height: 26px !important;
    width: 26px !important;
    min-width: 26px !important;
    max-width: 26px !important;
    text-align: center;
    vertical-align: middle;
    border: 1px solid #dcdcdc !important;
    font-size: 11px !important;
    background: #ffffff !important;
  }

  /* Diagonal black */
  .rr-matrix-table td.bg-light {
    background: #000 !important;
    border: 1px solid #fff !important;
  }

  /* ==============================================
     HEADER STYLE (smaller)
     ============================================== */
  .rr-matrix-table thead th {
    padding: 4px 6px !important;
    background: #0a3566 !important;
    color: #fff !important;
    font-weight: 600;
    font-size: 11px !important;
    white-space: nowrap !important;
    width: 140px !important; /* reduced from 200 */
  }

  /* ==============================================
     LEFT PLAYER NAMES (smaller)
     ============================================== */
  .rr-matrix-table tbody th {
    background: #0b722e !important;
    color: #fff !important;
    font-weight: 600;
    font-size: 11px !important;
    padding: 4px 6px !important;
    white-space: nowrap !important;
  }

  /* ==============================================
     SCORE COLORS
     ============================================== */
  .rr-matrix-table .rr-win {
    color: #00a859 !important;
    font-weight: bold;
  }

  .rr-matrix-table .rr-loss {
    color: #d32f2f !important;
    font-weight: bold;
  }

  /* ==============================================
     MOBILE IMPROVEMENTS
     ============================================== */
  @media (max-width: 576px) {

    /* tab buttons smaller on mobile */
    #rrTabs {
      flex-wrap: wrap;
      gap: 6px;
    }
    #rrTabs .nav-link {
      font-size: 12px;
      padding: 6px 10px;
    }

    /* shrink name column further */
    .rr-matrix-table thead th {
      font-size: 10px !important;
      width: 120px !important;
    }
    .rr-matrix-table tbody th {
      font-size: 10px !important;
    }

    /* slightly smaller cells on mobile */
    .rr-matrix-table td,
    .rr-matrix-table td.rr-score-cell {
      width: 24px !important;
      min-width: 24px !important;
      height: 24px !important;
      font-size: 10px !important;
    }

    /* auto-scale table for better fit */
    .rr-matrix-scroll {
      zoom: 0.85;
    }

    /* OOP table mobile */
    #rr-order-table {
      font-size: 12px;
    }
    #rr-order-table thead th {
      font-size: 11px;
      white-space: nowrap;
    }
    #rr-order-table tbody td {
      white-space: nowrap;
    }

    #oop-pane .card-body {
      overflow-x: auto;
    }

    /* Standings mobile */
    #rr-standings-wrapper table {
      font-size: 12px;
    }
    #rr-standings-wrapper th,
    #rr-standings-wrapper td {
      white-space: nowrap;
      padding: 4px 6px !important;
    }
  }

  /* ==============================================
     BRACKET WRAPPERS
     ============================================== */
  #main-bracket-wrapper,
  #plate-bracket-wrapper,
  #cons-bracket-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

</style>


<div id="round-robin-app" 
     data-draw-id="{{ $draw->id }}">

  <div class="col-12 mb-4">
    <h4 class="mb-0">
      🎾 Round Robin — {{ $draw->name }}
    </h4>
    <small class="text-muted">
      {{ $draw->category->name ?? '' }} @ {{ $draw->event->name ?? '' }}
    </small>
  </div>

  {{-- ============================
       TAB NAVIGATION
     ============================ --}}
 <ul class="nav nav-tabs mb-3" id="rrTabs" role="tablist">

  <li class="nav-item" role="presentation">
    <button class="nav-link "
            id="matrix-tab"
            data-bs-toggle="tab"
            data-bs-target="#matrix-pane"
            type="button" role="tab">
      Boxes
    </button>
  </li>

  <li class="nav-item" role="presentation">
    <button class="nav-link active"
            id="oop-tab"
            data-bs-toggle="tab"
            data-bs-target="#oop-pane"
            type="button" role="tab">
      Order of Play
    </button>
  </li>

 

  <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="standings-tab"
            data-bs-toggle="tab"
            data-bs-target="#standings-pane"
            type="button" role="tab">
      Standings
    </button>
  </li>
   <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="main-bracket-tab"
            data-bs-toggle="tab"
            data-bs-target="#main-bracket-pane"
            type="button"
            role="tab">
      Main Bracket
    </button>
</li>


</ul>

  {{-- ============================
       TAB CONTENT
     ============================ --}}
  <div class="tab-content" id="rrTabsContent">

    {{-- ============================
         TAB 1 — MATRIX + STANDINGS
       ============================ --}}
    <div class="tab-pane fade show" 
         id="matrix-pane" 
         role="tabpanel">
      <div class="row"> 
        <div class="col-12">
        <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Round Robin Matrix</h5>
          <small class="text-muted">Who plays who + results</small>
        </div>
        <div class="card-body p-0">
        <div id="rr-matrix-wrapper" class="rr-all-boxes-scroll p-2">

            <div class="text-center text-muted py-5" id="rr-matrix-loading">
              <div class="spinner-border spinner-border-sm"></div>
              <div class="mt-2">Loading round-robin grid…</div>
            </div>
          </div>
        </div>
      </div></div>
        
      </div>
    

    

    </div>

    {{-- ============================
         TAB 2 — ORDER OF PLAY
       ============================ --}}
    <div class="tab-pane fade show active" 
         id="oop-pane" 
         role="tabpanel">
       <div class="col-12">
            <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Order of Play</h5>
       
        </div>
        <div class="card-body p-0">
         <table class="table table-sm table-hover mb-0" id="rr-order-table">
    <thead class="table-light">
        <tr>
            <th>ID</th>
            <th>Player 1</th>
            <th class="text-center">VS</th>
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
     
    </div>

    {{-- ============================
         TAB 3 — SCORES
       ============================ --}}
    <div class="tab-pane fade" 
         id="scores-pane" 
         role="tabpanel">

      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Scores</h5>
          <small class="text-muted">Select match from OOP tab</small>
        </div>

        <div class="card-body">
          <form id="rr-score-form">
            @csrf
            <input type="hidden" name="fixture_id" id="rr-score-fixture-id">

            <div class="mb-2">
              <label class="form-label">Match</label>
              <div id="rr-score-match-label" class="fw-bold small text-muted">
                Select a match from Order of Play…
              </div>
            </div>

            <div class="row g-2 align-items-center">
              <div class="col-5">
                <label class="form-label small mb-1">Home score</label>
                <input type="text" class="form-control form-control-sm"
                       name="home_score" id="rr-home-score"
                       placeholder="6-4 6-3">
              </div>
              <div class="col-5">
                <label class="form-label small mb-1">Away score</label>
                <input type="text" class="form-control form-control-sm"
                       name="away_score" id="rr-away-score"
                       placeholder="4-6 3-6">
              </div>
              <div class="col-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-success w-100">
                  <i class="ti ti-device-floppy"></i>
                </button>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
  {{-- ============================
     TAB 4 — STANDINGS
   ============================ --}}
<div class="tab-pane fade" id="standings-pane" role="tabpanel">

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Standings</h5>
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





   <!-- =========================================
     Brackets
========================================= -->

  <div class="tab-pane fade" id="main-bracket-pane" role="tabpanel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Main Bracket</h5>
      
    </div>

    <div id="main-bracket-wrapper" class="mt-2">
        <div class="text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm"></div>
            <div>Loading…</div>
        </div>
    </div>
   
</div>

  </div> {{-- END TABS --}}
</div> {{-- END APP --}}
<!-- =========================================
      SCORE ENTRY MODAL
========================================= -->
<div class="modal fade" id="rrScoreModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="rr-score-modal-form" class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="rrm-match-label">Enter Score</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="rrm-fixture-id">

        <label class="form-label fw-bold mb-2">Set Scores</label>

        <!-- SET 1 -->
        <div class="row g-2 mb-2">
          <div class="col-12 fw-bold">Set 1</div>
          <div class="col-6">
            <label class="form-label"><span id="set1-p1-label">Player 1</span></label>
            <input type="number" min="0" class="form-control" id="set1-p1">
          </div>
          <div class="col-6">
            <label class="form-label"><span id="set1-p2-label">Player 2</span></label>
            <input type="number" min="0" class="form-control" id="set1-p2">
          </div>
        </div>

        <!-- SET 2 -->
        <div class="row g-2 mb-2">
          <div class="col-12 fw-bold">Set 2</div>
          <div class="col-6">
            <label class="form-label"><span id="set2-p1-label">Player 1</span></label>
            <input type="number" min="0" class="form-control" id="set2-p1">
          </div>
          <div class="col-6">
            <label class="form-label"><span id="set2-p2-label">Player 2</span></label>
            <input type="number" min="0" class="form-control" id="set2-p2">
          </div>
        </div>

        <!-- SET 3 -->
        <div class="row g-2 mb-2">
          <div class="col-12 fw-bold">Set 3</div>
          <div class="col-6">
            <label class="form-label"><span id="set3-p1-label">Player 1</span></label>
            <input type="number" min="0" class="form-control" id="set3-p1">
          </div>
          <div class="col-6">
            <label class="form-label"><span id="set3-p2-label">Player 2</span></label>
            <input type="number" min="0" class="form-control" id="set3-p2">
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Score</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
      </div>

    </form>
  </div>
</div>




@endsection



@section('page-script')

<script>
    window.RR_FIXTURES  = @json($rrFixtures);
    window.RR_GROUPS    = @json($groupsJson);   // THE ONLY CORRECT ONE
    window.RR_OOP       = @json($oops);
    window.RR_STANDINGS = @json($standings);

    window.RR_SAVE_SCORE_URL = "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}";

    window.EVENT_ID = {{ $draw->event_id }};
    const DRAW_ID   = {{ $draw->id }};
</script>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<!-- ADD THIS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).on('click', '#btn-import-teams', function () {
    const url = `${APP_URL}/backend/event/${EVENT_ID}/import-teams`;

    Swal.fire({
        title: 'Import Teams?',
        text: 'This will create categories and registrations for all teams.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, import'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post(url, {}, function (response) {
            toastr.success(response.message);
            location.reload();
        }).fail(function () {
            toastr.error('Import failed.');
        });
    });
});
</script>

<script src="{{ asset('assets/js/draw-roundrobin.js') }}?v={{ time() }}"></script>


@endsection

