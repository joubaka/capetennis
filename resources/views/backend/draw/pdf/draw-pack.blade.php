<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $event->name }} - Draw Pack</title>
  <style>
    @page { size: A4 landscape; margin: 11mm 10mm 13mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5pt; line-height: 1.3; }
    h1, h2, h3, p { margin-top: 0; }
    h1 { font-size: 25pt; line-height: 1.05; margin-bottom: 4mm; }
    h2 { font-size: 15pt; color: #163a64; margin-bottom: 3mm; }
    h3 { font-size: 10pt; color: #163a64; margin: 4mm 0 2mm; }
    small, .muted { color: #657184; }
    .page { page-break-before: always; }
    .cover { page-break-before: auto; padding: 10mm; border: 1.5pt solid #163a64; }
    .cover-kicker { color: #237f69; font-size: 10pt; font-weight: 700; letter-spacing: 1.6pt; text-transform: uppercase; }
    .cover-rule { width: 28mm; border-top: 3pt solid #237f69; margin: 8mm 0; }
    .event-dates { font-size: 12pt; margin-bottom: 10mm; }
    .stats { width: 100%; border-collapse: separate; border-spacing: 3mm; margin: 0 -3mm 8mm; }
    .stats td { width: 25%; padding: 5mm; border: 1pt solid #cdd5df; background: #f5f8fb; }
    .stats strong { display: block; font-size: 18pt; color: #163a64; }
    .contents { margin-top: 5mm; }
    .contents-row { break-inside: avoid; padding: 1.8mm 0; border-bottom: .5pt solid #d8dee7; }
    .section-head { display: table; width: 100%; padding-bottom: 2.5mm; border-bottom: 1.5pt solid #163a64; margin-bottom: 3mm; }
    .section-head > div { display: table-cell; vertical-align: bottom; }
    .section-meta { text-align: right; color: #657184; }
    table.data { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4mm; }
    table.data th, table.data td { border: .6pt solid #8d98a8; padding: 1.8mm 1.5mm; vertical-align: middle; }
    table.data th { color: #fff; background: #163a64; font-size: 7.6pt; text-align: left; }
    table.data tbody tr:nth-child(even) td { background: #f6f8fa; }
    table.data tr { page-break-inside: avoid; }
    .center { text-align: center !important; }
    .nowrap { white-space: nowrap; }
    .result-cell { min-height: 7mm; font-weight: 700; }
    .stage-label { color: #405064; font-size: 6.8pt; font-weight: 700; }
    .empty-note { padding: 8mm; border: 1pt dashed #9eabba; text-align: center; color: #657184; }
    .matrix-wrap { page-break-inside: avoid; }
    table.matrix { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4mm; }
    table.matrix th, table.matrix td { border: .6pt solid #8d98a8; padding: 1.2mm .8mm; height: 8mm; text-align: center; font-size: 7pt; overflow: hidden; }
    table.matrix thead th { color: #163a64; background: #edf3f8; }
    table.matrix tbody th { width: 34mm; color: #155f50; background: #edf7f3; text-align: left; }
    table.matrix .diagonal { background: #293443; }
    .standing { width: auto !important; min-width: 125mm; }
    .standing th, .standing td { padding: 1.4mm 2mm !important; }
    .pack-warning { margin: 3mm 0; padding: 3mm; border-left: 3pt solid #d49b22; background: #fff8e7; }
    .footer { position: fixed; left: 0; right: 0; bottom: -8mm; color: #7b8593; font-size: 7pt; border-top: .5pt solid #ccd3dc; padding-top: 1.5mm; }
    .footer .page-number { float: right; }
    .footer .page-number:after { content: counter(page); }
    .no-print { position: fixed; top: 12px; right: 12px; z-index: 5; }
    .no-print button { border: 0; border-radius: 5px; padding: 10px 16px; color: #fff; background: #163a64; font: 600 14px Arial, sans-serif; cursor: pointer; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
@if($autoPrint)
  <div class="no-print"><button type="button" onclick="window.print()">Print draw pack</button></div>
@endif

<div class="footer">
  {{ $event->name }} - Draw Pack - Generated {{ now()->format('d M Y H:i') }}
  <span class="page-number">Page </span>
</div>

@php
  $totalMatches = $draws->sum(fn ($draw) => count($draw['oops']));
  $scheduledMatches = $schedule->count();
  $unscheduledMatches = $totalMatches - $scheduledMatches;
  $eventDate = $event->start_date
    ? $event->start_date->format('d M Y').($event->end_date && !$event->end_date->equalTo($event->start_date) ? ' - '.$event->end_date->format('d M Y') : '')
    : 'Dates to be confirmed';
  $stageLabels = [
    'RR' => 'Round Robin', 'FM' => 'Flexible Monrad', 'MAIN' => 'Main Draw',
    'PLATE' => 'Plate', 'CONS' => 'Consolation', 'BOWL' => 'Bowl',
    'SHIELD' => 'Shield', 'SPOON' => 'Spoon',
  ];
@endphp

<section class="cover">
  <p class="cover-kicker">Complete Draw Pack</p>
  <h1>{{ $event->name }}</h1>
  <div class="cover-rule"></div>
  <p class="event-dates">{{ $eventDate }}</p>

  <table class="stats" role="presentation">
    <tr>
      <td><strong>{{ $draws->count() }}</strong><span>Draws</span></td>
      <td><strong>{{ $totalMatches }}</strong><span>Total matches</span></td>
      <td><strong>{{ $scheduledMatches }}</strong><span>Scheduled</span></td>
      <td><strong>{{ $unscheduledMatches }}</strong><span>Still to schedule</span></td>
    </tr>
  </table>

  @if($unscheduledMatches > 0)
    <div class="pack-warning"><strong>Scheduling check:</strong> {{ $unscheduledMatches }} {{ Str::plural('match', $unscheduledMatches) }} in this pack {{ $unscheduledMatches === 1 ? 'is' : 'are' }} not yet assigned a date, venue and court.</div>
  @endif

  <h2>Pack contents</h2>
  <div class="contents">
    <div class="contents-row"><strong>Master order of play</strong><br><small>All selected draws, grouped by day and venue</small></div>
    @foreach($draws as $draw)
      <div class="contents-row"><strong>{{ $draw['name'] }}</strong><br><small>{{ $draw['format'] }} - {{ count($draw['oops']) }} {{ Str::plural('match', count($draw['oops'])) }}</small></div>
    @endforeach
  </div>
</section>

@if($schedule->isNotEmpty())
  @foreach($schedule->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture['scheduled_at'])->format('Y-m-d')) as $date => $dayFixtures)
    @foreach($dayFixtures->groupBy(fn ($fixture) => $fixture['venue'] ?: 'Venue to be confirmed') as $venue => $venueFixtures)
      <section class="page">
        <header class="section-head">
          <div><p class="cover-kicker">Master order of play</p><h2>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</h2></div>
          <div class="section-meta"><strong>{{ $venue }}</strong><br>{{ $venueFixtures->count() }} {{ Str::plural('match', $venueFixtures->count()) }}</div>
        </header>
        <table class="data">
          <thead><tr><th style="width:10%">Time</th><th style="width:9%">Court</th><th style="width:17%">Draw</th><th style="width:8%">Match</th><th style="width:23%">Player 1</th><th style="width:23%">Player 2</th><th style="width:10%">Result</th></tr></thead>
          <tbody>
          @foreach($venueFixtures as $fixture)
            <tr>
              <td class="nowrap"><strong>{{ \Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i') }}</strong>@if($fixture['duration'])<br><small>{{ $fixture['duration'] }} min</small>@endif</td>
              <td>{{ $fixture['court'] ?: 'TBA' }}</td>
              <td>{{ $fixture['draw_name'] }}</td>
              <td><span class="stage-label">{{ $stageLabels[$fixture['stage']] ?? ($fixture['stage'] ?: 'Draw') }}</span><br>M{{ $fixture['match_nr'] ?? $fixture['id'] }}</td>
              <td>{{ $fixture['home'] ?: 'TBD' }}</td>
              <td>{{ $fixture['away'] ?: 'TBD' }}</td>
              <td class="result-cell">{{ $fixture['score'] }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </section>
    @endforeach
  @endforeach
@else
  <section class="page">
    <header class="section-head"><div><p class="cover-kicker">Master order of play</p><h2>Schedule</h2></div></header>
    <div class="empty-note">No selected matches have an applied date, venue and court yet.</div>
  </section>
@endif

@foreach($draws as $draw)
  <section class="page">
    <header class="section-head">
      <div><p class="cover-kicker">Draw sheet</p><h2>{{ $draw['name'] }}</h2></div>
      <div class="section-meta"><strong>{{ $draw['format'] }}</strong><br>{{ count($draw['oops']) }} {{ Str::plural('match', count($draw['oops'])) }}</div>
    </header>

    @if(count($draw['groups']))
      @foreach(collect($draw['groups'])->sortBy('name') as $group)
        @php
          $players = collect($group['registrations'])->sortBy(fn ($registration) => $registration['pivot']['seed'] ?? 9999)->values();
          $groupFixtures = collect($draw['rrFixtures'][$group['id']] ?? []);
        @endphp
        <div class="matrix-wrap">
          <h3>Box {{ $group['name'] }} - Results matrix</h3>
          <table class="matrix">
            <thead><tr><th>Player</th>@foreach($players as $player)<th>{{ $player['display_name'] }}</th>@endforeach<th>W</th></tr></thead>
            <tbody>
            @foreach($players as $rowPlayer)
              <tr>
                <th>{{ $rowPlayer['display_name'] }}</th>
                @foreach($players as $columnPlayer)
                  @if($rowPlayer['id'] === $columnPlayer['id'])
                    <td class="diagonal"></td>
                  @else
                    @php
                      $result = $groupFixtures->first(fn ($fixture) =>
                        ($fixture['r1_id'] === $rowPlayer['id'] && $fixture['r2_id'] === $columnPlayer['id']) ||
                        ($fixture['r1_id'] === $columnPlayer['id'] && $fixture['r2_id'] === $rowPlayer['id'])
                      );
                    @endphp
                    <td>{{ $result['score'] ?? '' }}</td>
                  @endif
                @endforeach
                <td></td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endforeach
    @endif

    @if(count($draw['oops']))
      @foreach(collect($draw['oops'])->groupBy(fn ($fixture) => $fixture['stage'] ?: 'DRAW') as $stage => $fixtures)
        <h3>{{ $draw['name'] }} - {{ $stageLabels[$stage] ?? str($stage)->headline() }}</h3>
        <table class="data">
          <thead><tr><th style="width:7%">Match</th><th style="width:21%">Player 1</th><th style="width:21%">Player 2</th><th style="width:9%">Date</th><th style="width:8%">Time</th><th style="width:14%">Venue</th><th style="width:8%">Court</th><th style="width:12%">Result</th></tr></thead>
          <tbody>
          @foreach($fixtures as $fixture)
            <tr>
              <td>M{{ $fixture['match_nr'] ?? $fixture['id'] }}@if($fixture['round'])<br><small>Round {{ $fixture['round'] }}</small>@endif</td>
              <td>{{ $fixture['home'] ?: 'TBD' }}</td>
              <td>{{ $fixture['away'] ?: 'TBD' }}</td>
              <td class="nowrap">{{ $fixture['scheduled_at'] ? \Carbon\Carbon::parse($fixture['scheduled_at'])->format('d M Y') : 'TBA' }}</td>
              <td class="nowrap">{{ $fixture['scheduled_at'] ? \Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i') : 'TBA' }}</td>
              <td>{{ $fixture['venue'] ?: 'TBA' }}</td>
              <td>{{ $fixture['court'] ?: 'TBA' }}</td>
              <td class="result-cell">{{ $fixture['score'] }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endforeach
    @else
      <div class="empty-note">This draw has no generated fixtures yet.</div>
    @endif

    @if($includeStandings && count($draw['standings']))
      @foreach(collect($draw['groups'])->sortBy('name') as $group)
        @php($standings = collect($draw['standings'][$group['id']] ?? [])->values())
        @if($standings->isNotEmpty())
          <h3>{{ $draw['name'] }} - Box {{ $group['name'] }} standings</h3>
          <table class="data standing">
            <thead><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets won</th><th>Sets lost</th><th>Games won</th><th>Games lost</th></tr></thead>
            <tbody>@foreach($standings as $index => $row)<tr><td>{{ $index + 1 }}</td><td>{{ $row['player'] }}</td><td>{{ $row['wins'] }}</td><td>{{ $row['losses'] }}</td><td>{{ $row['sets_won'] }}</td><td>{{ $row['sets_lost'] }}</td><td>{{ $row['games_won'] ?? 0 }}</td><td>{{ $row['games_lost'] ?? 0 }}</td></tr>@endforeach</tbody>
          </table>
        @endif
      @endforeach
    @endif
  </section>
@endforeach

@if($autoPrint)
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script>
@endif
</body>
</html>
