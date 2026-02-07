<style>
    .matrix-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
    }

    .matrix-box {
        flex: 0 0 48%;
        border: 1px solid #ccc;
        padding: 10px;
        background-color: #fdfdfd;
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.1);
    }

    @media print {
    /* Existing styles (optional reference) */
    .btn, .nav, .nav-tabs, .card-header, .card-footer, .form-control, select, label {
      display: none !important;
    }

    /* New additions to hide menu and user icon */
    .layout-navbar,      /* top bar */
    .layout-menu-toggle, /* mobile toggle button */
    .layout-menu,        /* left menu */
    .user-dropdown,      /* avatar icon (SU) */
    .header-navbar,      /* full top header if needed */
    .navbar-nav,         /* nav items like user dropdown */
    .dropdown,           /* any dropdowns */
    .nav-item.dropdown,
    .nav-link.dropdown-toggle {
      display: none !important;
    }

    /* Optional cleanup */
    body {
      background: white;
    }

    .layout-content-wrapper {
      margin-left: 0 !important;
    }
        .matrix-grid {
      page-break-inside: avoid;
    }

    .matrix-box {
      break-inside: avoid;
      page-break-inside: avoid;
    }

    /* Add page break after last box (if needed) */
    .matrix-box:last-child {
      page-break-after: always;
    }

  }
</style>

@php
    $boxes = $draw->registrations->filter(fn($r) => $r->pivot->box_number)->groupBy(fn($r) => $r->pivot->box_number);
@endphp
<div class="text-end mb-3">
    <button onclick="window.print()" class="btn btn-outline-primary">
        🖨️ Print Boxes
    </button>
</div>

