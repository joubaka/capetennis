<svg width="{{ $svgData['totalWidth'] }}" height="{{ $svgData['totalHeight'] + 50 }}" viewBox="0 0 {{ $svgData['totalWidth'] }} {{ $svgData['totalHeight'] + 50 }}" xmlns="http://www.w3.org/2000/svg">
    <style>
        .player-name { font-family: sans-serif; font-size: 13px; fill: #000; font-weight: 700; }
        .score-green { font-family: monospace; font-size: 12px; fill: #059669; font-weight: 800; }
        .id-red { font-family: sans-serif; font-size: 10px; fill: #ef4444; font-weight: 600; }
        .bracket-line { stroke: #000; stroke-width: 2.5; fill: none; }
    </style>

    @foreach($svgData['brackets'] as $bracket)
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
                    
                    $p1 = $fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
                    $p2 = $fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
                @endphp

                <g>
                    {{-- Top Player --}}
                    <text x="{{ $x }}" y="{{ $topLineY - 6 }}" class="player-name">{{ Str::limit($p1, 25) }}</text>
                    <line x1="{{ $x }}" y1="{{ $topLineY }}" x2="{{ $rightX }}" y2="{{ $topLineY }}" class="bracket-line" />

                    {{-- Bottom Player --}}
                    <line x1="{{ $x }}" y1="{{ $bottomLineY }}" x2="{{ $rightX }}" y2="{{ $bottomLineY }}" class="bracket-line" />
                    <text x="{{ $x }}" y="{{ $bottomLineY - 6 }}" class="player-name">{{ Str::limit($p2, 25) }}</text>

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
                        @if($winnerName)
                            <text x="{{ $winnerLineEndX + 10 }}" y="{{ $midY + 5 }}" class="player-name" style="fill: #059669; font-size: 15px;">
                                🏆 {{ Str::limit($winnerName, 30) }}
                            </text>
                        @endif
                    @endif

                    {{-- Middle Match Info --}}
                    <text x="{{ $rightX - 6 }}" y="{{ $midY + 4 }}" class="id-red" text-anchor="end">#{{ $fx?->id }}</text>
                    <text x="{{ $x - 10 }}" y="{{ $midY + 4 }}" class="id-red" text-anchor="end">({{ $fx?->match_nr }})</text>
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
                    
                    $p1 = $fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
                    $p2 = $fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
                    $label = $playoff['label'] ?? '';
                    $isFinal = $playoff['isFinal'] ?? false;
                @endphp

                <g>
                    {{-- Playoff Label --}}
                    <text x="{{ $x + ($w / 2) }}" y="{{ $y - 10 }}" text-anchor="middle" style="font-family: sans-serif; font-size: 11px; fill: #666; font-weight: 600;">
                        {{ $label }}
                    </text>

                    {{-- Top Player --}}
                    <text x="{{ $x }}" y="{{ $topLineY - 6 }}" class="player-name">{{ Str::limit($p1, 25) }}</text>
                    <line x1="{{ $x }}" y1="{{ $topLineY }}" x2="{{ $rightX }}" y2="{{ $topLineY }}" class="bracket-line" />

                    {{-- Bottom Player --}}
                    <line x1="{{ $x }}" y1="{{ $bottomLineY }}" x2="{{ $rightX }}" y2="{{ $bottomLineY }}" class="bracket-line" />
                    <text x="{{ $x }}" y="{{ $bottomLineY - 6 }}" class="player-name">{{ Str::limit($p2, 25) }}</text>

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
                        @if($winnerName)
                            <text x="{{ $winnerLineEndX + 10 }}" y="{{ $midY + 5 }}" class="player-name" style="fill: #059669;">
                                {{ Str::limit($winnerName, 25) }}
                            </text>
                        @endif
                    @else
                        {{-- Connecting line to next playoff round --}}
                        <line x1="{{ $rightX }}" y1="{{ $midY }}" x2="{{ $x + 200 }}" y2="{{ $midY }}" class="bracket-line" />
                    @endif

                    {{-- Match Info --}}
                    <text x="{{ $rightX - 6 }}" y="{{ $midY + 4 }}" class="id-red" text-anchor="end">#{{ $fx?->id }}</text>
                </g>
            @endforeach
        @endif
    @endforeach
</svg>
