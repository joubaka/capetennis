{{-- ============================================================
     MAIN BRACKET HTML VIEW (Replaces SVG)
     Modern, responsive bracket design
     ============================================================ --}}

@php
    // Helper functions
    $getName = function ($fx, $slot) {
        if (!$fx) return '---';
        $reg = $slot === 1 ? $fx->registration1 : $fx->registration2;
        if (!$reg) return '---';
        return $reg->players->pluck('full_name')->join(' / ') ?: '---';
    };

    $getScore = function ($fx) {
        if (!$fx || !$fx->fixtureResults || $fx->fixtureResults->isEmpty()) return '';
        return $fx->fixtureResults
            ->sortBy('set_nr')
            ->map(fn($r) => ($r->registration1_score ?? $r->score1 ?? 0) . '-' . ($r->registration2_score ?? $r->score2 ?? 0))
            ->implode(', ');
    };

    $getWinner = function ($fx) {
        if (!$fx || !$fx->winner_registration) return null;
        if ($fx->winner_registration == $fx->registration1_id) {
            return $fx->registration1?->players?->pluck('full_name')->join(' / ');
        }
        if ($fx->winner_registration == $fx->registration2_id) {
            return $fx->registration2?->players?->pluck('full_name')->join(' / ');
        }
        return null;
    };

    $hasFixtures = $sf1 || $sf2 || $final;
@endphp

<style>
/* ============================================================
   BRACKET STYLES
   ============================================================ */
