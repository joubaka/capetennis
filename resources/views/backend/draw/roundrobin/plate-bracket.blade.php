@php
    // ============================
    // PLATE FIXTURE MAPPING
    // ============================
    $m1    = $qf_plate[0] ?? null;
    $m2    = $qf_plate[1] ?? null;
    $m3    = $qf_plate[2] ?? null;
    $m4    = $qf_plate[3] ?? null;

    $sf1   = $sf_plate[0] ?? null;
    $sf2   = $sf_plate[1] ?? null;

    $final = $final_plate ?? null;

    $csf1  = $csf_plate[0] ?? null;
    $csf2  = $csf_plate[1] ?? null;

    $cfinal = $cfinal_plate ?? null;

    // 3rd/4th playoff fixture
    $plate_34 = $plate_34 ?? null;

    // MAIN SF LOSERS (FEED-INS)
    $main_sf1 = $main_sf[0] ?? null;
    $main_sf2 = $main_sf[1] ?? null;

    $main_sf_loser1 = $main_sf1?->loser_registration ?? null; // bottom SF
    $main_sf_loser2 = $main_sf2?->loser_registration ?? null; // top SF

    // HELPERS
    $regName = function ($reg) {
        if (!$reg) return '---';
        return $reg->players->pluck('full_name')->join(' / ');
    };

    $name = function ($fx, $slot) use ($regName) {
        if (!$fx) return '---';
        $reg = $slot === 1 ? $fx->registration1 : $fx->registration2;
        return $regName($reg);
    };

    $score = function ($fx) {
        if (!$fx || !$fx->fixtureResults?->count()) return '';
        return $fx->fixtureResults->map(function ($r) {
            return $r->score1 . '-' . $r->score2;
        })->join(', ');
    };

    $sf1_loser = $sf1?->loser_registration ?? null;
    $sf2_loser = $sf2?->loser_registration ?? null;
@endphp

