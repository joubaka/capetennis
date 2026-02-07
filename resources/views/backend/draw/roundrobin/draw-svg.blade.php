@php
    if (! function_exists('mnr')) {
        function mnr($fx) { return $fx?->match_nr ?? ''; }
    }
    if (! function_exists('name1')) {
        function name1($fx) {
            return $fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
        }
    }
    if (! function_exists('name2')) {
        function name2($fx) {
            return $fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
        }
    }
  function score($fx) {
    if (!$fx) return '';

    $sets = $fx->fixtureResults
        ->sortBy('set_nr')
        ->map(fn($s) => "{$s->registration1_score}-{$s->registration2_score}")
        ->implode(', ');

    return $sets ?: '';
}

  if (! function_exists('fxid')) {
    function fxid($fx) {
        return $fx?->id ?? '';
    }
}


function winnerName($fx) {
    if (!$fx->winner_registration) {
        return '';
    }

    // If winner = registration1
    if ($fx->winner_registration == $fx->registration1_id) {
        return $fx->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
    }

    // If winner = registration2
    if ($fx->winner_registration == $fx->registration2_id) {
        return $fx->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
    }

    // Should never happen, but safe fallback
    return '---';
}


@endphp


<svg width="1600" height="1600">

    {{-- ======================================================
         MAIN DRAW
       ====================================================== --}}
    @if(isset($svg['main']))
        <text x="50" y="40" style="font-size:20px;font-weight:bold;">
            MAIN DRAW Position 1-2
        </text>

        @foreach($svg['main'] as $round => $matches)
            @foreach($matches as $m)

                @include('svg.match', [
                    'fx'     => $m['fx'],
                    'x'      => $m['x'],
                    'y'      => $m['y'],
                    'height' => $m['height'] ?? 40
                ])


            @endforeach
        @endforeach
    @endif



    {{-- ======================================================
         PLATE DRAW (SHIFTED DOWN)
       ====================================================== --}}
    @php
        $plateOffset = 400;
    @endphp

    @if(isset($svg['plate']) && count($svg['plate']))

        <text x="50" y="{{ $plateOffset }}" style="font-size:20px;font-weight:bold;">
            PLATE DRAW Position 3-8
        </text>
 {{-- ======================================================
     LABELS FOR ALL PLATE PLAYOFF ROUNDS (CORRECTED)
     ====================================================== --}}

@php
    // Resolve playoff matches safely
    $m3009 = $svg['plate'][4][0] ?? null; // 3rd/4th
    $m3010 = $svg['plate'][4][1] ?? null; // 7th/8th  (CORRECTED)
    $m3011 = $svg['plate'][4][2] ?? null; // 5th/6th  (CORRECTED)
@endphp



@if($m3010)
    <text
        x="{{ $m3010['x'] }}"
        y="{{ $m3010['y'] + $plateOffset - 30 }}"
        style="font-size:18px; "
    >
        Playoff 7/8
    </text>
@endif

@if($m3011)
    <text
        x="{{ $m3011['x'] }}"
        y="{{ $m3011['y'] + $plateOffset - 30 }}"
        style="font-size:18px; "
    >
        Playoff 5/6
    </text>
@endif


        @foreach($svg['plate'] as $round => $matches)
            @foreach($matches as $m)

                @include('svg.match', [
                    'fx'     => $m['fx'],
                    'x'      => $m['x'],
                    'y'      => $m['y'] + $plateOffset,
                    'height' => $m['height'] ?? 40
                ])

              

            @endforeach
        @endforeach

    @endif
{{-- ======================================================
     CONSOLATION DRAW — NEW SECTION
   ====================================================== --}}
@if(isset($svg['consolation']) && count($svg['consolation']))

    @php
        // find bottom of Plate Round 1 to place Consolation properly
        $plateR1 = $svg['plate'][1] ?? [];
        $bottomPlateY = 0;

        if (count($plateR1)) {
            $last = end($plateR1);
            $bottomPlateY = ($last['y'] + $last['height']) + $plateOffset;
        }

        $consOffset = $bottomPlateY + 260; 
    @endphp

    <text x="50" y="{{ $consOffset }}" style="font-size:20px;font-weight:bold;">
        CONSOLATION DRAW Position 9-12
    </text>

    {{-- ============================
         Playoff 11/12 label
       ============================ --}}
    @php
        $c4004 = $svg['consolation'][2][1] ?? null;
    @endphp

    @if($c4004)
        <text
            x="{{ $c4004['x'] }}"
            y="{{ $c4004['y'] + $consOffset - 30 }}"
            style="font-size:18px;"
        >
            Playoff 11/12
        </text>
    @endif


    @foreach($svg['consolation'] as $round => $matches)
        @foreach($matches as $m)

            @include('svg.match', [
                'fx'     => $m['fx'],
                'x'      => $m['x'],
                'y'      => $m['y'] + $consOffset,
                'height' => $m['height'] ?? 40
            ])

        @endforeach
    @endforeach

@endif


</svg>
