<svg width="{{ $svgData['totalWidth'] }}" height="{{ $svgData['totalHeight'] }}" viewBox="0 0 {{ $svgData['totalWidth'] }} {{ $svgData['totalHeight'] }}" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMinYMin meet">
    <style>
        .player-name { font-family: sans-serif; font-size: 13px; fill: #000; font-weight: 700; }
        .score-green { font-family: monospace; font-size: 12px; fill: #059669; font-weight: 800; }
        .id-red { font-family: sans-serif; font-size: 10px; fill: #ef4444; font-weight: 600; }
        .bracket-line { stroke: #000; stroke-width: 2.5; fill: none; }
        .seed-origin { font-family: sans-serif; font-size: 9px; fill: #fff; font-weight: 700; }
        .seed-origin-bg { rx: 3; ry: 3; }
        .seed-origin-inline { font-family: sans-serif; font-size: 12px; fill: #6366f1; font-weight: 700; }
        .match-hit { fill: transparent; cursor: pointer; }
        .match-hit:hover { fill: rgba(99,102,241,0.06); }
        .match-score { font-family: monospace; font-size: 11px; fill: #059669; font-weight: 700; }
    </style>

    @php
        $seedMap  = $svgData['seedOriginMap'] ?? [];
        $byeSlots = $svgData['byeSlots'] ?? [];
        $isEmpty  = !empty($emptyBracket);

        $isAdmin = auth()->check() && method_exists(auth()->user(), 'hasRole') && (
            auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-user')
        );

        // Build feeder map: for each fixture ID, which match_nr feeds winner/loser into it
        $winnerFeeders = [];
        $loserFeeders = [];
        foreach ($svgData['brackets'] as $_b) {
            foreach ($_b['rounds'] as $_matches) {
                foreach ($_matches as $_m) {
                    $_fx = $_m['fx'];
                    if ($_fx && $_fx->parent_fixture_id) {
                        $winnerFeeders[$_fx->parent_fixture_id][] = $_fx->match_nr;
                    }
                    if ($_fx && $_fx->loser_parent_fixture_id) {
                        $loserFeeders[$_fx->loser_parent_fixture_id][] = $_fx->match_nr;
                    }
                }
            }
            foreach ($_b['positionPlayoffs'] ?? [] as $_pp) {
                // Position playoff fixtures also feed into each other
                $_pfx = $_pp['fx'];
                if ($_pfx && $_pfx->parent_fixture_id) {
                    $winnerFeeders[$_pfx->parent_fixture_id][] = $_pfx->match_nr;
                }
                if ($_pfx && $_pfx->loser_parent_fixture_id) {
                    $loserFeeders[$_pfx->loser_parent_fixture_id][] = $_pfx->match_nr;
                }
            }
        }
    @endphp

    @foreach($svgData['brackets'] as $bracket)
        @php
            $bracketName = $bracket['name'] ?? 'Bracket';
            $bracketPositions = $bracket['positions'] ?? [];
            $bracketStartY = $bracket['startY'] ?? 100;
            $numRounds = $bracket['numRounds'] ?? 1;
            $posFrom = $bracket['posFrom'] ?? null;
            $posTo   = $bracket['posTo'] ?? null;

            $posLabel = ($posFrom && $posTo) ? "Pos {$posFrom}-{$posTo}" : '';
        @endphp

        {{-- Bracket Title --}}
        <text x="60" y="{{ $bracketStartY - 45 }}" style="font-family: sans-serif; font-size: 18px; font-weight: 800; fill: #1e293b;">
            {{ $bracketName }}
        </text>
        @if($posLabel && $isAdmin)
            <text x="{{ 60 + strlen($bracketName) * 10 + 10 }}" y="{{ $bracketStartY - 45 }}" style="font-family: sans-serif; font-size: 12px; font-weight: 600; fill: #6366f1;">
                {{ $posLabel }}
            </text>
        @endif

        {{-- Round Labels --}}
        @foreach($bracket['rounds'] as $rn => $rMatches)
            @php
                $firstMatch = $rMatches[0] ?? null;
                $roundLabel = $firstMatch['roundLabel'] ?? "R$rn";
                $roundX = $firstMatch ? $firstMatch['x'] : (60 + ($rn - 1) * 200);
            @endphp
            <text x="{{ $roundX + 95 }}" y="{{ $bracketStartY - 20 }}" text-anchor="middle" style="font-family: sans-serif; font-size: 11px; font-weight: 700; fill: #64748b;">
                {{ $roundLabel }}
            </text>
        @endforeach

        @foreach($bracket['rounds'] as $roundNum => $matches)
            @foreach($matches as $matchIndex => $match)
                @php
                    $fx = $match['fx'];
                    $x = $match['x'];
                    $y = $match['y'];
                    $w = $match['width'];
                    $h = $match['height'];
                    
                    $topLineY = $y;
                    $bottomLineY = $y + $h;
                    $midY = $y + ($h / 2);
                    $rightX = $x + $w;
                    
                    // LINKING LOGIC: Calculate which line to connect to in next round
                    $nextRoundX = $x + 200; // ROUND_GAP constant
                    
                    // Determine if this is the top or bottom match of a pair
                    $isTopOfPair = ($matchIndex % 2 == 0);
                    
                    // Connect from midpoint to the appropriate line on next round match
                    $connectToY = $midY; // Default (for final round or debugging)
                    
                    $empty1 = is_null($fx?->registration1_id);
                    $empty2 = is_null($fx?->registration2_id);
                    $hasWinner = !is_null($fx?->winner_registration);
                    $isBye1 = $empty1 && ($roundNum === 1 || $hasWinner);
                    $isBye2 = $empty2 && ($roundNum === 1 || $hasWinner);
                    $p1 = $empty1 ? ($isBye1 ? 'BYE' : '---') : ($fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---');
                    $p2 = $empty2 ? ($isBye2 ? 'BYE' : '---') : ($fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---');

                    // Feeder labels: show "W1001" / "L1002" when slot is TBD
                    $wFeed = $fx ? ($winnerFeeders[$fx->id] ?? []) : [];
                    $lFeed = $fx ? ($loserFeeders[$fx->id] ?? []) : [];
                    sort($wFeed);
                    sort($lFeed);
                    $feed1 = '';
                    $feed2 = '';
                    if ($empty1 && !$isBye1 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed1 = 'W' . $wFeed[0];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed1 = 'W' . $wFeed[0];
                        elseif (count($lFeed) >= 2) $feed1 = 'L' . $lFeed[0];
                        elseif (count($lFeed) === 1) $feed1 = 'L' . $lFeed[0];
                    }
                    if ($empty2 && !$isBye2 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed2 = 'W' . $wFeed[1];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed2 = 'L' . $lFeed[0];
                        elseif (count($lFeed) >= 2) $feed2 = 'L' . $lFeed[1];
                    }

                    $origin1 = $seedMap[$fx?->registration1_id] ?? null;
                    $origin2 = $seedMap[$fx?->registration2_id] ?? null;

                    if ($isEmpty) {
                        // Use virtual data from engine when fx is null
                        $vSL1 = $match['seedLabel1'] ?? null;
                        $vSL2 = $match['seedLabel2'] ?? null;
                        $vFL1 = $match['feederLabel1'] ?? null;
                        $vFL2 = $match['feederLabel2'] ?? null;

                        $p1 = ($roundNum === 1) ? ($origin1 ?? $vSL1 ?? '') : ($vFL1 ?? '');
                        $p2 = ($roundNum === 1) ? ($origin2 ?? $vSL2 ?? '') : ($vFL2 ?? '');
                        $feed1 = '';
                        $feed2 = '';
                        $origin1 = null;
                        $origin2 = null;
                    }
                    // Replace '---' with feeder label if available
                    if ($feed1 && ($p1 === '---' || $p1 === 'TBD')) $p1 = $feed1;
                    if ($feed2 && ($p2 === '---' || $p2 === 'TBD')) $p2 = $feed2;
                    $isFeeder1 = str_starts_with($p1, 'W') || str_starts_with($p1, 'L');
                    $isFeeder2 = str_starts_with($p2, 'W') || str_starts_with($p2, 'L');
                    $feederIsWin1 = str_starts_with($p1, 'W');
                    $feederIsWin2 = str_starts_with($p2, 'W');
                @endphp

                <g>
                    {{-- Top Player --}}
                    <text x="{{ $x }}" y="{{ $topLineY - 6 }}" class="player-name"
                        @if($isFeeder1) style="fill: {{ $feederIsWin1 ? '#0d6efd' : '#e65100' }}; font-size: 11px; font-weight: 600;"
                        @elseif($isEmpty && $roundNum === 1 && $p1 && !$isBye1) style="fill: #6366f1; font-size: 12px; font-weight: 700;"
                        @elseif($isBye1 && !$isEmpty) style="fill: #9ca3af; font-style: italic;"
                        @endif>{{ Str::limit($p1, 25) }}</text>
                    @if($origin1 && $isAdmin)
                        @php $badgeX1 = $x + min(strlen($p1), 25) * 7.5 + 6; @endphp
                        <rect x="{{ $badgeX1 }}" y="{{ $topLineY - 17 }}" width="22" height="14" fill="#6366f1" class="seed-origin-bg" />
                        <text x="{{ $badgeX1 + 11 }}" y="{{ $topLineY - 7 }}" class="seed-origin" text-anchor="middle">{{ $origin1 }}</text>
                    @endif
                    <line x1="{{ $x }}" y1="{{ $topLineY }}" x2="{{ $rightX }}" y2="{{ $topLineY }}" class="bracket-line" />

                    {{-- Bottom Player --}}
                    <line x1="{{ $x }}" y1="{{ $bottomLineY }}" x2="{{ $rightX }}" y2="{{ $bottomLineY }}" class="bracket-line" />
                    <text x="{{ $x }}" y="{{ $bottomLineY - 6 }}" class="player-name"
                        @if($isFeeder2) style="fill: {{ $feederIsWin2 ? '#0d6efd' : '#e65100' }}; font-size: 11px; font-weight: 600;"
                        @elseif($isEmpty && $roundNum === 1 && $p2 && !$isBye2) style="fill: #6366f1; font-size: 12px; font-weight: 700;"
                        @elseif($isBye2 && !$isEmpty) style="fill: #9ca3af; font-style: italic;"
                        @endif>{{ Str::limit($p2, 25) }}</text>
                    @if($origin2 && $isAdmin)
                        @php $badgeX2 = $x + min(strlen($p2), 25) * 7.5 + 6; @endphp
                        <rect x="{{ $badgeX2 }}" y="{{ $bottomLineY - 17 }}" width="22" height="14" fill="#6366f1" class="seed-origin-bg" />
                        <text x="{{ $badgeX2 + 11 }}" y="{{ $bottomLineY - 7 }}" class="seed-origin" text-anchor="middle">{{ $origin2 }}</text>
                    @endif

                    {{-- Vertical Connector --}}
                    <line x1="{{ $rightX }}" y1="{{ $topLineY }}" x2="{{ $rightX }}" y2="{{ $bottomLineY }}" class="bracket-line" />

                    {{-- Horizontal Exit Line (Links to next round) - Skip for final round --}}
                    @if($roundNum < count($bracket['rounds']))
                        <line x1="{{ $rightX }}" y1="{{ $midY }}" x2="{{ $nextRoundX }}" y2="{{ $midY }}" class="bracket-line" />
                    @endif

                    {{-- Winner Line for Final Round --}}
                    @if($roundNum == count($bracket['rounds']))
                        @php
                            $winnerLineEndX = $rightX + 120;
                            $winnerId = $fx?->winner_registration;
                            $winnerName = '';
                            if ($winnerId) {
                                $winnerName = ($winnerId == $fx->registration1_id) ? $p1 : $p2;
                            }
                        @endphp
                        {{-- Horizontal winner line --}}
                        <line x1="{{ $rightX }}" y1="{{ $midY }}" x2="{{ $winnerLineEndX }}" y2="{{ $midY }}" class="bracket-line" />
                        
                        {{-- Winner name --}}
                        @if($winnerName && !$isEmpty)
                            <text x="{{ $winnerLineEndX + 10 }}" y="{{ $midY + 5 }}" class="player-name" style="fill: #059669; font-size: 15px;">
                                🏆 {{ Str::limit($winnerName, 30) }}
                            </text>
                        @endif
                    @endif

                    {{-- Score display --}}
                    @php
                        $scoreStr = $fx?->fixtureResults?->sortBy('set_nr')->map(fn($r) => $r->registration1_score . '-' . $r->registration2_score)->implode('  ');
                        $canEnterScore = !$empty1 && !$empty2 && !$hasWinner;
                    @endphp
                    @if($scoreStr && !$isEmpty)
                        <text x="{{ $x + ($w / 2) }}" y="{{ $midY + 4 }}" class="match-score" text-anchor="middle">{{ $scoreStr }}</text>
                    @endif

                    @if(!$isEmpty)
                        @if($isAdmin)
                            {{-- Middle Match Info (admin only) --}}
                            <text x="{{ $rightX - 6 }}" y="{{ $midY + 4 }}" class="id-red" text-anchor="end">#{{ $fx?->id }}</text>
                            <text x="{{ $x - 10 }}" y="{{ $midY + 4 }}" class="id-red" text-anchor="end">({{ $fx?->match_nr }})</text>
                        @endif

                        {{-- Clickable hit area for score entry --}}
                        @if($canEnterScore && $isAdmin)
                            <rect x="{{ $x }}" y="{{ $topLineY - 18 }}" width="{{ $w }}" height="{{ $h + 36 }}"
                                  class="match-hit bracket-score-btn"
                                  data-fixture-id="{{ $fx->id }}"
                                  data-home="{{ Str::limit($p1, 30) }}"
                                  data-away="{{ Str::limit($p2, 30) }}" />
                        @endif
                    @else
                        {{-- Virtual match number on empty bracket --}}
                        @php $vNr = $match['virtualMatchNr'] ?? null; @endphp
                        @if($vNr)
                            <text x="{{ $x + ($w / 2) }}" y="{{ $midY + 4 }}" text-anchor="middle" style="font-family: sans-serif; font-size: 10px; fill: #94a3b8; font-weight: 600;">M{{ $vNr }}</text>
                        @endif
                    @endif
                </g>
            @endforeach
        @endforeach

        {{-- ===================================
             POSITION PLAYOFFS SECTION
             =================================== --}}
        @if(!empty($bracket['positionPlayoffs']))
            {{-- Section Title --}}
            <text x="60" y="{{ $bracket['positionPlayoffs'][0]['y'] - 30 }}" style="font-family: sans-serif; font-size: 16px; font-weight: 700; fill: #333;">Position Playoffs</text>
            
            @foreach($bracket['positionPlayoffs'] as $playoff)
                @php
                    $fx = $playoff['fx'];
                    $x = $playoff['x'];
                    $y = $playoff['y'];
                    $w = $playoff['width'];
                    $h = $playoff['height'];
                    
                    $topLineY = $y;
                    $bottomLineY = $y + $h;
                    $midY = $y + ($h / 2);
                    $rightX = $x + $w;
                    
                    $empty1 = is_null($fx?->registration1_id);
                    $empty2 = is_null($fx?->registration2_id);
                    $hasWinner = !is_null($fx?->winner_registration);
                    $fxByeSlots = $byeSlots[$fx?->id] ?? [];
                    $isBye1 = $empty1 && ($hasWinner || !empty($fxByeSlots['slot1']));
                    $isBye2 = $empty2 && ($hasWinner || !empty($fxByeSlots['slot2']));
                    $p1 = $empty1 ? ($isBye1 ? 'BYE' : '---') : ($fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---');
                    $p2 = $empty2 ? ($isBye2 ? 'BYE' : '---') : ($fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---');
                    $label = $playoff['label'] ?? '';
                    $isFinal = $playoff['isFinal'] ?? false;

                    // Feeder labels for position playoffs
                    $wFeed = $fx ? ($winnerFeeders[$fx->id] ?? []) : [];
                    $lFeed = $fx ? ($loserFeeders[$fx->id] ?? []) : [];
                    sort($wFeed);
                    sort($lFeed);
                    $feed1 = '';
                    $feed2 = '';
                    if ($empty1 && !$isBye1 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed1 = 'W' . $wFeed[0];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed1 = 'W' . $wFeed[0];
                        elseif (count($lFeed) >= 2) $feed1 = 'L' . $lFeed[0];
                        elseif (count($lFeed) === 1) $feed1 = 'L' . $lFeed[0];
                    }
                    if ($empty2 && !$isBye2 && !$isEmpty) {
                        if (count($wFeed) >= 2) $feed2 = 'W' . $wFeed[1];
                        elseif (count($wFeed) === 1 && count($lFeed) >= 1) $feed2 = 'L' . $lFeed[0];
                        elseif (count($lFeed) >= 2) $feed2 = 'L' . $lFeed[1];
                    }

                    $origin1 = $seedMap[$fx?->registration1_id] ?? null;
                    $origin2 = $seedMap[$fx?->registration2_id] ?? null;

                    if ($isEmpty) {
                        // Use virtual feeder labels from engine
                        $vFL1 = $playoff['feederLabel1'] ?? null;
                        $vFL2 = $playoff['feederLabel2'] ?? null;
                        $p1 = $origin1 ?? $vFL1 ?? '';
                        $p2 = $origin2 ?? $vFL2 ?? '';
                        $feed1 = '';
                        $feed2 = '';
                        $origin1 = null;
                        $origin2 = null;
                    }
                    if ($feed1 && ($p1 === '---' || $p1 === 'TBD')) $p1 = $feed1;
                    if ($feed2 && ($p2 === '---' || $p2 === 'TBD')) $p2 = $feed2;
                    $isFeeder1 = str_starts_with($p1, 'W') || str_starts_with($p1, 'L');
                    $isFeeder2 = str_starts_with($p2, 'W') || str_starts_with($p2, 'L');
                    $feederIsWin1 = str_starts_with($p1, 'W');
                    $feederIsWin2 = str_starts_with($p2, 'W');
                @endphp

                <g>
                    {{-- Playoff Label --}}
                    <text x="{{ $x + ($w / 2) }}" y="{{ $y - 20 }}" text-anchor="middle" style="font-family: sans-serif; font-size: 11px; fill: #666; font-weight: 600;">
                        {{ $label }}
                    </text>

                    {{-- Top Player --}}
                    <text x="{{ $x }}" y="{{ $topLineY - 6 }}" class="player-name"
                        @if($isFeeder1) style="fill: {{ $feederIsWin1 ? '#0d6efd' : '#e65100' }}; font-size: 11px; font-weight: 600;"
                        @elseif($isBye1 && !$isEmpty) style="fill: #9ca3af; font-style: italic;"
                        @endif>{{ Str::limit($p1, 25) }}</text>
                    @if($origin1 && $isAdmin)
                        @php $badgeX1 = $x + min(strlen($p1), 25) * 7.5 + 6; @endphp
                        <rect x="{{ $badgeX1 }}" y="{{ $topLineY - 17 }}" width="22" height="14" fill="#6366f1" class="seed-origin-bg" />
                        <text x="{{ $badgeX1 + 11 }}" y="{{ $topLineY - 7 }}" class="seed-origin" text-anchor="middle">{{ $origin1 }}</text>
                    @endif
                    <line x1="{{ $x }}" y1="{{ $topLineY }}" x2="{{ $rightX }}" y2="{{ $topLineY }}" class="bracket-line" />

                    {{-- Bottom Player --}}
                    <line x1="{{ $x }}" y1="{{ $bottomLineY }}" x2="{{ $rightX }}" y2="{{ $bottomLineY }}" class="bracket-line" />
                    <text x="{{ $x }}" y="{{ $bottomLineY - 6 }}" class="player-name"
                        @if($isFeeder2) style="fill: {{ $feederIsWin2 ? '#0d6efd' : '#e65100' }}; font-size: 11px; font-weight: 600;"
                        @elseif($isBye2 && !$isEmpty) style="fill: #9ca3af; font-style: italic;"
                        @endif>{{ Str::limit($p2, 25) }}</text>
                    @if($origin2 && $isAdmin)
                        @php $badgeX2 = $x + min(strlen($p2), 25) * 7.5 + 6; @endphp
                        <rect x="{{ $badgeX2 }}" y="{{ $bottomLineY - 17 }}" width="22" height="14" fill="#6366f1" class="seed-origin-bg" />
                        <text x="{{ $badgeX2 + 11 }}" y="{{ $bottomLineY - 7 }}" class="seed-origin" text-anchor="middle">{{ $origin2 }}</text>
                    @endif

                    {{-- Vertical Connector --}}
                    <line x1="{{ $rightX }}" y1="{{ $topLineY }}" x2="{{ $rightX }}" y2="{{ $bottomLineY }}" class="bracket-line" />

                    {{-- Winner Line for Final Playoffs --}}
                    @if($isFinal)
                        @php
                            $winnerLineEndX = $rightX + 100;
                            $winnerId = $fx?->winner_registration;
                            $winnerName = '';
                            if ($winnerId) {
                                $winnerName = ($winnerId == $fx->registration1_id) ? $p1 : $p2;
                            }
                        @endphp
                        <line x1="{{ $rightX }}" y1="{{ $midY }}" x2="{{ $winnerLineEndX }}" y2="{{ $midY }}" class="bracket-line" />
                        @if($winnerName && !$isEmpty)
                            <text x="{{ $winnerLineEndX + 10 }}" y="{{ $midY + 5 }}" class="player-name" style="fill: #059669;">
                                {{ Str::limit($winnerName, 25) }}
                            </text>
                        @endif
                    @else
                        {{-- Connecting line to next playoff round --}}
                        <line x1="{{ $rightX }}" y1="{{ $midY }}" x2="{{ $x + 200 }}" y2="{{ $midY }}" class="bracket-line" />
                    @endif

                    {{-- Score display --}}
                    @php
                        $scoreStr = $fx?->fixtureResults?->sortBy('set_nr')->map(fn($r) => $r->registration1_score . '-' . $r->registration2_score)->implode('  ');
                        $canEnterScore = !$empty1 && !$empty2 && !$hasWinner;
                    @endphp
                    @if($scoreStr && !$isEmpty)
                        <text x="{{ $x + ($w / 2) }}" y="{{ $midY + 4 }}" class="match-score" text-anchor="middle">{{ $scoreStr }}</text>
                    @endif

                    @if(!$isEmpty)
                        @if($isAdmin)
                            {{-- Match Info (admin only) --}}
                            <text x="{{ $rightX - 6 }}" y="{{ $midY + 4 }}" class="id-red" text-anchor="end">#{{ $fx?->id }}</text>
                        @endif

                        {{-- Clickable hit area for score entry --}}
                        @if($canEnterScore && $isAdmin)
                            <rect x="{{ $x }}" y="{{ $topLineY - 18 }}" width="{{ $w }}" height="{{ $h + 36 }}"
                                  class="match-hit bracket-score-btn"
                                  data-fixture-id="{{ $fx->id }}"
                                  data-home="{{ Str::limit($p1, 30) }}"
                                  data-away="{{ Str::limit($p2, 30) }}" />
                        @endif
                    @else
                        {{-- Virtual match number on empty bracket --}}
                        @php $vNr = $playoff['virtualMatchNr'] ?? null; @endphp
                        @if($vNr)
                            <text x="{{ $x + ($w / 2) }}" y="{{ $midY + 4 }}" text-anchor="middle" style="font-family: sans-serif; font-size: 10px; fill: #94a3b8; font-weight: 600;">M{{ $vNr }}</text>
                        @endif
                    @endif
                </g>
            @endforeach
        @endif
    @endforeach
</svg>
