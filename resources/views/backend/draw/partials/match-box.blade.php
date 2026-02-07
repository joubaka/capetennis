@php
    /** @var \App\Models\Fixture|null $fixture */
    $fx = $fixture ?? null;

    $x1    = $x;            // left
    $x2    = $x + 200;      // right edge of the match box
    $topY  = $y;
    $botY  = $y + 40;
    $midY  = ($topY + $botY) / 2;
@endphp

{{-- Horizontal lines (top & bottom) --}}
<line x1="{{ $x1 }}" y1="{{ $topY }}" x2="{{ $x2 }}" y2="{{ $topY }}" stroke="black" />
<line x1="{{ $x1 }}" y1="{{ $botY }}" x2="{{ $x2 }}" y2="{{ $botY }}" stroke="black" />

{{-- Names --}}
<text x="{{ $x1 + 10 }}" y="{{ $topY - 5 }}"  class="name">{{ $name($fx, 1) }}</text>
<text x="{{ $x1 + 10 }}" y="{{ $botY - 5 }}" class="name">{{ $name($fx, 2) }}</text>

{{-- Vertical connector on the right side of the match --}}
<line x1="{{ $x2 }}" y1="{{ $topY }}" x2="{{ $x2 }}" y2="{{ $botY }}" stroke="black"/>

{{-- Short elbow going right (to next round connector) --}}
<line x1="{{ $x2 }}" y1="{{ $midY }}" x2="{{ $x2 + 80 }}" y2="{{ $midY }}" stroke="black"/>