<div class="matrix-grid mt-4">

    @foreach ($boxes as $boxNumber => $registrations)
        @php
            $players = $registrations->map(fn($r) => $r->players->first()?->full_name ?? 'TBD')->values();
            $playerMap = $registrations->mapWithKeys(fn($r, $i) => [$r->id => $players[$i]]);
            $numPlayers = $players->count();
            $cellWidth = 70;
            $cellHeight = 30;
            $offsetX = 160;
            $offsetY = 60;
            $labelHeight = 50;
            $svgWidth = $offsetX + ($numPlayers + 2) * $cellWidth + 20;
            $svgHeight = $labelHeight + $offsetY + $numPlayers * $cellHeight + 20;

            $boxFixtures = $draw->drawFixtures->filter(fn($f) => $f->draw_group_id == $boxNumber);

            // calculate player stats
            $stats = [];
            foreach ($registrations as $reg) {
                $rid = $reg->id;
                $wins = 0;
                $games = 0;

                foreach ($boxFixtures as $f) {
                    foreach ($f->fixtureResults as $r) {
                        if ($r->winner_registration === $rid) {
                            $wins++;
                        }

                        if ($f->registration1_id === $rid) {
                            $games += $r->registration1_score;
                        } elseif ($f->registration2_id === $rid) {
                            $games += $r->registration2_score;
                        }
                    }
                }
                $stats[$rid] = ['wins' => $wins, 'games' => $games];
            }

            // sort by wins, then games, then head-to-head
            $rankings = collect($stats)
                ->map(fn($s, $rid) => ['rid' => $rid] + $s)
                ->sort(function ($a, $b) use ($boxFixtures) {
                    if ($a['wins'] !== $b['wins']) {
                        return $b['wins'] <=> $a['wins'];
                    }
                    if ($a['games'] !== $b['games']) {
                        return $b['games'] <=> $a['games'];
                    }

                    $fixture = $boxFixtures->first(function ($f) use ($a, $b) {
                        return ($f->registration1_id === $a['rid'] && $f->registration2_id === $b['rid']) ||
                            ($f->registration1_id === $b['rid'] && $f->registration2_id === $a['rid']);
                    });

                    if ($fixture && $fixture->fixtureResults->first()) {
                        return $fixture->fixtureResults->first()->winner_registration === $a['rid'] ? -1 : 1;
                    }

                    return 0;
                })
                ->values()
                ->pluck('rid')
                ->flip();
        @endphp

        <div class="matrix-box" id="box-matrix-{{ $boxNumber }}">
            <h6 class="text-center">Box {{ $boxNumber }}</h6>

            <svg width="{{ $svgWidth }}" height="{{ $svgHeight }}" style="overflow: visible;"
                xmlns="http://www.w3.org/2000/svg">
                {{-- Column headers --}}
                @foreach ($players as $i => $name)
                    @php $textX = $offsetX + $i * $cellWidth + 20; @endphp
                    <text x="{{ $textX }}" y="45" transform="rotate(-45 {{ $textX }},45)" font-size="12"
                        font-family="Helvetica">
                        {{ Str::limit($name, 14) }}
                    </text>
                @endforeach

                {{-- Extra headers --}}
                @php
                    $totalX = $offsetX + $numPlayers * $cellWidth;
                    $posX = $totalX + $cellWidth;
                @endphp
                <text x="{{ $totalX + 5 }}" y="25" font-size="12" font-family="Helvetica">M/G</text>
                <text x="{{ $posX + 5 }}" y="25" font-size="12" font-family="Helvetica">Position</text>

                @foreach ($players as $row => $rowName)
                    @php $rowRegId = $registrations[$row]->id; @endphp
                    <text x="10" y="{{ $offsetY + $row * $cellHeight + 20 }}" font-size="12" font-family="Helvetica">
                        {{ $rowName }}
                    </text>

                    @foreach ($players as $col => $colName)
                        @php
                            $colRegId = $registrations[$col]->id;
                            $x = $offsetX + $col * $cellWidth;
                            $y = $offsetY + $row * $cellHeight;
                            $isDiagonal = $row === $col;

                            $fixture = $boxFixtures->first(function ($f) use ($rowRegId, $colRegId) {
                                return ($f->registration1_id === $rowRegId && $f->registration2_id === $colRegId) ||
                                    ($f->registration1_id === $colRegId && $f->registration2_id === $rowRegId);
                            });

                            $score = '';
                            if ($fixture && $fixture->fixtureResults->count()) {
                                $score = $fixture->fixtureResults
                                    ->map(function ($r) use ($fixture, $rowRegId) {
                                        return $fixture->registration1_id === $rowRegId
                                            ? "{$r->registration1_score}-{$r->registration2_score}"
                                            : "{$r->registration2_score}-{$r->registration1_score}";
                                    })
                                    ->implode(', ');
                            }
                        @endphp

                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $cellWidth }}"
                            height="{{ $cellHeight }}" fill="{{ $isDiagonal ? '#000' : '#fff' }}" stroke="#000" />

                        @if (!$isDiagonal)
                            <text x="{{ $x + 5 }}" y="{{ $y + 20 }}" font-size="12"
                                font-family="Helvetica">
                                {{ $score ?: '-' }}
                            </text>
                        @endif
                    @endforeach

                    {{-- Match/Games --}}
                    @php
                        $x = $totalX;
                        $y = $offsetY + $row * $cellHeight;
                        $summary = $stats[$rowRegId]['wins'] . ' / ' . $stats[$rowRegId]['games'];
                    @endphp
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $cellWidth }}"
                        height="{{ $cellHeight }}" fill="#f0f0f0" stroke="#000" />
                    <text x="{{ $x + 5 }}" y="{{ $y + 20 }}" font-size="12" font-family="Helvetica">
                        {{ $summary }}
                    </text>

                    {{-- Position --}}
                    @php
                        $x = $posX;
                        $rank = $rankings[$rowRegId] + 1;
                    @endphp
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $cellWidth }}"
                        height="{{ $cellHeight }}" fill="#d5fcd5" stroke="#000" />
                    <text x="{{ $x + 20 }}" y="{{ $y + 20 }}" font-size="12" font-family="Helvetica">
                        {{ $rank }}
                    </text>
                @endforeach
            </svg>
        </div>
    @endforeach
</div>
