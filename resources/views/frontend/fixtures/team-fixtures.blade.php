@extends('layouts/layoutMaster')

@section('title', 'Team Fixtures')

@section('content')
<div class="container-xxl py-4">
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
                            $homeNames = [];
                            $awayNames = [];
                            $homeRegionShort = $fx->region1Name?->short_name ?? null;
                            $awayRegionShort = $fx->region2Name?->short_name ?? null;

                            foreach ($fx->fixturePlayers as $fpRow) {
                                if ($fpRow->team1_id && $fpRow->player1) {
                                    $name = $fpRow->player1->full_name;
                                    if ($homeRegionShort) $name .= " ({$homeRegionShort})";
                                    $homeNames[] = $name;
                                } elseif ($fpRow->team1_no_profile_id) {
                                    $np = \App\Models\NoProfileTeamPlayer::find($fpRow->team1_no_profile_id);
                                    if ($np) {
                                        $name = trim($np->name . ' ' . $np->surname);
                                        if ($homeRegionShort) $name .= " ({$homeRegionShort})";
                                        $homeNames[] = $name;
                                    }
                                }
                                if ($fpRow->team2_id && $fpRow->player2) {
                                    $name = $fpRow->player2->full_name;
                                    if ($awayRegionShort) $name .= " ({$awayRegionShort})";
                                    $awayNames[] = $name;
                                } elseif ($fpRow->team2_no_profile_id) {
                                    $np2 = \App\Models\NoProfileTeamPlayer::find($fpRow->team2_no_profile_id);
                                    if ($np2) {
                                        $name = trim($np2->name . ' ' . $np2->surname);
                                        if ($awayRegionShort) $name .= " ({$awayRegionShort})";
                                        $awayNames[] = $name;
                                    }
                                }
                            }
                            $homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
                            $awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';
                            $display = $fx->scheduled_at ?? null;
                            $result = $fx->fixtureResults->count()
                                ? $fx->fixtureResults->map(fn($r) => "{$r->team1_score}-{$r->team2_score}")->implode(', ')
                                : null;

                            $homeClass = ''; $awayClass = '';
                            if ($fx->fixtureResults->count()) {
                                $lastSet = $fx->fixtureResults->last();
                                if ($lastSet->team1_score > $lastSet->team2_score) {
                                    $homeClass = 'winner-home'; $awayClass = 'loser-home';
                                } elseif ($lastSet->team2_score > $lastSet->team1_score) {
                                    $homeClass = 'loser-home'; $awayClass = 'winner-home';
                                } else {
                                    $homeClass = 'draw-cell'; $awayClass = 'draw-cell';
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

                            <td class="fw-bold text-secondary d-none d-md-table-cell">{{ $fx->home_rank_nr ?? '—' }}</td>
                            
                            <td class="fw-semibold text-end {{ $homeClass }} text-wrap" style="max-width:150px;">
                                {{ $homeLabel }}
                            </td>
                            <td class="text-center p-0" style="width:24px;">
                                <small class="text-muted">vs</small>
                            </td>
                            <td class="fw-semibold {{ $awayClass }} text-wrap" style="max-width:150px;">
                                {{ $awayLabel }}
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
