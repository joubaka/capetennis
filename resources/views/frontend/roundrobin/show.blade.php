@extends('layouts/contentNavbarLayout')

@section('title', ($draw->isRoundRobinOnly() ? 'Round Robin' : 'Round Robin & Playoffs') . ' — ' . ($draw->drawName ?? 'Draw'))

@section('content')
<link rel="stylesheet" href="{{ asset('css/public-draw.css') }}?v={{ filemtime(public_path('css/public-draw.css')) }}">
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

  /* Keep the player-facing schedule readable and make start times easy to scan. */
  #oop-pane .card-body { overflow-x: auto; }
  #rr-order-table { min-width: 1050px; }
  #rr-order-table th,
  #rr-order-table td { vertical-align: middle; }
  #rr-order-table td[data-label="Player 1"],
  #rr-order-table td[data-label="Player 2"] { font-weight: 650; }
  #rr-order-table td[data-label="Date"],
  #rr-order-table td[data-label="Time"] { white-space: nowrap; }
  #rr-order-table td[data-label="Time"] {
    color: #0d675e;
    font-size: 1rem;
    font-weight: 800;
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

    /* OOP table mobile */
    #rr-order-table { font-size: 12px; }
    #rr-order-table thead th {
      font-size: 11px;
      white-space: nowrap;
    }
    #rr-order-table tbody td {
      white-space: nowrap;
    }

    #rr-order-table td[data-label="Date"],
    #rr-order-table td[data-label="Time"],
    #rr-order-table td[data-label="Court"] {
      color: #176448;
      font-weight: 700;
    }

    #rr-order-table,
    #rr-order-table tbody,
    #rr-order-table tr,
    #rr-order-table td { display: block; width: 100%; }
    #rr-order-table { min-width: 0; }
    #rr-order-table thead { display: none; }
    #rr-order-table tbody { padding: .75rem; }
    #rr-order-table tr {
      margin-bottom: .75rem;
      padding: .75rem;
      border: 1px solid #dbe5ec;
      border-radius: .65rem;
      background: #fff;
      box-shadow: 0 .2rem .75rem rgba(23,46,69,.06);
    }
    #rr-order-table td {
      display: grid;
      grid-template-columns: 5.5rem minmax(0,1fr);
      gap: .5rem;
      padding: .25rem 0 !important;
      border: 0;
      text-align: left !important;
      white-space: normal;
    }
    #rr-order-table td::before {
      content: attr(data-label);
      color: #6b7d8f;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
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
  .bracket-zoom-outer {
    position: relative;
  }
  .bracket-zoom-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-x pan-y pinch-zoom;
    padding: 8px;
  }
  .bracket-zoom-inner {
    transform-origin: top left;
    display: inline-block;
    min-width: 100%;
  }
  .bracket-zoom-inner svg {
    display: block;
  }

</style>


<div id="round-robin-app" 
     data-draw-id="{{ $draw->id }}">

  @include('frontend.draw.partials.public-header')

  {{-- ============================
       TAB NAVIGATION
     ============================ --}}
 <ul class="nav nav-tabs ct-public-draw-nav" id="rrTabs" role="tablist">

  <li class="nav-item" role="presentation">
    <button class="nav-link active"
            id="oop-tab"
            data-bs-toggle="tab"
            data-bs-target="#oop-pane"
            type="button" role="tab" aria-controls="oop-pane" aria-selected="true">
      Match times
    </button>
  </li>

  <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="matrix-tab"
            data-bs-toggle="tab"
            data-bs-target="#matrix-pane"
            type="button" role="tab" aria-controls="matrix-pane" aria-selected="false">
      Draw
    </button>
  </li>

  <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="standings-tab"
            data-bs-toggle="tab"
            data-bs-target="#standings-pane"
            type="button" role="tab" aria-controls="standings-pane" aria-selected="false">
      Standings
    </button>
  </li>
  @unless($draw->isRoundRobinOnly())
   <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="main-bracket-tab"
            data-bs-toggle="tab"
            data-bs-target="#main-bracket-pane"
            type="button"
            role="tab" aria-controls="main-bracket-pane" aria-selected="false">
      Playoffs
    </button>
</li>
  @endunless


</ul>

  {{-- ============================
       TAB CONTENT
     ============================ --}}
  <div class="tab-content" id="rrTabsContent">

    {{-- ============================
         TAB 1 — MATRIX + STANDINGS
       ============================ --}}
    <div class="tab-pane fade"
         id="matrix-pane" 
         role="tabpanel" aria-labelledby="matrix-tab">
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
         role="tabpanel" aria-labelledby="oop-tab">
       <div class="col-12">
            <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-1">Match times &amp; courts</h5>
            <div class="small text-muted">Find your name, then confirm the date, time, venue and court.</div>
          </div>
       
        </div>
        <div class="card-body p-0">
        @if($draw->oop_published)
         <table class="table table-sm table-hover mb-0" id="rr-order-table">
    <caption class="visually-hidden">Player match dates, start times, venues, courts and scores</caption>
    <thead class="table-light">
        <tr>
            <th class="text-center">Match</th>
            <th>Player 1</th>
            <th>Player 2</th>
            <th class="text-center">Round</th>
            <th class="text-center">Stage</th>
            <th class="text-center">Date</th>
            <th class="text-center">Time</th>
            <th>Venue</th>
            <th class="text-center">Court</th>
            <th class="text-center">Score</th>
           
        </tr>
    </thead>
    <tbody></tbody>
