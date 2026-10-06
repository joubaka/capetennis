@extends('layouts.backend')

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
    .fixture-region-badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    /* PDF/Print Optimization */
    @media print {
        @page {
            size: landscape;
            margin: 1cm;
        }
        .d-print-none, .layout-navbar, .layout-menu, .content-footer, .btn-close {
            display: none !important;
        }
        body {
            background-color: #fff !important;
            font-size: 10pt;
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
        }
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
    function generatePDF() {
        const originalTitle = document.title;
        document.title = "{{ $event->name ?? 'Event' }}_{{ $venue->name }}_Fixtures";
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
@include('backend.event.partials.header', [
    'eventWorkspaceActive' => 'draws',
    'eventWorkspaceIcon' => 'ti-map-pin',
    'eventWorkspaceSubtitle' => $venue->name . ' fixtures',
])
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

<div class="d-none d-print-block mb-4 border-bottom pb-3">
    <div class="d-flex justify-content-between">
        <div>
            <h2 class="fw-bold mb-1">{{ $event->name ?? 'Tournament Fixtures' }}</h2>
            <h4 class="text-primary">{{ $venue->name }}</h4>
        </div>
        <div class="text-end">
            <p class="mb-0"><strong>Status:</strong> Official Venue Report</p>
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
                        <th>Draw</th>
                        <th>Rd</th>
                        <th>Match</th>
                        <th>Home</th>
                        <th>Away</th>
                        <th>Result</th>
                        <th>Scheduled</th>
                        <th>Venue</th>
                        <th class="text-end d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($fixtureGroups as $group)
                    <tr class="venue-tie-heading">
                        <th colspan="10">
                            {{ $group['fixture']->draw?->drawName }} · Round {{ $group['fixture']->round_nr }} ·
                            {{ $group['fixture']->tie_display['home'] }} vs {{ $group['fixture']->tie_display['away'] }}
                            <div class="small fw-normal mt-1">
                                Tie starts: {{ $group['start']?->format('d M Y H:i') ?? 'To be confirmed' }}
                                @if($group['next_start']) · Next tie starts: {{ $group['next_start']->format('d M Y H:i') }}@endif
                            </div>
                        </th>
                    </tr>
                    @foreach($group['waves'] as $wave)
                    <tr class="venue-wave-heading">
                        <th colspan="10" class="small">
                            Players start: {{ $wave->first()->scheduled_at?->format('d M Y H:i') ?? 'To be confirmed' }}
                            @if(!$loop->last)
                                · Next players start: {{ $group['waves']->get($loop->index + 1)->first()->scheduled_at?->format('d M Y H:i') ?? 'To be confirmed' }}
                            @endif
                        </th>
                    </tr>
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
                        <td>{{ optional($fx->draw)->drawName ?? '—' }}</td>
                        <td>{{ $fx->round_nr }}</td>
                        <td>{{ $fx->rubber_sequence ?? $fx->match_nr ?? $fx->home_rank_nr ?? '—' }}</td>
                        <td class="home-cell {{ $homeClass }}">@include('backend.headOffice.partials.venue-lineup', ['lineup' => $fx->lineup_display['home']])</td>
                        <td class="away-cell {{ $awayClass }}">@include('backend.headOffice.partials.venue-lineup', ['lineup' => $fx->lineup_display['away']])</td>
                        <td id="result-col-{{ $fx->id }}">
                            @forelse($fx->fixtureResults as $r)
                                <strong>{{ $r->team1_score }}-{{ $r->team2_score }}</strong>@if(!$loop->last), @endif
                            @empty
                                <span class="text-muted small no-result-print">No result</span>
                            @endforelse
                        </td>
                        <td>
                            @if($display)
                                {{ \Carbon\Carbon::parse($display)->format('Y-m-d H:i') }}
                            @else — @endif
                        </td>
                        <td>{{ $venue->name }}</td>
                        <td class="text-end d-print-none">
                            <button id="edit-btn-{{ $fx->id }}" class="btn btn-sm btn-icon btn-label-primary edit-score-btn"
                                data-id="{{ $fx->id }}" data-participant-revision="{{ $fx instanceof \App\Models\TeamFixture ? app(\App\Services\TeamParticipantHistoryService::class)->revision($fx) : '' }}"
                                data-action="{{ route('backend.team-fixtures.update', $fx->id) }}"
                                data-home="{{ e($homeLabel) }}"
                                data-away="{{ e($awayLabel) }}"
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

<div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editScoreForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title">Edit Score</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong id="fixtureTeams"></strong></p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Set</th>
                  <th>Home</th>
                  <th>Away</th>
                </tr>
              </thead>
              <tbody>
                @for($i = 1; $i <= 3; $i++)
                  <tr>
                    <td>Set {{ $i }}</td>
                    <td><input type="number" class="form-control" name="set{{ $i }}_home" id="set{{ $i }}Home" min="0"></td>
                    <td><input type="number" class="form-control" name="set{{ $i }}_away" id="set{{ $i }}Away" min="0"></td>
                  </tr>
                @endfor
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection
