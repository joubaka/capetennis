@extends('layouts/layoutMaster')

@section('title', 'Team Fixtures')

@section('content')
@if($draw->published)
<div class="container-xxl pt-3 d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.team-draw.standings', $draw) }}">Team standings</a><a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.events.standings', $draw->event) }}">Full event standings</a></div>
@include('frontend.fixtures.partials.live-results-status')
@endif
<div class="container-xxl py-4" @if($draw->published) data-live-results="team-fixture-list" @endif>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            Team Fixtures
            @if(isset($draw) && $draw)
                <small class="text-muted">— {{ $draw->drawName }}</small>
            @endif
        </h2>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            {{-- Hide ID and Round on mobile --}}
                            <th class="d-none d-sm-table-cell">#</th>
                            <th class="d-none d-sm-table-cell">Round</th>
                            
                            {{-- This column shows "Scheduled" on mobile, replacing Round --}}
                            <th class="position-relative">
                                Scheduled
                                <span 
                                    class="position-absolute top-0 end-0 me-1 mt-1 d-none d-md-inline"
                                    style="font-size: 0.9rem; cursor: pointer;"
                                    title="Shows the day and time (hover for full date)">
                                    <i class="bi bi-info-circle text-info"></i>
                                </span>
                            </th>

                            <th class="d-none d-md-table-cell">Match #</th>
                            <th class="text-end">Home</th>
                            <th class="p-0"></th>
                            <th>Away</th>
                            <th>Result</th>
                            <th class="d-none d-lg-table-cell">Venue</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($fixtures as $fx)
                        @php
                            $display = $fx->scheduled_at ?? null;
                            $result = $fx->fixtureResults->count()
                                ? $fx->fixtureResults->map(fn($r) => "{$r->team1_score}-{$r->team2_score}")->implode(', ')
                                : null;

                            $homeClass = ''; $awayClass = '';
                            if ($fx->fixtureResults->count()) {
                                $winner = $fx->winnerSide();
                                if ($winner === 'home') {
                                    $homeClass = 'winner-home'; $awayClass = 'loser-home';
                                } elseif ($winner === 'away') {
                                    $homeClass = 'loser-home'; $awayClass = 'winner-home';
                                }
                            }
                        @endphp
                        <tr>
                            <td class="text-muted d-none d-sm-table-cell">{{ $fx->id }}</td>
                            <td class="fw-bold text-primary d-none d-sm-table-cell">{{ $fx->round_nr ?? '—' }}</td>
                            
                            {{-- Scheduled Column (Moved to 2nd/3rd position on mobile) --}}
                            <td>
                                @if($display)
                                    @php
                                        $carbon = \Carbon\Carbon::parse($display);
                                        $short = $carbon->format('D H:i');
                                        $full = $carbon->format('l Y-m-d H:i');
                                    @endphp
                                    <span class="badge bg-light border text-dark text-nowrap" title="{{ $full }}">
                                        {{ $short }}
                                    </span>
                                @else
                                    <span class="badge bg-light border text-muted">—</span>
                                @endif
                            </td>

                            <td class="fw-bold text-secondary d-none d-md-table-cell">{{ $fx->rubber_sequence ?: ($fx->home_rank_nr ?? '—') }}</td>
                            
                            <td class="fw-semibold text-end {{ $homeClass }} text-wrap" style="max-width:150px;">
                                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])
                            </td>
                            <td class="text-center p-0" style="width:24px;">
                                <small class="text-muted">vs</small>
                            </td>
                            <td class="fw-semibold {{ $awayClass }} text-wrap" style="max-width:150px;">
                                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])
                            </td>
                            <td>
                                @if($result)
                                    <span class="badge bg-success text-white px-2 py-1" style="font-weight:600;">{{ $result }}</span>
                                @else
                                    <span class="text-muted smaller">Pending</span>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <span class="badge bg-light border text-dark">
                                    {{ optional($fx->venue)->name ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No fixtures found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection

<style>
.winner-home { background-color: rgba(40,167,69,.12)!important; }
.loser-home { background-color: rgba(220,53,69,.12)!important; }
.draw-cell { background-color: rgba(255,193,7,.12)!important; }
th, td { vertical-align: middle !important; }
.text-wrap { white-space: normal !important; word-break: break-word; }
.text-nowrap { white-space: nowrap !important; }

@media (max-width: 576px) {
    .table th, .table td { font-size: 0.75rem; padding: 0.4rem 0.2rem; }
    .table .badge { font-size: 0.75rem; padding: 0.2rem 0.3rem; }
    .card-body { padding: 0; }
    h2 { font-size: 1.1rem; }
    .smaller { font-size: 0.7rem; }
}
</style>