.bracket-container {
    padding: 20px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.bracket-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bracket-title i {
    color: #0d6efd;
}

/* Bracket Layout */
.bracket {
    display: flex;
    flex-direction: row;
    gap: 0;
    align-items: center;
    min-width: 800px;
}

.bracket-round {
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}

.bracket-round-title {
    text-align: center;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 0.5rem;
    letter-spacing: 0.05em;
}

/* Match Box */
.bracket-match {
    background: #fff;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    margin: 10px 0;
    min-width: 200px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.bracket-match:hover {
    border-color: #0d6efd;
    box-shadow: 0 4px 12px rgba(13,110,253,0.15);
}

.bracket-match.final {
    border-color: #ffc107;
    background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
}

.bracket-match.final:hover {
    border-color: #e0a800;
}

/* Match Header */
.match-header {
    background: #0a3566;
    color: #fff;
    padding: 4px 10px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.match-header.final-header {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #000;
}

/* Player Rows */
.match-player {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.85rem;
    transition: background 0.15s ease;
}

.match-player:last-child {
    border-bottom: none;
}

.match-player.winner {
    background: #d4edda;
    font-weight: 600;
}

.match-player.loser {
    background: #f8f9fa;
    color: #6c757d;
}

.player-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.player-score {
    font-weight: 600;
    color: #0d6efd;
    margin-left: 8px;
    white-space: nowrap;
}

/* Match Score (center) */
.match-score {
    text-align: center;
    padding: 4px 8px;
    font-size: 0.75rem;
    color: #28a745;
    font-weight: 600;
    background: #f8f9fa;
    border-top: 1px solid #f0f0f0;
}

/* Winner Display */
.bracket-winner {
    background: linear-gradient(135deg, #28a745, #218838);
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    text-align: center;
    min-width: 180px;
    box-shadow: 0 4px 12px rgba(40,167,69,0.3);
}

.bracket-winner .trophy {
    font-size: 1.5rem;
    margin-bottom: 4px;
}

.bracket-winner .winner-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    opacity: 0.9;
}

.bracket-winner .winner-name {
    font-size: 1rem;
    font-weight: 700;
    margin-top: 4px;
}

/* Connector Lines */
.bracket-connector {
    width: 40px;
    position: relative;
}

.bracket-connector::before {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    width: 40px;
    height: 2px;
    background: #dee2e6;
}

/* Vertical connector for SF to Final */
.bracket-connector-vertical {
    width: 40px;
    height: 100%;
    position: relative;
}

.bracket-connector-vertical::before {
    content: '';
    position: absolute;
    left: 0;
    top: 25%;
    bottom: 25%;
    width: 2px;
    background: #dee2e6;
}

.bracket-connector-vertical::after {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    width: 40px;
    height: 2px;
    background: #dee2e6;
}

/* Empty State */
.bracket-empty {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.bracket-empty i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .bracket {
        min-width: 600px;
        transform: scale(0.85);
        transform-origin: top left;
    }
    
    .bracket-match {
        min-width: 160px;
    }
    
    .player-name {
        max-width: 100px;
    }
}
</style>

<div class="bracket-container">

    @if(!$hasFixtures)
        {{-- No fixtures yet --}}
        <div class="bracket-empty">
            <i class="ti ti-tournament"></i>
            <h5>No Playoff Brackets Generated</h5>
            <p class="text-muted mb-3">Click "Generate All Playoffs" to create the bracket from round-robin standings.</p>
        </div>
    @else
        {{-- MAIN BRACKET --}}
        <div class="bracket-title">
            <i class="ti ti-trophy"></i>
            Main Draw (1st-4th)
        </div>

        <div class="bracket">
            {{-- ROUND 1: Semi-Finals --}}
            <div class="bracket-round">
                <div class="bracket-round-title">Semi-Finals</div>
                
                {{-- SF1 --}}
                <div class="bracket-match">
                    <div class="match-header">
                        <span>SF 1</span>
                        <span>#{{ $sf1->match_nr ?? '' }}</span>
                    </div>
                    @php
                        $sf1Winner = $sf1?->winner_registration;
                        $sf1Score = $getScore($sf1);
                    @endphp
                    <div class="match-player {{ $sf1Winner == $sf1?->registration1_id ? 'winner' : ($sf1Winner ? 'loser' : '') }}">
                        <span class="player-name" title="{{ $getName($sf1, 1) }}">{{ $getName($sf1, 1) }}</span>
                    </div>
                    <div class="match-player {{ $sf1Winner == $sf1?->registration2_id ? 'winner' : ($sf1Winner ? 'loser' : '') }}">
                        <span class="player-name" title="{{ $getName($sf1, 2) }}">{{ $getName($sf1, 2) }}</span>
                    </div>
                    @if($sf1Score)
                        <div class="match-score">{{ $sf1Score }}</div>
                    @endif
                </div>

                {{-- SF2 --}}
                <div class="bracket-match">
                    <div class="match-header">
                        <span>SF 2</span>
                        <span>#{{ $sf2->match_nr ?? '' }}</span>
                    </div>
                    @php
                        $sf2Winner = $sf2?->winner_registration;
                        $sf2Score = $getScore($sf2);
                    @endphp
                    <div class="match-player {{ $sf2Winner == $sf2?->registration1_id ? 'winner' : ($sf2Winner ? 'loser' : '') }}">
                        <span class="player-name" title="{{ $getName($sf2, 1) }}">{{ $getName($sf2, 1) }}</span>
                    </div>
                    <div class="match-player {{ $sf2Winner == $sf2?->registration2_id ? 'winner' : ($sf2Winner ? 'loser' : '') }}">
                        <span class="player-name" title="{{ $getName($sf2, 2) }}">{{ $getName($sf2, 2) }}</span>
                    </div>
                    @if($sf2Score)
                        <div class="match-score">{{ $sf2Score }}</div>
                    @endif
                </div>
            </div>

            {{-- Connector --}}
            <div class="bracket-connector"></div>

            {{-- ROUND 2: Final --}}
            <div class="bracket-round">
                <div class="bracket-round-title">Final</div>
                
                <div class="bracket-match final">
                    <div class="match-header final-header">
                        <span>🏆 FINAL</span>
                        <span>#{{ $final->match_nr ?? '' }}</span>
                    </div>
                    @php
                        $finalWinner = $final?->winner_registration;
                        $finalScore = $getScore($final);
                    @endphp
                    <div class="match-player {{ $finalWinner == $final?->registration1_id ? 'winner' : ($finalWinner ? 'loser' : '') }}">
                        <span class="player-name" title="{{ $getName($final, 1) }}">{{ $getName($final, 1) }}</span>
                    </div>
                    <div class="match-player {{ $finalWinner == $final?->registration2_id ? 'winner' : ($finalWinner ? 'loser' : '') }}">
                        <span class="player-name" title="{{ $getName($final, 2) }}">{{ $getName($final, 2) }}</span>
                    </div>
                    @if($finalScore)
                        <div class="match-score">{{ $finalScore }}</div>
                    @endif
                </div>
            </div>

            {{-- Connector to Winner --}}
            <div class="bracket-connector"></div>

            {{-- WINNER --}}
            <div class="bracket-round">
                <div class="bracket-round-title">Champion</div>
                
                @php $champion = $getWinner($final); @endphp
                <div class="bracket-winner">
                    <div class="trophy">🏆</div>
                    <div class="winner-label">Winner</div>
                    <div class="winner-name">{{ $champion ?: 'TBD' }}</div>
                </div>
            </div>
        </div>

        {{-- PLATE BRACKET (if exists) --}}
        @if($qf_plate->count() > 0 || $sf_plate->count() > 0 || $final_plate)
            <hr class="my-4">
            
            <div class="bracket-title">
                <i class="ti ti-award"></i>
                Plate Draw (3rd-8th)
            </div>

            <div class="bracket">
                {{-- QF Round --}}
                @if($qf_plate->count() > 0)
                <div class="bracket-round">
                    <div class="bracket-round-title">Quarter-Finals</div>
                    
                    @foreach($qf_plate as $qf)
                        <div class="bracket-match" style="margin: 5px 0;">
                            <div class="match-header" style="font-size: 0.65rem; padding: 3px 8px;">
                                <span>QF</span>
                                <span>#{{ $qf->match_nr ?? '' }}</span>
                            </div>
                            @php
                                $qfWinner = $qf?->winner_registration;
                                $qfScore = $getScore($qf);
                            @endphp
                            <div class="match-player {{ $qfWinner == $qf?->registration1_id ? 'winner' : ($qfWinner ? 'loser' : '') }}" style="padding: 4px 8px; font-size: 0.8rem;">
                                <span class="player-name" title="{{ $getName($qf, 1) }}">{{ $getName($qf, 1) }}</span>
                            </div>
                            <div class="match-player {{ $qfWinner == $qf?->registration2_id ? 'winner' : ($qfWinner ? 'loser' : '') }}" style="padding: 4px 8px; font-size: 0.8rem;">
                                <span class="player-name" title="{{ $getName($qf, 2) }}">{{ $getName($qf, 2) }}</span>
                            </div>
                            @if($qfScore)
                                <div class="match-score" style="font-size: 0.7rem;">{{ $qfScore }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="bracket-connector"></div>
                @endif

                {{-- SF Round --}}
                @if($sf_plate->count() > 0)
                <div class="bracket-round">
                    <div class="bracket-round-title">Semi-Finals</div>
                    
                    @foreach($sf_plate as $sf)
                        <div class="bracket-match">
                            <div class="match-header">
                                <span>SF</span>
                                <span>#{{ $sf->match_nr ?? '' }}</span>
                            </div>
                            @php
                                $sfWinner = $sf?->winner_registration;
                                $sfScore = $getScore($sf);
                            @endphp
                            <div class="match-player {{ $sfWinner == $sf?->registration1_id ? 'winner' : ($sfWinner ? 'loser' : '') }}">
                                <span class="player-name" title="{{ $getName($sf, 1) }}">{{ $getName($sf, 1) }}</span>
                            </div>
                            <div class="match-player {{ $sfWinner == $sf?->registration2_id ? 'winner' : ($sfWinner ? 'loser' : '') }}">
                                <span class="player-name" title="{{ $getName($sf, 2) }}">{{ $getName($sf, 2) }}</span>
                            </div>
                            @if($sfScore)
                                <div class="match-score">{{ $sfScore }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="bracket-connector"></div>
                @endif

                {{-- Plate Final --}}
                @if($final_plate)
                <div class="bracket-round">
                    <div class="bracket-round-title">Plate Final</div>
                    
                    <div class="bracket-match">
                        <div class="match-header" style="background: #6c757d;">
                            <span>🥉 PLATE FINAL</span>
                            <span>#{{ $final_plate->match_nr ?? '' }}</span>
                        </div>
                        @php
                            $pfWinner = $final_plate?->winner_registration;
                            $pfScore = $getScore($final_plate);
                        @endphp
                        <div class="match-player {{ $pfWinner == $final_plate?->registration1_id ? 'winner' : ($pfWinner ? 'loser' : '') }}">
                            <span class="player-name" title="{{ $getName($final_plate, 1) }}">{{ $getName($final_plate, 1) }}</span>
                        </div>
                        <div class="match-player {{ $pfWinner == $final_plate?->registration2_id ? 'winner' : ($pfWinner ? 'loser' : '') }}">
                            <span class="player-name" title="{{ $getName($final_plate, 2) }}">{{ $getName($final_plate, 2) }}</span>
                        </div>
                        @if($pfScore)
                            <div class="match-score">{{ $pfScore }}</div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        @endif

    @endif
</div>
