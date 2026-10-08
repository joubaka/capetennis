@extends(($scoringPrint ?? false) ? 'layouts/layoutMaster' : 'layouts.backend')

@section('title', 'Venue Fixtures')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
<style>
    .winner-home { background-color: rgba(40,167,69,.25)!important; }
    .loser-home { background-color: rgba(220,53,69,.25)!important; }
    .draw-cell { background-color: rgba(255,193,7,.25)!important; }

    .venue-tie-heading th { background: #e5edf5; padding: .9rem; }
    .venue-wave-heading th { background: #f2f5f8; padding: .55rem .9rem; }
    .venue-player { display: block; margin-top: .25rem; white-space: normal; }
    #editScoreModal .modal-dialog { max-width:680px; width:calc(100% - 2rem); margin:1rem auto; }
    #editScoreModal .modal-header, #editScoreModal .modal-body, #editScoreModal .modal-footer { padding:1rem 1.25rem; }
    #editScoreModal .venue-score-sides { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:1rem; }
    #editScoreModal .venue-score-side { min-width:0; padding:.75rem; border:1px solid #d9e2eb; border-radius:.5rem; background:#f4f7fb; }
    #editScoreModal .venue-score-side strong { display:block; overflow-wrap:anywhere; }
    #editScoreModal .venue-score-table { table-layout:fixed; margin-bottom:0; }
    #editScoreModal .venue-score-table th:first-child { width:22%; }
    #editScoreModal .venue-score-table th, #editScoreModal .venue-score-table td { padding:.5rem; }
    #editScoreModal input, #editScoreModal .modal-footer .btn { min-height:44px; font-size:1rem; }
    #editScoreModal input { text-align:center; }
    #editScoreModal .modal-header { gap:1rem; }
    #editScoreModal .modal-header .modal-title { flex:1; }
    #editScoreModal .modal-header .btn-close { position:static; margin:0; transform:none; min-width:44px; min-height:44px; }
    @media(max-width:575px) { #editScoreModal .modal-header, #editScoreModal .modal-body, #editScoreModal .modal-footer { padding:.875rem; } #editScoreModal .venue-score-sides { gap:.5rem; } }
    .fixture-region-badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    /* PDF/Print Optimization */
    @media print {
        @page {
            size: landscape;
            margin: 7mm;
        }
        .d-print-none, .layout-navbar, .layout-menu, .content-footer, .btn-close {
            display: none !important;
        }
        body {
            background-color: #fff !important;
            font-size: 9pt;
        }
        .container-xxl {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
        }
        .card {
            border: 1px solid #eee !important;
            box-shadow: none !important;
        }
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed;
        }
        .venue-print-header { margin-bottom:3mm !important; padding-bottom:2mm !important; }
        .venue-print-header h2 { font-size:12pt; margin:0 !important; }
        .venue-print-header h4 { font-size:10pt; margin:0 !important; }
        .venue-print-header p { font-size:8pt; }
        .table th, .table td { padding:1.2mm 1.5mm !important; font-size:9pt; line-height:1.2; text-transform:none !important; letter-spacing:normal !important; overflow-wrap:anywhere; }
        .table thead th { white-space:nowrap; }
        .table th:first-child { width:6%; }
        .table th:nth-child(3), .table th:nth-child(4) { width:5%; }
        .table th:nth-child(5), .table th:nth-child(6) { width:31%; }
        .table th:nth-child(7) { width:10%; }
        .table th:nth-child(8) { width:12%; }
        .venue-print-repeat { display:none !important; }
        .venue-tie-heading th, .venue-wave-heading th { padding:1.5mm !important; }
        .venue-player { display:inline; margin:0; }
        .venue-player + .venue-player::before { content:' + '; }
        .fixture-region-badge { display:none !important; }
        .venue-print-team { font-weight:600; }
        .venue-print-time { white-space:nowrap; }
        .table-responsive { overflow:visible !important; }
        thead { display:table-header-group; }
        tr { break-inside:avoid; }
        /* Forces colors to show in saved PDF */
        .winner-home { background-color: #d4edda !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .loser-home { background-color: #f8d7da !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .draw-cell { background-color: #fff3cd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        /* Hide Actions Column in PDF */
        thead th:last-child, td.d-print-none {
            display: none !important;
        }

        /* Specifically hide "No Result" spans during print */
        .no-result-print {
            display: none !important;
        }
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
@include('backend.team-fixtures.partials.participant-revision-script')
<script src="{{ asset(mix('js/draw-fixtures-show.js')) }}"></script>
<script>
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.edit-score-btn');
        if (!button) return;
        document.getElementById('venue-score-home-name').textContent = button.getAttribute('data-home') || 'Home player';
        document.getElementById('venue-score-away-name').textContent = button.getAttribute('data-away') || 'Away player';
    }, true);
    document.getElementById('editScoreModal').addEventListener('shown.bs.modal', function () {
        document.getElementById('set1Home').focus();
    });
    function generatePDF() {
        const originalTitle = document.title;
        document.title = @json(($event->name ?? 'Event').'_'.$venue->name.'_Fixtures');
        window.print();
        document.title = originalTitle;
    }
</script>
<script>
    // Delete result handler for venue fixtures (AJAX)
    (function () {
        document.addEventListener('click', function (e) {
            const el = e.target.closest('.delete-result-btn');
            if (!el) return;

            if (!confirm('Delete the result for this fixture?')) return;

            const fixtureId = el.getAttribute('data-id');
            const url = "{{ route('backend.team-fixtures.destroyResult', ':id') }}".replace(':id', fixtureId);

            el.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ _method: 'DELETE' })
            }).then(res => res.json()).then(data => {
                if (!data || !data.success) {
                    AppFeedback.error(data?.message || 'Could not delete the result.');
                    return;
                }

                const resultCol = document.getElementById(`result-col-${fixtureId}`);
                const row = document.getElementById(`row-${fixtureId}`);
                if (resultCol) resultCol.innerHTML = data.html ?? '<span class="text-muted">No result</span>';
                if (row) {
                    row.querySelectorAll('.home-cell, .away-cell').forEach(function (c) {
                        c.classList.remove('winner-home', 'loser-home', 'draw-cell');
                    });
                }
                AppFeedback.success(data.message || 'Result deleted.');
            }).catch(err => {
                console.error('Error deleting result', err);
                AppFeedback.error('Could not delete the result. Please try again.');
            }).finally(() => { el.disabled = false; });
        });
    })();
