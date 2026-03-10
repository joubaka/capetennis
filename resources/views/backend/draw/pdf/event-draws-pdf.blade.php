<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; padding: 12px; color: #000; font-size: 11px; }
  h1 { font-size: 16px; margin-bottom: 4px; }
  h2 { font-size: 13px; color: #333; margin-bottom: 10px; border-bottom: 1px solid #999; padding-bottom: 3px; }
  h3 { font-size: 12px; margin: 12px 0 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
  th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; }
  th { background: #333; color: #fff; font-weight: 600; }
  .text-center { text-align: center; }
  .fw-bold { font-weight: bold; }
  .text-success { color: #198754; }
  .page-break { page-break-before: always; }
  .no-break { page-break-inside: avoid; }
  .feeder-win { color: #0d6efd; font-size: 9px; }
  .feeder-lose { color: #e65100; font-size: 9px; }

  .rr-matrix-table { border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
  .rr-matrix-table td, .rr-matrix-table th { border: 1px solid #999; padding: 3px 2px; text-align: center; font-size: 8px; white-space: nowrap; overflow: hidden; }
  .rr-matrix-table thead th { background: #fff; color: #0a3566; border: 2px solid #0a3566; font-weight: 700; }
  .rr-matrix-table tbody th { background: #fff; color: #0b722e; border: 2px solid #0b722e; font-weight: 700; text-align: left; font-size: 8px; }
  .rr-win { color: #00a859; font-weight: bold; }
  .rr-loss { color: #d32f2f; font-weight: bold; }
  .bg-diagonal { background: #000 !important; }
  .wins-col { font-weight: 800; font-size: 10px; background: #f0fdf4; color: #198754; text-align: center; }

  .standings-table { width: auto; margin-top: 8px; page-break-inside: avoid; }
  .standings-table th { background: #f5f5f5; color: #222; border: 1px solid #666; font-weight: 700; }
  .standings-table td { border: 1px solid #666; }
</style>
</head>
<body>

<h1>{{ $event->name }}</h1>

@php
  $stageLabels = ['RR' => 'Round Robin', 'MAIN' => 'Main Draw', 'PLATE' => 'Plate', 'CONS' => 'Consolation', 'BOWL' => 'Bowl', 'SHIELD' => 'Shield', 'SPOON' => 'Spoon'];
@endphp

@foreach($draws as $idx => $draw)

  @if($idx > 0)
    <div class="page-break"></div>
  @endif

  <h2>{{ $draw['name'] }}</h2>

  {{-- MATRIX --}}
  @if(in_array($printType, ['matrix', 'combined']))
    @php
      $groups = collect($draw['groups'])->sortBy('name')->values();
      $rrFixtures = $draw['rrFixtures'] ?? [];
    @endphp

    @foreach($groups as $group)
      @php
        $players = collect($group['registrations'])->map(function ($r) {
          return ['id' => $r['id'], 'name' => $r['display_name'] ?? 'N/A', 'seed' => $r['pivot']['seed'] ?? 999];
        })->sortBy('seed')->values();
        $gFixtures = $rrFixtures[$group['id']] ?? [];
      @endphp

      <h3>Box {{ $group['name'] }}</h3>
      <table class="rr-matrix-table">
        <thead>
          <tr>
            <th style="width:120px;"></th>
            @foreach($players as $p)
              <th>{{ $p['name'] }}</th>
            @endforeach
            <th style="width:30px; background:#198754; color:#fff; font-weight:800;">W</th>
          </tr>
        </thead>
        <tbody>
          @foreach($players as $rowP)
            <tr>
              <th>{{ $rowP['name'] }}</th>
              @foreach($players as $colP)
                @if($rowP['id'] === $colP['id'])
                  <td class="bg-diagonal"></td>
                @else
                  @php
                    $fx = collect($gFixtures)->first(function ($f) use ($rowP, $colP) {
                      return ($f['r1_id'] == $rowP['id'] && $f['r2_id'] == $colP['id'])
                          || ($f['r1_id'] == $colP['id'] && $f['r2_id'] == $rowP['id']);
                    });
                    $cellHtml = '';
                    $cellClass = '';
                    if ($fx && !empty($fx['all_sets'])) {
                      $display = [];
                      foreach ($fx['all_sets'] as $set) {
                        $parts = array_map('intval', explode('-', $set));
                        $display[] = ($fx['r1_id'] == $rowP['id'])
                          ? $parts[0].'-'.$parts[1]
                          : $parts[1].'-'.$parts[0];
                      }
                      $last = array_map('intval', explode('-', end($display)));
                      $cellClass = $last[0] > $last[1] ? 'rr-win' : ($last[1] > $last[0] ? 'rr-loss' : '');
                      $cellHtml = implode(', ', $display);
                    }
                  @endphp
                  <td class="{{ $cellClass }}">{{ $cellHtml }}</td>
                @endif
              @endforeach
              @php
                $rowWins = 0;
                foreach ($gFixtures as $f) {
                  if (empty($f['all_sets'])) continue;
                  $last = array_map('intval', explode('-', end($f['all_sets'])));
                  if ($f['r1_id'] == $rowP['id'] && $last[0] > $last[1]) $rowWins++;
                  if ($f['r2_id'] == $rowP['id'] && $last[1] > $last[0]) $rowWins++;
                }
              @endphp
              <td class="wins-col">{{ $rowWins }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endforeach

    {{-- Standings --}}
    @if($withStandings)
      @php $standings = $draw['standings'] ?? []; @endphp
      @foreach($groups as $group)
        @if(isset($standings[$group['id']]))
          @php
            $rows = collect($standings[$group['id']])->sort(function ($a, $b) {
              if ($a['wins'] !== $b['wins']) return $b['wins'] <=> $a['wins'];
              $aTotalSets = $a['sets_won'] + $a['sets_lost'];
              $bTotalSets = $b['sets_won'] + $b['sets_lost'];
              $aSetsPct = $aTotalSets > 0 ? $a['sets_won'] / $aTotalSets : 0;
              $bSetsPct = $bTotalSets > 0 ? $b['sets_won'] / $bTotalSets : 0;
              if (abs($aSetsPct - $bSetsPct) > 0.0001) return $bSetsPct <=> $aSetsPct;
              $aTotalGames = ($a['games_won'] ?? 0) + ($a['games_lost'] ?? 0);
              $bTotalGames = ($b['games_won'] ?? 0) + ($b['games_lost'] ?? 0);
              $aGamesPct = $aTotalGames > 0 ? ($a['games_won'] ?? 0) / $aTotalGames : 0;
              $bGamesPct = $bTotalGames > 0 ? ($b['games_won'] ?? 0) / $bTotalGames : 0;
              return $bGamesPct <=> $aGamesPct;
            })->values();
          @endphp
          <h3>Box {{ $group['name'] }} — Standings</h3>
          <table class="standings-table">
            <thead><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets %</th><th>Games %</th></tr></thead>
            <tbody>
              @foreach($rows as $i => $r)
                @php
                  $totalSets = $r['sets_won'] + $r['sets_lost'];
                  $setsPct = $totalSets > 0 ? round(($r['sets_won'] / $totalSets) * 100) . '%' : '-';
                  $totalGames = ($r['games_won'] ?? 0) + ($r['games_lost'] ?? 0);
                  $gamesPct = $totalGames > 0 ? round((($r['games_won'] ?? 0) / $totalGames) * 100) . '%' : '-';
                @endphp
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td>{{ $r['player'] }}</td>
                  <td>{{ $r['wins'] }}</td>
                  <td>{{ $r['losses'] }}</td>
                  <td>{{ $setsPct }}</td>
                  <td>{{ $gamesPct }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      @endforeach
    @endif
  @endif

  {{-- FIXTURES --}}
  @if(in_array($printType, ['fixtures', 'combined']))
    @php
      $oops = collect($draw['oops'] ?? []);
      $grouped = $oops->groupBy(fn($fx) => $fx['stage'] ?? 'RR');
      $stageOrder = ['RR', 'MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'];

      // Feeder label helper
      $feeder = function ($fx, $slot) {
        if (($fx['stage'] ?? 'RR') === 'RR') return '';
        $wf = $fx['winner_feeders'] ?? [];
        $lf = $fx['loser_feeders'] ?? [];
        $idx = ($slot === 'home') ? 0 : 1;
        $name = ($slot === 'home') ? ($fx['home'] ?? '') : ($fx['away'] ?? '');
        if ($name && $name !== 'TBD' && $name !== '---') return '';
        if (count($wf) >= 2) return '<span class="feeder-win">W' . $wf[$idx] . '</span>';
        if (count($wf) === 1 && count($lf) >= 1) {
          return $idx === 0
            ? '<span class="feeder-win">W' . $wf[0] . '</span>'
            : '<span class="feeder-lose">L' . $lf[0] . '</span>';
        }
        if (count($lf) >= 2) return '<span class="feeder-lose">L' . $lf[$idx] . '</span>';
        if (count($lf) === 1 && $idx === 0) return '<span class="feeder-lose">L' . $lf[0] . '</span>';
        return '';
      };
    @endphp

    @foreach($stageOrder as $stage)
      @if($grouped->has($stage))
        <h3>{{ $stageLabels[$stage] ?? $stage }}</h3>
        <table>
          <thead>
            <tr>
              <th style="width:30px;">M#</th>
              <th>Player 1</th>
              <th style="width:24px;" class="text-center">vs</th>
              <th>Player 2</th>
              <th style="width:30px;" class="text-center">Rd</th>
              <th style="width:80px;" class="text-center">Score</th>
            </tr>
          </thead>
          <tbody>
            @foreach($grouped[$stage] as $fx)
              @php
                $home = $fx['home'] ?? '---';
                $away = $fx['away'] ?? '---';
                $homeFeed = $feeder($fx, 'home');
                $awayFeed = $feeder($fx, 'away');
                if ($homeFeed) $home = $homeFeed;
                if ($awayFeed) $away = $awayFeed;
              @endphp
              <tr>
                <td>{{ $fx['match_nr'] ?? $fx['id'] }}</td>
                <td class="{{ $fx['winner'] == $fx['r1_id'] ? 'fw-bold text-success' : '' }}">
                  {!! $home !!}
                  @if($fx['playoff_type']) <small style="color:#666;">({{ $fx['playoff_type'] }})</small> @endif
                </td>
                <td class="text-center">vs</td>
                <td class="{{ $fx['winner'] == $fx['r2_id'] ? 'fw-bold text-success' : '' }}">
                  {!! $away !!}
                </td>
                <td class="text-center">{{ $fx['round'] ?? '' }}</td>
                <td class="text-center">{{ $fx['score'] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    @endforeach
  @endif

@endforeach

</body>
</html>