<svg class="ct-bracket-svg" width="1140" height="1400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1140 1400">
    @include('draw.partials.bracket-svg-style')
  <style>
      .name  { font-family: Helvetica; font-size: 14px; font-weight: bold; }
      .score { font-family: Helvetica; font-size: 12px; }
      .mnr   { font-family: Helvetica; font-size: 12px; }
  </style>

  <g>
    <text x="83" y="72" fill="black" style="font-family: Helvetica; font-size: 16px; font-weight: bold;">
      Plate Draw
    </text>

    <!-- ============================================================
         ROUND 1
         ============================================================ -->

    <!-- P-R1-M1 -->
    <text x="83" y="163" class="mnr">P-R1-M1</text>
    <line x1="108" y1="148" x2="258" y2="148" stroke="black"/>
    <line x1="108" y1="188" x2="258" y2="188" stroke="black"/>
    <line x1="258" y1="148" x2="258" y2="188" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $m1, 'slot' => 1, 'x' => 118, 'y' => 143, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['fixture' => $m1, 'slot' => 2, 'x' => 118, 'y' => 183, 'maxWidth' => 135])
    <text x="120" y="166" class="score">{{ $score($m1) }}</text>

    <!-- P-R1-M2 -->
    <text x="83" y="243" class="mnr">P-R1-M2</text>
    <line x1="108" y1="228" x2="258" y2="228" stroke="black"/>
    <line x1="108" y1="268" x2="258" y2="268" stroke="black"/>
    <line x1="258" y1="228" x2="258" y2="268" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $m2, 'slot' => 1, 'x' => 118, 'y' => 223, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['fixture' => $m2, 'slot' => 2, 'x' => 118, 'y' => 263, 'maxWidth' => 135])
    <text x="118" y="248" class="score">{{ $score($m2) }}</text>

    <!-- P-R1-M3 -->
    <text x="83" y="323" class="mnr">P-R1-M3</text>
    <line x1="108" y1="308" x2="258" y2="308" stroke="black"/>
    <line x1="108" y1="348" x2="258" y2="348" stroke="black"/>
    <line x1="258" y1="308" x2="258" y2="348" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $m3, 'slot' => 1, 'x' => 118, 'y' => 303, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['fixture' => $m3, 'slot' => 2, 'x' => 118, 'y' => 343, 'maxWidth' => 135])
    <text x="118" y="328" class="score">{{ $score($m3) }}</text>

    <!-- P-R1-M4 -->
    <text x="83" y="403" class="mnr">P-R1-M4</text>
    <line x1="108" y1="388" x2="258" y2="388" stroke="black"/>
    <line x1="108" y1="428" x2="258" y2="428" stroke="black"/>
    <line x1="258" y1="388" x2="258" y2="428" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $m4, 'slot' => 1, 'x' => 118, 'y' => 383, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['fixture' => $m4, 'slot' => 2, 'x' => 118, 'y' => 423, 'maxWidth' => 135])
    <text x="118" y="408" class="score">{{ $score($m4) }}</text>

    <!-- ============================================================
         ROUND 2
         ============================================================ -->

    <!-- P-R2-M5 -->
    <text x="238" y="208" class="mnr">P-R2-M5</text>
    <line x1="258" y1="168" x2="458" y2="168" stroke="black"/>
    <line x1="258" y1="248" x2="458" y2="248" stroke="black"/>
    <line x1="458" y1="168" x2="458" y2="248" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $sf1, 'slot' => 1, 'x' => 268, 'y' => 163, 'maxWidth' => 185])
    @include('draw.partials.svg-player-identity', ['fixture' => $sf1, 'slot' => 2, 'x' => 268, 'y' => 243, 'maxWidth' => 185])
    <text x="268" y="208" class="score">{{ $score($sf1) }}</text>

    <!-- P-R2-M6 -->
    <text x="238" y="368" class="mnr">P-R2-M6</text>
    <line x1="258" y1="328" x2="458" y2="328" stroke="black"/>
    <line x1="258" y1="408" x2="458" y2="408" stroke="black"/>
    <line x1="458" y1="328" x2="458" y2="408" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $sf2, 'slot' => 1, 'x' => 268, 'y' => 323, 'maxWidth' => 185])
    @include('draw.partials.svg-player-identity', ['fixture' => $sf2, 'slot' => 2, 'x' => 268, 'y' => 403, 'maxWidth' => 185])
    <text x="268" y="368" class="score">{{ $score($sf2) }}</text>

    <!-- ============================================================
         ROUND 3 FEED-IN
         ============================================================ -->

    <!-- P-R3-M7 -->
    <text x="440" y="163" class="mnr">P-R3-M7</text>
    <line x1="658" y1="208" x2="658" y2="122" stroke="black"/>
    <line x1="458" y1="122" x2="658" y2="122" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['registration' => $main_sf_loser2, 'x' => 465, 'y' => 104, 'maxWidth' => 185])

    <!-- P-R3-M8 -->
    <text x="440" y="408" class="mnr">P-R3-M8</text>
    <line x1="458" y1="457" x2="658" y2="457" stroke="black"/>
    <line x1="657" y1="367" x2="658" y2="457" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['registration' => $main_sf_loser1, 'x' => 466, 'y' => 444, 'maxWidth' => 185])

    <!-- ============================================================
         ROUND 4
         ============================================================ -->

    <!-- P-R4-M9 (Plate Final) -->
    <text x="638" y="288" class="mnr">P-R4-M9</text>
    <line x1="458" y1="208" x2="658" y2="208" stroke="black"/>
    <line x1="458" y1="367" x2="658" y2="367" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['fixture' => $final, 'slot' => 1, 'x' => 468, 'y' => 203, 'maxWidth' => 185])
    @include('draw.partials.svg-player-identity', ['fixture' => $final, 'slot' => 2, 'x' => 466, 'y' => 361, 'maxWidth' => 185])
    <text x="468" y="288" class="score">{{ $score($final) }}</text>

    <!-- P-R4-M10 (3rd/4th) -->
    <text x="638" y="528" class="mnr">P-R4-M10</text>
    <line x1="658" y1="508" x2="858" y2="508" stroke="black"/>
    <line x1="658" y1="548" x2="858" y2="548" stroke="black"/>
    <line x1="858" y1="508" x2="858" y2="548" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['registration' => $sf1_loser, 'x' => 668, 'y' => 503, 'maxWidth' => 185])
    @include('draw.partials.svg-player-identity', ['registration' => $sf2_loser, 'x' => 668, 'y' => 543, 'maxWidth' => 185])
    <text x="668" y="523" class="score">{{ $score($plate_34) }}</text>

    <!-- WINNER LINE – P-R4-M10 -->
    <line x1="858" y1="528" x2="1058" y2="528" stroke="black"/>

    <!-- P-R4-M11 (7th/8th) -->
    <text x="238" y="528" class="mnr">P-R4-M11</text>
    <line x1="258" y1="508" x2="458" y2="508" stroke="black"/>
    <line x1="258" y1="548" x2="458" y2="548" stroke="black"/>
    <line x1="458" y1="508" x2="458" y2="548" stroke="black"/>
    @include('draw.partials.svg-player-identity', ['registration' => $sf1_loser, 'x' => 268, 'y' => 503, 'maxWidth' => 185])
    @include('draw.partials.svg-player-identity', ['registration' => $sf2_loser, 'x' => 268, 'y' => 543, 'maxWidth' => 185])
    <text x="268" y="523" class="score">{{ $score($plate_78 ?? null) }}</text>
    <line x1="458" y1="528" x2="558" y2="528" stroke="black"/>

    <!-- Static connectors (keep as in your version) -->
    <path data-ct-edge fill="none" stroke="#000" d="m657.99998,412l198,0"/>
    <path data-ct-edge fill="none" stroke="#000" d="m657.99998,164.99094l198,0"/>
    <line y2="411.99999" x2="855.99998" y1="165.09741" x1="855.99998" stroke="#000" fill="none"/>
    <line y2="289.32719" x2="1059.203" y1="287.55728" x1="856.54819" stroke="#000" fill="none"/>

   <!-- ============================================================
     CONSOLATION BRACKET (4 players)
     ============================================================ -->