</script>
@endsection

@section('content')
@unless($scoringPrint ?? false)
@include('backend.event.partials.header', [
    'eventWorkspaceActive' => 'draws',
    'eventWorkspaceIcon' => 'ti-map-pin',
    'eventWorkspaceSubtitle' => $venue->name . ' fixtures',
])
@endunless
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 d-print-none">
    <div>
        <h4 class="mb-1">{{ $venue->name }} fixtures</h4>
        <p class="text-muted mb-0">Review, print, or update the fixtures assigned to this venue.</p>
    </div>
    <div class="btn-group">
        <button class="btn btn-outline-secondary" onclick="window.print();">
            <i class="ti ti-printer me-1"></i> Print
        </button>
        <button class="btn btn-primary" onclick="generatePDF();">
            <i class="ti ti-file-description me-1"></i> Save as PDF
        </button>
    </div>
</div>

<form method="get" action="{{ route(($scoringPrint ?? false) ? 'frontend.scoring.print' : 'headoffice.venue.fixtures', ['event' => $event, 'venue' => $venue]) }}" class="d-flex flex-wrap align-items-end gap-2 mb-3 d-print-none">
    <div style="min-width:0;max-width:100%">
        <label for="venue-schedule-source" class="form-label">Schedule source</label>
        <select name="source" id="venue-schedule-source" class="form-select" style="min-height:44px;max-width:100%">
            <option value="published" @selected($scheduleSource === 'published')>Published schedule</option>
            <option value="working" @selected($scheduleSource === 'working')>Working schedule</option>
        </select>
    </div>
    <div style="min-width:0;max-width:100%">
        <label for="venue-print-day" class="form-label">Day to print</label>
        <select name="date" id="venue-print-day" class="form-select" style="min-height:44px;max-width:100%">
            <option value="" @selected(!$selectedDate)>All days</option>
            @foreach($availableDays as $day)
                <option value="{{ $day }}" @selected($selectedDate === $day)>{{ \Carbon\Carbon::parse($day)->format('l j M Y') }}</option>
            @endforeach
            @if($selectedDate && !$availableDays->contains($selectedDate))
                <option value="{{ $selectedDate }}" selected>{{ \Carbon\Carbon::parse($selectedDate)->format('l j M Y') }} — no scheduled matches</option>
            @endif
        </select>
    </div>
    <button type="submit" class="btn btn-primary" style="min-height:44px">Show day</button>
    @if($selectedDate)<a class="btn btn-outline-secondary" style="min-height:44px" href="{{ route(($scoringPrint ?? false) ? 'frontend.scoring.print' : 'headoffice.venue.fixtures', ['event' => $event, 'venue' => $venue, 'source' => $scheduleSource]) }}">All days</a>@endif
    <span class="text-muted mb-2">{{ $selectedDate ? \Carbon\Carbon::parse($selectedDate)->format('l j M Y') : 'All days' }} · {{ $fixtures->count() }} matches</span>