</table>
        @else
          <div class="alert alert-info rounded-0 mb-0 py-4 text-center" role="status">
            <div class="fw-semibold">Match times and venues have not been published yet.</div>
            <div class="small">The draw is available now; return here after the organiser releases the schedule.</div>
          </div>
        @endif
        </div>
      </div>
        </div>
     
    </div>

  {{-- ============================
     TAB 4 — STANDINGS
   ============================ --}}
<div class="tab-pane fade" id="standings-pane" role="tabpanel" aria-labelledby="standings-tab">

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





  @unless($draw->isRoundRobinOnly())
   <!-- =========================================
     Brackets
========================================= -->

  <div class="tab-pane fade" id="main-bracket-pane" role="tabpanel" aria-labelledby="main-bracket-tab">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <h5 class="card-title mb-0">Main Bracket</h5>
        <div class="bracket-zoom-controls d-flex gap-2 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary btn-bracket-zoom" data-dir="out" style="width:30px;height:30px;padding:0;">−</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-bracket-zoom" data-dir="in" style="width:30px;height:30px;padding:0;">+</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-bracket-zoom" data-dir="fit" style="font-size:11px;padding:0 10px;height:30px;">Fit</button>
          <small class="text-muted bracket-zoom-label">100%</small>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="bracket-zoom-outer">
          <div class="bracket-zoom-scroll" style="background:#f8fafc;border-radius:0 0 6px 6px;">
            <div class="bracket-zoom-inner" id="main-bracket-wrapper">
              <div class="text-center text-muted py-5">
                <div class="spinner-border spinner-border-sm"></div>
                <div>Loading…</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endunless

  </div> {{-- END TABS --}}
</div> {{-- END APP --}}
@endsection



@section('page-script')

<script>
    window.RR_FIXTURES  = @json($rrFixtures);
    window.RR_GROUPS    = @json($groupsJson);   // THE ONLY CORRECT ONE
    window.RR_OOP       = @json($oops);
    window.RR_STANDINGS = @json($standings);

    window.RR_MAIN_BRACKET_URL = "{{ route('public.roundrobin.main-bracket', $draw) }}";
</script>
<script src="{{ asset('assets/js/roundrobin-public.js') }}?v={{ filemtime(public_path('assets/js/roundrobin-public.js')) }}"></script>

<script>
(function(){
  var zoom = 1;
  var minZ = 0.3;
  var maxZ = 1.5;
  var step = 0.15;

  function applyZoom(outer) {
    var inner = outer.querySelector('.bracket-zoom-inner');
    var label = outer.querySelector('.bracket-zoom-label');
    if (inner) inner.style.zoom = zoom;
    if (label) label.textContent = Math.round(zoom * 100) + '%';
  }

  function fitZoom(outer) {
    var scroll = outer.querySelector('.bracket-zoom-scroll');
    var inner  = outer.querySelector('.bracket-zoom-inner');
    var svg    = inner ? inner.querySelector('svg') : null;
    if (!svg || !scroll) return;
    var vb = svg.getAttribute('viewBox');
    if (!vb) return;
    var parts = vb.split(/[\s,]+/).map(Number);
    var svgW = parts[2] || svg.getBoundingClientRect().width;
    if (svgW <= 0) return;
    zoom = Math.max(minZ, Math.min(maxZ, scroll.clientWidth / svgW));
    applyZoom(outer);
  }

  $(document).on('click', '.btn-bracket-zoom', function(){
    var dir = $(this).data('dir');
    var outer = $(this).closest('.bracket-zoom-outer')[0];
    if (dir === 'in')  zoom = Math.min(maxZ, zoom + step);
    if (dir === 'out') zoom = Math.max(minZ, zoom - step);
    if (dir === 'fit') { fitZoom(outer); return; }
    applyZoom(outer);
  });

  // Auto-fit on mobile after bracket loads
  var observer = new MutationObserver(function(mutations){
    mutations.forEach(function(m){
      if (m.addedNodes.length) {
        var outer = m.target.closest('.bracket-zoom-outer');
        if (outer && m.target.querySelector('svg')) {
          zoom = 1;
          setTimeout(function(){ applyZoom(outer); }, 50);
        }
      }
    });
  });
  document.querySelectorAll('.bracket-zoom-inner').forEach(function(el){
    observer.observe(el, { childList: true, subtree: true });
  });
})();
</script>

@endsection

