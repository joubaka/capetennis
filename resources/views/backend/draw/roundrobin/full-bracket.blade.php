<div class="svgdrawdiv svgdraw0">
<svg
    width="1140"
    height="1400"
    viewBox="0 0 1140 1400"
    preserveAspectRatio="xMinYMin slice"
    class="svgdraw"
    xmlns="http://www.w3.org/2000/svg"
>

    <style>
        .svg_name { font-family: Helvetica; font-weight: bold; font-size: 14px; }
        .name     { font-family: Helvetica; font-weight: bold; font-size: 14px; }
        .score    { font-family: Helvetica; font-size: 12px; }
        .mnr      { font-family: Helvetica; font-size: 12px; }
    </style>

    {{-- ============================================================
         MAIN DRAW HELPERS
         ============================================================ --}}
    @php
        function name1($fx) {
            return $fx?->registration1?->players?->pluck('full_name')->join(' / ') ?? '---';
        }
        function name2($fx) {
            return $fx?->registration2?->players?->pluck('full_name')->join(' / ') ?? '---';
        }
        function score($fx) {
            if (!$fx || !$fx->fixtureResults->count()) return '';
            return $fx->fixtureResults->map(fn($r) => $r->score1.'-'.$r->score2)->join(', ');
        }
    function mnr($fx) {
        return $fx?->match_nr ? ('#'.$fx->match_nr) : '';
    }


    @endphp

    <!-- ============================================================
         MAIN DRAW (SF → FINAL)
         ============================================================ -->
    <g id="main-draw" transform="translate(0,0)">

        <!-- TITLE -->
        <text x="83" y="22" fill="black"
              style="font-family: Helvetica; font-size: 16px; font-weight: bold;">
            Main Draw
        </text>

        <!-- R1-M1 (SF1) -->
        <g>
            <text x="83" y="93" class="mnr">M-R1-M1({{mnr($sf1)}})</text>

            <line x1="108" y1="78" x2="258" y2="78" stroke="black"/>
            <line x1="108" y1="118" x2="258" y2="118" stroke="black"/>
            <line x1="258" y1="78" x2="258" y2="118" stroke="black"/>

            <text x="118" y="73" class="svg_name">{{ name1($sf1) }}</text>
            <text x="118" y="113" class="svg_name">{{ name2($sf1) }}</text>
            <text x="118" y="96"  class="score">{{ score($sf1) }}</text>
        </g>

        <!-- R1-M2 (SF2) -->
        <g>
            <text x="83" y="173" class="mnr">M-R1-M2 ({{mnr($sf2)}})</text>

            <line x1="108" y1="158" x2="258" y2="158" stroke="black"/>
            <line x1="108" y1="198" x2="258" y2="198" stroke="black"/>
            <line x1="258" y1="158" x2="258" y2="198" stroke="black"/>

            <text x="118" y="153" class="svg_name">{{ name1($sf2) }}</text>
            <text x="118" y="193" class="svg_name">{{ name2($sf2) }}</text>
            <text x="118" y="176" class="score">{{ score($sf2) }}</text>
        </g>

        <!-- R2-M3 (FINAL) -->
        <g>
            <text x="258" y="133" class="mnr">M-R2-M3 ({{mnr($final)}})</text>

            <line x1="258" y1="98"  x2="458" y2="98"  stroke="black"/>
            <line x1="258" y1="178" x2="458" y2="178" stroke="black"/>
            <line x1="458" y1="98"  x2="458" y2="178" stroke="black"/>

            <text x="268" y="93"  class="svg_name">{{ name1($final) }}</text>
            <text x="268" y="173" class="svg_name">{{ name2($final) }}</text>
            <text x="268" y="133" class="score">{{ score($final) }}</text>
        </g>

        <!-- WINNER LINE -->
        <line x1="458" y1="138" x2="658" y2="138" stroke="black"/>

        <text x="468" y="135" class="svg_name">
            {{ $final?->winner?->players?->pluck('full_name')->join(' / ') ?? '' }}
        </text>
    </g>

    {{-- ============================================================
         PLATE + CONSOLATION MAPPING + HELPERS
         ============================================================ --}}
    @php
        // PLATE FIXTURE MAPPING
        $m1    = $qf_plate[0] ?? null;
        $m2    = $qf_plate[1] ?? null;
        $m3    = $qf_plate[2] ?? null;
        $m4    = $qf_plate[3] ?? null;

        $sf1_fx   = $sf_plate[0] ?? null;
        $sf2_fx   = $sf_plate[1] ?? null;

        $final_fx = $final_plate ?? null;

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

        $scorePlate = function ($fx) {
            if (!$fx || !$fx->fixtureResults?->count()) return '';
            return $fx->fixtureResults->map(function ($r) {
                return $r->score1 . '-' . $r->score2;
            })->join(', ');
        };

        $sf1_loser = $sf1_fx?->loser_registration ?? null;
        $sf2_loser = $sf2_fx?->loser_registration ?? null;
    @endphp

    <!-- ============================================================
         PLATE + CONSOLATION (shifted down under Main)
         ============================================================ -->
    <g id="plate-draw" transform="translate(0,260)">
      <g>
        <text x="83" y="72" fill="black"
              style="font-family: Helvetica; font-size: 16px; font-weight: bold;">
          Plate Draw
        </text>

        <!-- ============================================================
             ROUND 1
             ============================================================ -->

        <!-- P-R1-M1 (#3007) -->
        <text x="83" y="163" class="mnr">P-R1-M1 (#3007)</text>
        <line x1="108" y1="148" x2="258" y2="148" stroke="black"/>
        <line x1="108" y1="188" x2="258" y2="188" stroke="black"/>
        <line x1="258" y1="148" x2="258" y2="188" stroke="black"/>
        <text x="118" y="143" class="name">{{ $name($m1, 1) }}</text>
        <text x="118" y="183" class="name">{{ $name($m1, 2) }}</text>
        <text x="120" y="166" class="score">{{ $scorePlate($m1) }}</text>

        <!-- P-R1-M2 (#3006) -->
        <text x="83" y="243" class="mnr">P-R1-M2 (#3006)</text>
        <line x1="108" y1="228" x2="258" y2="228" stroke="black"/>
        <line x1="108" y1="268" x2="258" y2="268" stroke="black"/>
        <line x1="258" y1="228" x2="258" y2="268" stroke="black"/>
        <text x="118" y="223" class="name">{{ $name($m2, 1) }}</text>
        <text x="118" y="263" class="name">{{ $name($m2, 2) }}</text>
        <text x="118" y="248" class="score">{{ $scorePlate($m2) }}</text>

        <!-- P-R1-M3 (#3005) -->
        <text x="83" y="323" class="mnr">P-R1-M3 (#3005)</text>
        <line x1="108" y1="308" x2="258" y2="308" stroke="black"/>
        <line x1="108" y1="348" x2="258" y2="348" stroke="black"/>
        <line x1="258" y1="308" x2="258" y2="348" stroke="black"/>
        <text x="118" y="303" class="name">{{ $name($m3, 1) }}</text>
        <text x="118" y="343" class="name">{{ $name($m3, 2) }}</text>
        <text x="118" y="328" class="score">{{ $scorePlate($m3) }}</text>

        <!-- P-R1-M4 (#3004) -->
        <text x="83" y="403" class="mnr">P-R1-M4 (#3004)</text>
        <line x1="108" y1="388" x2="258" y2="388" stroke="black"/>
        <line x1="108" y1="428" x2="258" y2="428" stroke="black"/>
        <line x1="258" y1="388" x2="258" y2="428" stroke="black"/>
        <text x="118" y="383" class="name">{{ $name($m4, 1) }}</text>
        <text x="118" y="423" class="name">{{ $name($m4, 2) }}</text>
        <text x="118" y="408" class="score">{{ $scorePlate($m4) }}</text>

        <!-- ============================================================
             ROUND 2
             ============================================================ -->

        <!-- P-R2-M5 (#3009) -->
        <text x="238" y="208" class="mnr">P-R2-M5 (#3009)</text>
        <line x1="258" y1="168" x2="458" y2="168" stroke="black"/>
        <line x1="258" y1="248" x2="458" y2="248" stroke="black"/>
        <line x1="458" y1="168" x2="458" y2="248" stroke="black"/>
        <text x="268" y="163" class="name">{{ $name($sf1_fx, 1) }}</text>
        <text x="268" y="243" class="name">{{ $name($sf1_fx, 2) }}</text>
        <text x="268" y="208" class="score">{{ $scorePlate($sf1_fx) }}</text>

        <!-- P-R2-M6 (#3008) -->
        <text x="238" y="368" class="mnr">P-R2-M6 (#3008)</text>
        <line x1="258" y1="328" x2="458" y2="328" stroke="black"/>
        <line x1="258" y1="408" x2="458" y2="408" stroke="black"/>
        <line x1="458" y1="328" x2="458" y2="408" stroke="black"/>
        <text x="268" y="323" class="name">{{ $name($sf2_fx, 1) }}</text>
        <text x="268" y="403" class="name">{{ $name($sf2_fx, 2) }}</text>
        <text x="268" y="368" class="score">{{ $scorePlate($sf2_fx) }}</text>

        <!-- ============================================================
             ROUND 3 FEED-IN
             ============================================================ -->

        <!-- P-R3-M7 (#4002) -->
        <text x="440" y="163" class="mnr">P-R3-M7 (#4002)</text>
        <line x1="658" y1="208" x2="658" y2="122" stroke="black"/>
        <line x1="458" y1="122" x2="658" y2="122" stroke="black"/>
        <text x="465" y="104" class="name">{{ $regName($main_sf_loser2) }}</text>

        <!-- P-R3-M8 (#4003) -->
        <text x="440" y="408" class="mnr">P-R3-M8 (#4003)</text>
        <line x1="458" y1="457" x2="658" y2="457" stroke="black"/>
        <line x1="657" y1="367" x2="658" y2="457" stroke="black"/>
        <text x="466" y="444" class="name">{{ $regName($main_sf_loser1) }}</text>

        <!-- ============================================================
             ROUND 4
             ============================================================ -->

        <!-- P-R4-M9 (#3010) (Plate Final) -->
        <text x="638" y="288" class="mnr">P-R4-M9 (#3010)</text>
        <line x1="458" y1="208" x2="658" y2="208" stroke="black"/>
        <line x1="458" y1="367" x2="658" y2="367" stroke="black"/>
        <text x="468" y="203" class="name">{{ $name($final_fx, 1) }}</text>
        <text x="466" y="361" class="name">{{ $name($final_fx, 2) }}</text>
        <text x="468" y="288" class="score">{{ $scorePlate($final_fx) }}</text>

        <!-- P-R4-M10 (#3011) (3rd/4th) -->
        <text x="638" y="528" class="mnr">P-R4-M10 (#3011)</text>
        <line x1="658" y1="508" x2="858" y2="508" stroke="black"/>
        <line x1="658" y1="548" x2="858" y2="548" stroke="black"/>
        <line x1="858" y1="508" x2="858" y2="548" stroke="black"/>
        <text x="668" y="503" class="name">{{ $regName($sf1_loser) }}</text>
        <text x="668" y="543" class="name">{{ $regName($sf2_loser) }}</text>
        <text x="668" y="523" class="score">{{ $scorePlate($plate_34) }}</text>

        <!-- WINNER LINE – P-R4-M10 (#3011) -->
        <line x1="858" y1="528" x2="1058" y2="528" stroke="black"/>

        <!-- P-R4-M11 (#3003) (7th/8th) -->
        <text x="238" y="528" class="mnr">P-R4-M11 (#3003)</text>
        <line x1="258" y1="508" x2="458" y2="508" stroke="black"/>
        <line x1="258" y1="548" x2="458" y2="548" stroke="black"/>
        <line x1="458" y1="508" x2="458" y2="548" stroke="black"/>
        <text x="268" y="503" class="name">{{ $regName($sf1_loser) }}</text>
        <text x="268" y="543" class="name">{{ $regName($sf2_loser) }}</text>
        <text x="268" y="523" class="score">{{ $scorePlate($plate_78 ?? null) }}</text>
        <line x1="458" y1="528" x2="558" y2="528" stroke="black"/>

        <!-- Static connectors -->
        <path fill="none" stroke="#000" d="m657.99998,412l198,0"/>
        <path fill="none" stroke="#000" d="m657.99998,164.99094l198,0"/>
        <line y2="411.99999" x2="855.99998" y1="165.09741" x1="855.99998" stroke="#000" fill="none"/>
        <line y2="289.32719" x2="1059.203" y1="287.55728" x1="856.54819" stroke="#000" fill="none"/>

        <!-- ============================================================
             CONSOLATION BRACKET (4 players)
             ============================================================ -->

        <!-- C-R1-M1 (#4004) (Consolation SF1) -->
        <g>
            <text x="83" y="693" class="mnr">C-R1-M1 (#4004)</text>

            <line x1="108" y1="678" x2="258" y2="678" stroke="black"/>
            <line x1="108" y1="718" x2="258" y2="718" stroke="black"/>
            <line x1="258" y1="678" x2="258" y2="718" stroke="black"/>

            <text x="118" y="673" class="name">{{ $regName($csf1?->registration1) }}</text>
            <text x="118" y="713" class="name">{{ $regName($csf1?->registration2) }}</text>
            <text x="118" y="696" class="score">{{ $scorePlate($csf1) }}</text>
        </g>

        <!-- C-R1-M2 (#4005) (Consolation SF2) -->
        <g>
            <text x="83" y="773" class="mnr">C-R1-M2 (#4005)</text>

            <line x1="108" y1="758" x2="258" y2="758" stroke="black"/>
            <line x1="108" y1="798" x2="258" y2="798" stroke="black"/>
            <line x1="258" y1="758" x2="258" y2="798" stroke="black"/>

            <text x="118" y="753" class="name">{{ $regName($csf2?->registration1) }}</text>
            <text x="118" y="793" class="name">{{ $regName($csf2?->registration2) }}</text>
            <text x="118" y="776" class="score">{{ $scorePlate($csf2) }}</text>
        </g>

        <!-- C-R2-M3 (#4006) (Consolation FINAL) -->
        <g>
            <text x="258" y="733" class="mnr">C-R2-M3 (#4006)</text>

            <line x1="258" y1="698" x2="458" y2="698" stroke="black"/>
            <line x1="258" y1="778" x2="458" y2="778" stroke="black"/>
            <line x1="458" y1="698" x2="458" y2="778" stroke="black"/>

            <text x="268" y="693" class="name">{{ $name($cfinal, 1) }}</text>
            <text x="268" y="773" class="name">{{ $name($cfinal, 2) }}</text>
            <text x="268" y="733" class="score">{{ $scorePlate($cfinal) }}</text>
        </g>

        <!-- WINNER LINE — Consolation Final -->
        <line x1="458" y1="738" x2="658" y2="738" stroke="black"/>

        <text x="468" y="735" class="name">
            {{ $cfinal?->winner?->players?->pluck('full_name')->join(' / ') ?? '' }}
        </text>

        <!-- C-R2-M4 (#4007) (Consolation 3rd/4th Playoff) -->
        <g>
            <text x="83" y="853" class="mnr">C-R2-M4 (#4007)</text>

            <line x1="108" y1="838" x2="258" y2="838" stroke="black"/>
            <line x1="108" y1="878" x2="258" y2="878" stroke="black"/>
            <line x1="258" y1="838" x2="258" y2="878" stroke="black"/>

            <text x="118" y="833" class="name">{{ $name($csf1?->loser_fixture ?? null, 1) }}</text>
            <text x="118" y="873" class="name">{{ $name($csf2?->loser_fixture ?? null, 2) }}</text>
            <text x="118" y="856" class="score">
                {{ $scorePlate($c34 ?? null) }}
            </text>
        </g>

        <!-- WINNER LINE — Consolation 3rd/4th -->
        <line x1="258" y1="858" x2="458" y2="858" stroke="black"/>

      </g>
    </g>


