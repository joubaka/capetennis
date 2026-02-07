<style>
  .nmr {
      fill: red;
      font-weight: bold;
      font-size: 9px;
  }
  .score-svg {
      font-size: 11px;
      fill: green;
      font-weight: 600;
  }
  .svg_name {
      font-size: 12px;
      font-family: Helvetica;
      font-weight: bold;
  }
  .sched {
      font-size: 9px;
      fill: orange;
      font-family: Helvetica;
  }
</style>

<g transform="translate({{ $x }}, {{ $y }})">

    {{-- MATCH NUMBER (center-left) --}}
    <text x="-3" y="{{ ($height / 2) + 4 }}" class="nmr" text-anchor="end">
        ({{ mnr($fx) }})
    </text>

    {{-- FIXTURE ID (top-right) --}}
    <text 
        x="148" 
        y="{{$height/2}}" 
        class="nmr"
        text-anchor="end"
    >
        #{{ fxid($fx) }}
    </text>

    {{-- OUTER BOX --}}
    <line x1="0" y1="0" x2="150" y2="0" stroke="black"/>
    <line x1="0" y1="{{ $height }}" x2="150" y2="{{ $height }}" stroke="black"/>
    <line x1="150" y1="0" x2="150" y2="{{ $height }}" stroke="black"/>

    {{-- PLAYER 1 NAME (top) --}}
    <text x="10" y="-3" class="svg_name">
        {{ name1($fx) }}
    </text>

    {{-- SCORE (under player 1 name) --}}
    <text x="10" y="{{ $height/2 }}" class="score-svg">
        {{ score($fx) }}
    </text>

    {{-- SCHEDULE (centered, 5px above bottom) --}}
    @php
        $sch = $fx->orderOfPlay ?? null;

        if ($sch && $sch->time) {
            $day   = \Carbon\Carbon::parse($sch->time)->format('D');
            $time  = \Carbon\Carbon::parse($sch->time)->format('H:i');
            $venue = $sch->venue->name ?? '';
            $display = trim("$day $time" . ($venue ? " • $venue" : ""));
        } else {
            $display = '';
        }
        $scheduleY = $height - 5;
    @endphp

    <text 
        x="75"
        y="{{ $scheduleY }}"
        class="sched"
        text-anchor="middle"
    >
        {{ $display }}
    </text>

    {{-- PLAYER 2 BELOW --}}
    <text x="10" y="{{ $height + 13 }}" class="svg_name">
        {{ name2($fx) }}
    </text>

</g>

{{-- WINNER LINE + WINNER NAME --}}
@php
    $mn = $fx->match_nr;
    $needsWinnerLine = in_array($mn, [
        2003, 3009, 3010, 3011,
        4003, 4004
    ]);

    $boxWidth = 150;
    $lineWidth = 120;
    $winner = winnerName($fx);
@endphp

@if ($needsWinnerLine)
    {{-- HORIZONTAL LINE OUT OF BOX --}}
    <line
        x1="{{ $x + $boxWidth }}"
        y1="{{ $y + ($height / 2) }}"
        x2="{{ $x + $boxWidth + $lineWidth }}"
        y2="{{ $y + ($height / 2) }}"
        stroke="black"
        stroke-width="2"
    />

    {{-- WINNER NAME --}}
    <text
        x="{{ $x + $boxWidth + $lineWidth - 90 }}"
        y="{{ $y + ($height / 2) - 6 }}"
        class="svg_name"
    >
        {{ $winner }}
    </text>
@endif
