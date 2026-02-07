@php
  $registrations = $registrations->values(); // Ensure numeric indexes
  $players = $registrations->map(fn($r) => $r->players->first()?->full_name ?? 'TBD')->values();
  $numPlayers = $players->count();
  $cellWidth = 70;
  $cellHeight = 30;
  $offsetX = 160;
  $offsetY = 40;
  $svgWidth = $offsetX + ($numPlayers + 2) * $cellWidth + 30; // +2 for Total & Position
  $svgHeight = $offsetY + $numPlayers * $cellHeight + 40;

  $boxFixtures = $draw->drawFixtures->filter(fn($f) => $f->draw_group_id == $boxNumber);

  // Pre-compute match and game wins
  $playerStats = collect();
  foreach ($registrations as $reg) {
    $regId = $reg->id;
    $matchWins = 0;
    $gameWins = 0;

    foreach ($boxFixtures as $fixture) {
      foreach ($fixture->fixtureResults as $result) {
        if ($result->winner_registration === $regId) $matchWins++;
        if ($fixture->registration1_id === $regId) {
          $gameWins += $result->registration1_score;
        } elseif ($fixture->registration2_id === $regId) {
          $gameWins += $result->registration2_score;
        }
      }
    }

    $playerStats->push([
      'reg_id' => $regId,
      'matchWins' => $matchWins,
      'gameWins' => $gameWins,
      'name' => $reg->players->first()?->full_name ?? 'TBD',
    ]);
  }

  // Sort by matchWins DESC, then gameWins DESC, then head-to-head
  $sorted = $playerStats->sort(function ($a, $b) use ($boxFixtures) {
    if ($a['matchWins'] !== $b['matchWins']) {
      return $b['matchWins'] <=> $a['matchWins'];
    }
    if ($a['gameWins'] !== $b['gameWins']) {
      return $b['gameWins'] <=> $a['gameWins'];
    }

    // Tie-break: head-to-head winner
    $fixture = $boxFixtures->first(function ($f) use ($a, $b) {
      return ($f->registration1_id === $a['reg_id'] && $f->registration2_id === $b['reg_id']) ||
             ($f->registration1_id === $b['reg_id'] && $f->registration2_id === $a['reg_id']);
    });

    if ($fixture && $fixture->fixtureResults->count()) {
      $winner = $fixture->fixtureResults->last()?->winner_registration;
      if ($winner === $a['reg_id']) return -1;
      if ($winner === $b['reg_id']) return 1;
    }

    return 0;
  })->values();

  // Assign positions
  $positionMap = $sorted->pluck('reg_id')->flip()->map(fn($i) => $i + 1);
@endphp

<div class="matrix-box" id="box-matrix-{{ $boxNumber }}">
  <h6 class="text-center">Box {{ $boxNumber }}</h6>

  @if ($numPlayers === 0)
    <p class="text-muted text-center">No players in this box.</p>
  @else
    <svg width="{{ $svgWidth }}" height="{{ $svgHeight }}" xmlns="http://www.w3.org/2000/svg">
      {{-- Column Headers --}}
      @foreach ($players as $i => $name)
        <text x="{{ $offsetX + $i * $cellWidth + 10 }}" y="25"
              font-size="12" font-family="Helvetica"
              transform="rotate(-45 {{ $offsetX + $i * $cellWidth + 10 }},25)">
          {{ \Illuminate\Support\Str::limit($name, 10) }}
        </text>
      @endforeach

      <text x="{{ $offsetX + $numPlayers * $cellWidth + 5 }}" y="15" font-size="12">M/G</text>
      <text x="{{ $offsetX + ($numPlayers + 1) * $cellWidth + 5 }}" y="15" font-size="12">Position</text>

      {{-- Rows --}}
      @foreach ($players as $row => $rowName)
        @php
          $rowRegId = $registrations[$row]->id;
          $matchWins = $playerStats->firstWhere('reg_id', $rowRegId)['matchWins'];
          $gameWins = $playerStats->firstWhere('reg_id', $rowRegId)['gameWins'];
          $position = $positionMap[$rowRegId];
        @endphp

        {{-- Row label --}}
        <text x="10" y="{{ $offsetY + $row * $cellHeight + 20 }}" font-size="12">{{ $rowName }}</text>

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
              $score = $fixture->fixtureResults->map(function ($r) use ($fixture, $rowRegId) {
                return $fixture->registration1_id === $rowRegId
                  ? "{$r->registration1_score}-{$r->registration2_score}"
                  : "{$r->registration2_score}-{$r->registration1_score}";
              })->implode(', ');
            }
          @endphp

          <rect x="{{ $x }}" y="{{ $y }}" width="{{ $cellWidth }}" height="{{ $cellHeight }}"
                fill="{{ $isDiagonal ? '#000' : '#fff' }}" stroke="#000" />

          @if (!$isDiagonal)
            <text x="{{ $x + 5 }}" y="{{ $y + 20 }}" font-size="12">{{ $score ?: '-' }}</text>
          @endif
        @endforeach

        {{-- Total cell --}}
        @php $x = $offsetX + $numPlayers * $cellWidth; @endphp
        <rect x="{{ $x }}" y="{{ $offsetY + $row * $cellHeight }}" width="{{ $cellWidth }}" height="{{ $cellHeight }}" fill="#eee" stroke="#000" />
        <text x="{{ $x + 5 }}" y="{{ $offsetY + $row * $cellHeight + 20 }}" font-size="12">{{ "{$matchWins} / {$gameWins}" }}</text>

        {{-- Position cell --}}
        @php $x = $offsetX + ($numPlayers + 1) * $cellWidth; @endphp
        <rect x="{{ $x }}" y="{{ $offsetY + $row * $cellHeight }}" width="{{ $cellWidth }}" height="{{ $cellHeight }}" fill="#cfc" stroke="#000" />
        <text x="{{ $x + 20 }}" y="{{ $offsetY + $row * $cellHeight + 20 }}" font-size="12">{{ $position }}</text>
      @endforeach
    </svg>
  @endif
</div>