</form>
<p class="small text-muted">{{ $scheduleSource === 'published' ? 'Published schedule — matches visible to players. Unpublished changes appear in Working schedule.' : 'Working schedule — includes unpublished changes. Times and venues may differ from the published schedule.' }}</p>

<div class="d-none d-print-block mb-4 border-bottom pb-3 venue-print-header">
    <div class="d-flex justify-content-between">
        <div>
            <h2 class="fw-bold mb-1">{{ $event->name ?? 'Tournament Fixtures' }}</h2>
            <h4 class="text-primary">{{ $venue->name }}</h4>
            <p class="mb-0">{{ $selectedDate ? \Carbon\Carbon::parse($selectedDate)->format('l j M Y') : 'All days' }}</p>
        </div>
        <div class="text-end">
            <p class="mb-0">{{ $scheduleSource === 'published' ? 'Published schedule' : 'Working schedule' }} · {{ $fixtures->count() }} matches</p>
            <p class="mb-0"><strong>Generated:</strong> {{ now()->format('d M Y, H:i') }}</p>
        </div>
    </div>
</div>

<div class="container-xxl">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th class="venue-print-repeat">Draw</th>
                        <th>Rd</th>
                        <th>Match</th>
                        <th>Home</th>
                        <th>Away</th>
                        <th>Result</th>
                        <th>Scheduled</th>
                        <th class="venue-print-repeat">Venue</th>
                        <th class="text-end d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @php
                    $previousPrintDrawRound = null;
                @endphp
                @forelse($fixtureGroups as $group)
                    @php
                        $printDrawRound = $group['fixture']->draw_id.':'.$group['fixture']->round_nr;
                    @endphp
                    @if($printDrawRound !== $previousPrintDrawRound)
                    <tr class="venue-tie-heading" data-print-draw-round="{{ $printDrawRound }}"><th colspan="10">{{ $group['fixture']->draw?->drawName }} · Round {{ $group['fixture']->round_nr }}</th></tr>
                    @php
                        $previousPrintDrawRound = $printDrawRound;
                    @endphp
                    @endif
                    @foreach($group['waves'] as $wave)
                    @foreach($wave as $fx)
                    @php
                        $homeClass = '';
                        $awayClass = '';
                        if ($fx->fixtureResults->count()) {
                            $lastSet = $fx->fixtureResults->last();
                            if ($lastSet->team1_score > $lastSet->team2_score) {
                                $homeClass='winner-home'; $awayClass='loser-home';
                            } elseif ($lastSet->team2_score > $lastSet->team1_score) {
                                $homeClass='loser-home'; $awayClass='winner-home';
                            } else {
                                $homeClass='draw-cell'; $awayClass='draw-cell';
                            }
                        }

                        $homeLabel = collect($fx->lineup_display['home']['players'])->map(fn ($player) => ($player['rank'] ? '(' . $player['rank'] . ') ' : '') . $player['name'])->implode(' + ') ?: 'TBD';
                        $awayLabel = collect($fx->lineup_display['away']['players'])->map(fn ($player) => ($player['rank'] ? '(' . $player['rank'] . ') ' : '') . $player['name'])->implode(' + ') ?: 'TBD';
                        $display = $fx->scheduled_at ?? null;
                    @endphp
                    <tr id="row-{{ $fx->id }}">
                        <td>{{ $fx->id }}</td>
                        <td class="venue-print-repeat">{{ optional($fx->draw)->drawName ?? '—' }}</td>
                        <td>{{ $fx->round_nr }}</td>
                        <td>{{ $fx->rubber_sequence ?? $fx->match_nr ?? $fx->home_rank_nr ?? '—' }}</td>
                        <td class="home-cell {{ $homeClass }}"><span class="venue-print-team d-print-none">{{ $fx->tie_display['home'] }} · </span><span class="venue-print-team d-none d-print-inline">{{ $fx->lineup_display['home']['region'] ?: $fx->tie_display['home'] }} · </span>@include('backend.headOffice.partials.venue-lineup', ['lineup' => $fx->lineup_display['home']])</td>
                        <td class="away-cell {{ $awayClass }}"><span class="venue-print-team d-print-none">{{ $fx->tie_display['away'] }} · </span><span class="venue-print-team d-none d-print-inline">{{ $fx->lineup_display['away']['region'] ?: $fx->tie_display['away'] }} · </span>@include('backend.headOffice.partials.venue-lineup', ['lineup' => $fx->lineup_display['away']])</td>
                        <td id="result-col-{{ $fx->id }}">
                            @forelse($fx->fixtureResults as $r)
                                <strong>{{ $r->team1_score }}-{{ $r->team2_score }}</strong>@if(!$loop->last), @endif
                            @empty
                                <span class="text-muted small no-result-print">No result</span>
                            @endforelse
                        </td>
                        <td>
                            @if($display)
                                <span class="d-print-none">{{ \Carbon\Carbon::parse($display)->format('Y-m-d H:i') }}</span>
                                <span class="d-none d-print-inline venue-print-time">{{ \Carbon\Carbon::parse($display)->format('d M H:i') }}</span>
                            @else — @endif
                            @if($fx->court_label)<span class="d-none d-print-block">Court {{ $fx->court_label }}</span>@endif
                        </td>
                        <td class="venue-print-repeat">{{ $venue->name }}@if($fx->court_label)<br>Court {{ $fx->court_label }}@endif</td>
                        <td class="text-end d-print-none">
                            @unless($scoringPrint ?? false)
                            <button id="edit-btn-{{ $fx->id }}" class="btn btn-sm btn-icon btn-label-primary edit-score-btn"
                                data-id="{{ $fx->id }}" data-participant-revision="{{ $fx instanceof \App\Models\TeamFixture ? app(\App\Services\TeamParticipantHistoryService::class)->revision($fx) : '' }}"
                                data-action="{{ route('backend.team-fixtures.update', $fx->id) }}"
                                data-home="{{ $homeLabel }}"
                                data-away="{{ $awayLabel }}"
                                @foreach($fx->fixtureResults as $r)
                                    data-set{{ $r->set_nr }}_home="{{ $r->team1_score }}"
                                    data-set{{ $r->set_nr }}_away="{{ $r->team2_score }}"
                                @endforeach
                            >
                                <i class="ti ti-edit"></i>
                            </button>

                            @if($fx->fixtureResults->count())
                                <button type="button" class="btn btn-sm btn-icon btn-label-danger delete-result-btn ms-1"
                                    data-id="{{ $fx->id }}" data-participant-revision="{{ $fx instanceof \App\Models\TeamFixture ? app(\App\Services\TeamParticipantHistoryService::class)->revision($fx) : '' }}">
                                    <i class="ti ti-trash"></i>
                                </button>
                            @endif
                            @endunless
                        </td>
                    </tr>
                    @endforeach
                    @endforeach
                @empty
                    <tr><td colspan="10" class="text-center">No fixtures found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editScoreModal" tabindex="-1" aria-labelledby="venue-score-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editScoreForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title" id="venue-score-title">Edit score</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <strong id="fixtureTeams" class="visually-hidden"></strong>
          <div class="venue-score-sides">
            <div class="venue-score-side"><span id="venue-score-home-heading" class="small text-muted">Home</span><strong id="venue-score-home-name">Home player</strong></div>
            <div class="venue-score-side"><span id="venue-score-away-heading" class="small text-muted">Away</span><strong id="venue-score-away-name">Away player</strong></div>
          </div>
          <table class="table align-middle venue-score-table">
            <thead><tr><th scope="col">Set</th><th scope="col">Home score</th><th scope="col">Away score</th></tr></thead>
            <tbody>
              @for($i = 1; $i <= 3; $i++)
              <tr>
                <th scope="row" id="venue-set-{{ $i }}">Set {{ $i }}</th>
                <td><input type="number" class="form-control" name="set{{ $i }}_home" id="set{{ $i }}Home" min="0" inputmode="numeric" aria-labelledby="venue-set-{{ $i }} venue-score-home-heading venue-score-home-name"></td>
                <td><input type="number" class="form-control" name="set{{ $i }}_away" id="set{{ $i }}Away" min="0" inputmode="numeric" aria-labelledby="venue-set-{{ $i }} venue-score-away-heading venue-score-away-name"></td>
              </tr>
              @endfor
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save score</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
