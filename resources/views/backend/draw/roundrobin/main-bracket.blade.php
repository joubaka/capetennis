<div class="svgdrawdiv svgdraw0">
<svg width="694" height="250" viewBox="0 0 694 250" preserveAspectRatio="xMinYMin slice" class="svgdraw" xmlns="http://www.w3.org/2000/svg">

<style>
    .svg_name { font-family: Helvetica; font-weight: bold; font-size: 14px; }
    .score    { font-family: Helvetica; font-size: 12px; }
    .mnr      { font-family: Helvetica; font-size: 12px; }
</style>

@php
    function name1($fx) { return $fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---'; }
    function name2($fx) { return $fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---'; }
    function score($fx) {
        if (!$fx || !$fx->fixtureResults->count()) return '';
        return $fx->fixtureResults->map(fn($r) => $r->score1.'-'.$r->score2)->join(', ');
    }
@endphp

<!-- TITLE -->
<text x="83" y="12" fill="black" style="font-family: Helvetica; font-size: 16px; font-weight: bold;">
    Main Draw
</text>

<!-- ============================================================
     R1-M1 (SF1) — shifted +30px, with M- prefix
     ============================================================ -->
<g>
    <text x="83" y="93" class="mnr">M-R1-M1</text>

    <line x1="108" y1="78" x2="258" y2="78" stroke="black"/>
    <line x1="108" y1="118" x2="258" y2="118" stroke="black"/>
    <line x1="258" y1="78" x2="258" y2="118" stroke="black"/>

    <text x="118" y="73" class="svg_name">{{ name1($sf1) }}</text>
    <text x="118" y="113" class="svg_name">{{ name2($sf1) }}</text>
    <text x="118" y="96"  class="score">{{ score($sf1) }}</text>
</g>

<!-- ============================================================
     R1-M2 (SF2) — shifted +30px, with M- prefix
     ============================================================ -->
<g>
    <text x="83" y="173" class="mnr">M-R1-M2</text>

    <line x1="108" y1="158" x2="258" y2="158" stroke="black"/>
    <line x1="108" y1="198" x2="258" y2="198" stroke="black"/>
    <line x1="258" y1="158" x2="258" y2="198" stroke="black"/>

    <text x="118" y="153" class="svg_name">{{ name1($sf2) }}</text>
    <text x="118" y="193" class="svg_name">{{ name2($sf2) }}</text>
    <text x="118" y="176" class="score">{{ score($sf2) }}</text>
</g>

<!-- ============================================================
     R2-M3 (FINAL) — shifted +30px, with M- prefix
     ============================================================ -->
<g>
    <text x="258" y="133" class="mnr">M-R2-M3</text>

    <line x1="258" y1="98"  x2="458" y2="98"  stroke="black"/>
    <line x1="258" y1="178" x2="458" y2="178" stroke="black"/>
    <line x1="458" y1="98"  x2="458" y2="178" stroke="black"/>

    <text x="268" y="93"  class="svg_name">{{ name1($final) }}</text>
    <text x="268" y="173" class="svg_name">{{ name2($final) }}</text>
    <text x="268" y="133" class="score">{{ score($final) }}</text>
</g>

<!-- ============================================================
     WINNER LINE — shifted +30px
     ============================================================ -->
<line x1="458" y1="138" x2="658" y2="138" stroke="black"/>

<text x="468" y="135" class="svg_name">
    {{ $final?->winner?->players?->pluck('full_name')->join(' / ') ?? '' }}
</text>

</svg>
</div>