<!-- C-R1-M1 (Consolation SF1) -->
<g>
    <text x="83" y="693" class="mnr">C-R1-M1</text>

    <line x1="108" y1="678" x2="258" y2="678" stroke="black"/>
    <line x1="108" y1="718" x2="258" y2="718" stroke="black"/>
    <line x1="258" y1="678" x2="258" y2="718" stroke="black"/>

    @include('draw.partials.svg-player-identity', ['fixture' => $csf1, 'slot' => 1, 'x' => 118, 'y' => 673, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['fixture' => $csf1, 'slot' => 2, 'x' => 118, 'y' => 713, 'maxWidth' => 135])
    <text x="118" y="696" class="score">{{ $score($csf1) }}</text>
</g>

<!-- C-R1-M2 (Consolation SF2) -->
<g>
    <text x="83" y="773" class="mnr">C-R1-M2</text>

    <line x1="108" y1="758" x2="258" y2="758" stroke="black"/>
    <line x1="108" y1="798" x2="258" y2="798" stroke="black"/>
    <line x1="258" y1="758" x2="258" y2="798" stroke="black"/>

    @include('draw.partials.svg-player-identity', ['fixture' => $csf2, 'slot' => 1, 'x' => 118, 'y' => 753, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['fixture' => $csf2, 'slot' => 2, 'x' => 118, 'y' => 793, 'maxWidth' => 135])
    <text x="118" y="776" class="score">{{ $score($csf2) }}</text>
</g>

<!-- C-R2-M3 (Consolation FINAL) -->
<g>
    <text x="258" y="733" class="mnr">C-R2-M3</text>

    <line x1="258" y1="698" x2="458" y2="698" stroke="black"/>
    <line x1="258" y1="778" x2="458" y2="778" stroke="black"/>
    <line x1="458" y1="698" x2="458" y2="778" stroke="black"/>

    @include('draw.partials.svg-player-identity', ['fixture' => $cfinal, 'slot' => 1, 'x' => 268, 'y' => 693, 'maxWidth' => 185])
    @include('draw.partials.svg-player-identity', ['fixture' => $cfinal, 'slot' => 2, 'x' => 268, 'y' => 773, 'maxWidth' => 185])
    <text x="268" y="733" class="score">{{ $score($cfinal) }}</text>
</g>

<!-- WINNER LINE — Consolation Final -->
<line x1="458" y1="738" x2="658" y2="738" stroke="black"/>

<text x="468" y="735" class="name bracket-winner">
    {{ $cfinal?->winner?->players?->pluck('full_name')->join(' / ') ?? '' }}
</text>

<!-- C-R2-M4 (Consolation 3rd/4th Playoff) -->
<g>
    <text x="83" y="853" class="mnr">C-R2-M4</text>

    <line x1="108" y1="838" x2="258" y2="838" stroke="black"/>
    <line x1="108" y1="878" x2="258" y2="878" stroke="black"/>
    <line x1="258" y1="838" x2="258" y2="878" stroke="black"/>

    @include('draw.partials.svg-player-identity', ['name' => $name($csf1?->loser_fixture ?? null, 1), 'x' => 118, 'y' => 833, 'maxWidth' => 135])
    @include('draw.partials.svg-player-identity', ['name' => $name($csf2?->loser_fixture ?? null, 2), 'x' => 118, 'y' => 873, 'maxWidth' => 135])
    <text x="118" y="856" class="score">
        {{ $score($c34 ?? null) }}
    </text>
</g>

<!-- WINNER LINE — Consolation 3rd/4th -->
<line x1="258" y1="858" x2="458" y2="858" stroke="black"/>


  </g>
</svg>
@include('draw.partials.final-positions', ['draw' => $draw])
