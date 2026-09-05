<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $event->name }} - {{ match ($printType ?? 'pack') { 'venue' => 'Per-Venue Order of Play', 'bracket' => 'Brackets', default => 'Draw Pack' } }}</title>
  <style>
    @page { size: A4 landscape; margin: 11mm 10mm 13mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5pt; line-height: 1.3; }
    h1, h2, h3, h4, p { margin-top: 0; }
    h1 { font-size: 25pt; line-height: 1.05; margin-bottom: 4mm; }
    h2 { font-size: 15pt; color: #163a64; margin-bottom: 3mm; }
    h3 { font-size: 10pt; color: #163a64; margin: 4mm 0 2mm; }
    h4 { font-size: 8.5pt; color: #405064; margin: 3mm 0 1.5mm; }
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
    .status-line { margin-top: 1.5mm; }
    .status { display: inline-block; margin: 0 1.5mm 1mm 0; padding: 1mm 2mm; border: .6pt solid #9eabba; color: #405064; font-size: 6.8pt; font-weight: 700; text-transform: uppercase; }
    .status.ready { border-color: #237f69; color: #155f50; background: #edf7f3; }
    .status.attention { border-color: #d49b22; color: #7b5510; background: #fff8e7; }
    .notes-block { margin: 0 0 3mm; padding: 3mm; border: .6pt solid #cdd5df; background: #f8fafc; page-break-inside: avoid; }
    .notes-block p { margin: 0; white-space: pre-line; }
    .pathway { width: 100%; border-collapse: separate; border-spacing: 2mm; table-layout: fixed; margin: 0 -2mm 4mm; }
    .pathway th { padding: 1.5mm; color: #163a64; background: #edf3f8; font-size: 7pt; text-align: center; }
    .pathway td { padding: 0; vertical-align: top; }
    .pathway .round-heading { margin-bottom: 2mm; padding: 1.5mm; color: #163a64; background: #edf3f8; font-size: 7pt; font-weight: 700; text-align: center; }
    .match-card { min-height: 18mm; margin-bottom: 2mm; padding: 2mm; border: .8pt solid #8d98a8; background: #fff; page-break-inside: avoid; }
    .match-card strong { color: #163a64; }
    .match-card .advance { margin-top: 1.5mm; padding-top: 1mm; border-top: .5pt solid #d8dee7; color: #657184; font-size: 6.5pt; }
    .matrix-ledger { page-break-inside: auto; }
    .fixture-list { page-break-before: always; }
    .bracket-only h3 { margin-top: 2mm; }
    .bracket-only h4 { margin-top: 2mm; }
    .bracket-only .match-card { min-height: 15mm; margin-bottom: 1mm; padding: 1.5mm; }
    caption { height: 0; overflow: hidden; color: transparent; font-size: 0; }
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
  {{ $event->name }} - {{ match ($printType ?? 'pack') { 'venue' => 'Per-Venue Order of Play', 'bracket' => 'Brackets', default => 'Draw Pack' } }} - Generated {{ now()->format('d M Y H:i') }}
  <span class="page-number">Page </span>
</div>

@php
  $totalMatches = $draws->sum(fn ($draw) => count($draw['oops']));
  $allFixtures = $draws->flatMap(fn ($draw) => $draw['oops']);
  $scheduledMatches = $allFixtures->filter(fn ($fixture) =>
    filled($fixture['scheduled_at']) && filled($fixture['venue']) && filled($fixture['court'])
  )->count();
  $unscheduledMatches = $totalMatches - $scheduledMatches;
  $timedButIncomplete = $allFixtures->filter(fn ($fixture) =>
    filled($fixture['scheduled_at']) && (!filled($fixture['venue']) || !filled($fixture['court']))
  )->count();
  $draftDraws = $draws->where('published', false)->count();
  $unpublishedSchedules = $draws->where('schedule_published', false)->count();
  $eventDate = $event->start_date
    ? $event->start_date->format('d M Y').($event->end_date && !$event->end_date->equalTo($event->start_date) ? ' - '.$event->end_date->format('d M Y') : '')
    : 'Dates to be confirmed';
  $stageLabels = [
    'RR' => 'Round Robin', 'FM' => 'Flexible Monrad', 'MAIN' => 'Main Draw',
    'PLATE' => 'Plate', 'CONS' => 'Consolation', 'BOWL' => 'Bowl',
    'SHIELD' => 'Shield', 'SPOON' => 'Spoon',
  ];
  $venueOnly = ($printType ?? 'pack') === 'venue';
  $bracketOnly = ($printType ?? 'pack') === 'bracket';
  $venueSchedules = $schedule
    ->filter(fn ($fixture) => filled($fixture['venue']))
    ->groupBy('venue')
    ->sortKeys();
  $venueScheduleMatches = $venueSchedules->flatten(1);
  $excludedVenueMatches = $draws->flatMap(fn ($draw) => collect($draw['oops'])
    ->filter(fn ($fixture) => !filled($fixture['scheduled_at']) || !filled($fixture['venue']))
    ->map(fn ($fixture) => $fixture + ['draw_name' => $draw['name']]))
    ->values();
  $notInVenueCopies = $excludedVenueMatches->count();
  $incompleteVenueCopies = $venueScheduleMatches->filter(fn ($fixture) => !filled($fixture['court']))->count();
@endphp

@if($venueOnly)
<section class="cover">
  <p class="cover-kicker">Per-Venue Order of Play</p>
  <h1>{{ $event->name }}</h1>
  <div class="cover-rule"></div>
  <p class="event-dates">{{ $eventDate }}</p>

  <table class="stats" role="presentation">
    <tr>
      <td><strong>{{ $venueSchedules->count() }}</strong><span>{{ Str::plural('Venue', $venueSchedules->count()) }}</span></td>
      <td><strong>{{ $draws->count() }}</strong><span>Selected draws</span></td>
      <td><strong>{{ $venueScheduleMatches->count() }}</strong><span>Venue schedule rows</span></td>
      <td><strong>{{ $notInVenueCopies }}</strong><span>Not assigned to a venue</span></td>
    </tr>
  </table>

  @if($notInVenueCopies > 0 || $incompleteVenueCopies > 0)
    <div class="pack-warning"><strong>Scheduling check:</strong>@if($notInVenueCopies > 0) {{ $notInVenueCopies }} {{ Str::plural('match', $notInVenueCopies) }} {{ $notInVenueCopies === 1 ? 'is' : 'are' }} not included because no applied time and venue are available.@endif @if($incompleteVenueCopies > 0){{ $incompleteVenueCopies }} venue {{ Str::plural('row', $incompleteVenueCopies) }} still {{ $incompleteVenueCopies === 1 ? 'needs' : 'need' }} a court assignment.@endif</div>
  @endif

  <h2>Venue copies</h2>
  <div class="contents">
    @forelse($venueSchedules as $venue => $venueFixtures)
      <div class="contents-row"><strong>{{ $venue }}</strong><br><small>{{ $venueFixtures->count() }} {{ Str::plural('match', $venueFixtures->count()) }} across {{ $venueFixtures->pluck('draw_id')->unique()->count() }} {{ Str::plural('draw', $venueFixtures->pluck('draw_id')->unique()->count()) }}</small></div>
    @empty
      <div class="empty-note">No selected matches have an applied venue and time yet.</div>
    @endforelse
    @foreach($excludedVenueMatches as $fixture)
      @php
        $missingAssignments = collect([
          !filled($fixture['scheduled_at']) ? 'time' : null,
          !filled($fixture['venue']) ? 'venue' : null,
        ])->filter()->implode(' and ');
      @endphp
      <div class="contents-row">
        <strong>Not on venue list: {{ $fixture['draw_name'] }} - M{{ $fixture['match_nr'] ?? $fixture['id'] }}</strong><br>
        <small>{{ $fixture['home'] }} vs {{ $fixture['away'] }} - Missing {{ $missingAssignments }}</small>
      </div>
    @endforeach
  </div>
</section>

@foreach($venueSchedules as $venue => $venueFixtures)
  @foreach($venueFixtures->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture['scheduled_at'])->format('Y-m-d')) as $date => $dayFixtures)
    <section class="page">
      <header class="section-head">
        <div><p class="cover-kicker">Venue order of play</p><h2>{{ $venue }}</h2></div>
        <div class="section-meta"><strong>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</strong><br>{{ $dayFixtures->count() }} {{ Str::plural('match', $dayFixtures->count()) }}</div>
      </header>
      <table class="data">
        <caption>{{ $venue }} order of play for {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</caption>
        <thead><tr><th scope="col" style="width:9%">Time</th><th scope="col" style="width:8%">Court</th><th scope="col" style="width:17%">Draw</th><th scope="col" style="width:8%">Match</th><th scope="col" style="width:24%">Player 1</th><th scope="col" style="width:24%">Player 2</th><th scope="col" style="width:10%">Result</th></tr></thead>
        <tbody>
        @foreach($dayFixtures as $fixture)
          <tr>
            <td class="nowrap"><strong>{{ \Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i') }}</strong>@if($fixture['duration'])<br><small>{{ $fixture['duration'] }} min</small>@endif</td>
            <td>{{ $fixture['court'] ?: 'TBA' }}</td>
            <td>{{ $fixture['draw_name'] }}</td>
            <td><span class="stage-label">{{ $stageLabels[$fixture['stage']] ?? ($fixture['stage'] ?: 'Draw') }}</span><br>M{{ $fixture['match_nr'] ?? $fixture['id'] }}</td>
            <td>{{ $fixture['home'] }}</td>
            <td>{{ $fixture['away'] }}</td>
            <td class="result-cell">{{ $fixture['score'] }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </section>
  @endforeach
@endforeach
@elseif($bracketOnly)
@foreach($draws as $draw)
  <section @class(['bracket-only', 'page' => !$loop->first])>
    <header class="section-head">
      <div><p class="cover-kicker">Bracket only</p><h2>{{ $draw['name'] }}</h2></div>
      <div class="section-meta"><strong>{{ $draw['format'] }}</strong><br>{{ count($draw['oops']) }} {{ Str::plural('match', count($draw['oops'])) }}</div>
    </header>
    @include('backend.draw.pdf.partials.pathway-board', ['showEmptyPathway' => true])
  </section>
@endforeach
@else
<section class="cover">
  <p class="cover-kicker">Complete Draw Pack</p>
  <h1>{{ $event->name }}</h1>
  <div class="cover-rule"></div>
  <p class="event-dates">{{ $eventDate }}</p>

  <table class="stats" role="presentation">
    <tr>
      <td><strong>{{ $draws->count() }}</strong><span>Draws</span></td>
      <td><strong>{{ $totalMatches }}</strong><span>Total matches</span></td>
      <td><strong>{{ $scheduledMatches }}</strong><span>Fully scheduled</span></td>
      <td><strong>{{ $unscheduledMatches }}</strong><span>Still to schedule</span></td>
    </tr>
  </table>

  @if($unscheduledMatches > 0)
    <div class="pack-warning"><strong>Scheduling check:</strong> {{ $unscheduledMatches }} {{ Str::plural('match', $unscheduledMatches) }} in this pack {{ $unscheduledMatches === 1 ? 'is' : 'are' }} not yet fully assigned a date, venue and court.@if($timedButIncomplete) {{ $timedButIncomplete }} already {{ $timedButIncomplete === 1 ? 'has' : 'have' }} a time but still {{ $timedButIncomplete === 1 ? 'needs' : 'need' }} a venue or court.@endif</div>
  @endif
  @if($draftDraws || $unpublishedSchedules)
    <div class="pack-warning"><strong>Publication check:</strong> {{ $draftDraws }} draft {{ Str::plural('draw', $draftDraws) }} and {{ $unpublishedSchedules }} unpublished {{ Str::plural('schedule', $unpublishedSchedules) }} are included. Verify this copy before courtside use.</div>
  @endif

  <h2>Pack contents</h2>
  <div class="contents">
    <div class="contents-row"><strong>Master order of play</strong><br><small>All selected draws, grouped by day and venue</small></div>
    @foreach($draws as $draw)
      <div class="contents-row"><strong>{{ $draw['name'] }}</strong><br><small>{{ $draw['format'] }} - {{ count($draw['oops']) }} {{ Str::plural('match', count($draw['oops'])) }} - {{ $draw['published'] ? 'Published draw' : 'Draft draw' }} - {{ $draw['schedule_published'] ? 'Published schedule' : 'Unpublished schedule' }}</small></div>
    @endforeach
  </div>
</section>

@if($schedule->isNotEmpty())
  <section class="page">
    <header class="section-head">
      <div><p class="cover-kicker">Master order of play</p><h2>All scheduled times</h2></div>
      <div class="section-meta">{{ $schedule->count() }} timed {{ Str::plural('match', $schedule->count()) }}</div>
    </header>
    @foreach($schedule->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture['scheduled_at'])->format('Y-m-d')) as $date => $dayFixtures)
      <h3>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</h3>
      @foreach($dayFixtures->groupBy(fn ($fixture) => $fixture['venue'] ?: 'Venue to be confirmed') as $venue => $venueFixtures)
        <h4>{{ $venue }} - {{ $venueFixtures->count() }} {{ Str::plural('match', $venueFixtures->count()) }}</h4>
        <table class="data">
          <caption>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }} at {{ $venue }}</caption>
          <thead><tr><th scope="col" style="width:10%">Time</th><th scope="col" style="width:9%">Court</th><th scope="col" style="width:17%">Draw</th><th scope="col" style="width:8%">Match</th><th scope="col" style="width:23%">Player 1</th><th scope="col" style="width:23%">Player 2</th><th scope="col" style="width:10%">Result</th></tr></thead>
          <tbody>
          @foreach($venueFixtures as $fixture)
            <tr>
              <td class="nowrap"><strong>{{ \Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i') }}</strong>@if($fixture['duration'])<br><small>{{ $fixture['duration'] }} min</small>@endif</td>
              <td>{{ $fixture['court'] ?: 'TBA' }}</td>
              <td>{{ $fixture['draw_name'] }}</td>
              <td><span class="stage-label">{{ $stageLabels[$fixture['stage']] ?? ($fixture['stage'] ?: 'Draw') }}</span><br>M{{ $fixture['match_nr'] ?? $fixture['id'] }}</td>
              <td>{{ $fixture['home'] }}</td>
              <td>{{ $fixture['away'] }}</td>
              <td class="result-cell">{{ $fixture['score'] }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endforeach
    @endforeach
  </section>
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
      <div class="section-meta"><strong>{{ $draw['format'] }}</strong><br>{{ count($draw['oops']) }} {{ Str::plural('match', count($draw['oops'])) }}
        <div class="status-line"><span class="status {{ $draw['published'] ? 'ready' : 'attention' }}">{{ $draw['published'] ? 'Published draw' : 'Draft draw' }}</span><span class="status {{ $draw['schedule_published'] ? 'ready' : 'attention' }}">{{ $draw['schedule_published'] ? 'Schedule published' : 'Schedule unpublished' }}</span>@if($draw['locked'])<span class="status ready">Locked</span>@endif</div>
      </div>
    </header>

    <h3>Rules and notes</h3>
    @forelse($draw['notes'] as $label => $note)
      <div class="notes-block"><h4>{{ $label }}</h4><p>{{ $note }}</p></div>
    @empty
      <div class="empty-note">No draw-specific rules or notes have been saved.</div>
    @endforelse

    @if(count($draw['groups']))
      @foreach(collect($draw['groups'])->sortBy('name') as $group)
        @php
          $players = collect($group['registrations'])->sortBy(fn ($registration) => $registration['pivot']['seed'] ?? 9999)->values();
          $groupFixtures = collect($draw['rrFixtures'][$group['id']] ?? []);
        @endphp
        <div class="{{ $players->count() <= 12 ? 'matrix-wrap' : 'matrix-ledger-wrap' }}">
          <h3>Box {{ $group['name'] }} - Results matrix</h3>
          @if($players->count() <= 12)
          <table class="matrix">
            <caption>{{ $draw['name'] }} Box {{ $group['name'] }} results matrix</caption>
            <thead><tr><th scope="col">Player</th>@foreach($players as $player)<th scope="col">{{ $player['display_name'] }}</th>@endforeach<th scope="col">W</th></tr></thead>
            <tbody>
            @foreach($players as $rowPlayer)
              <tr>
                <th scope="row">{{ $rowPlayer['display_name'] }}</th>
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
                    @php
                      $orientedScore = collect($result['all_sets'] ?? [])->map(function ($set) use ($result, $rowPlayer) {
                        [$home, $away] = array_pad(explode('-', $set, 2), 2, '');
                        return (int) $result['r1_id'] === (int) $rowPlayer['id'] ? "{$home}-{$away}" : "{$away}-{$home}";
                      })->implode(', ');
                    @endphp
                    <td>{{ $orientedScore }}</td>
                  @endif
                @endforeach
                <td>{{ $groupFixtures->filter(fn ($fixture) => (int) ($fixture['winner'] ?? 0) === (int) $rowPlayer['id'])->count() }}</td>
              </tr>
            @endforeach
            </tbody>
          </table>
          @else
            <div class="pack-warning"><strong>Large box:</strong> a {{ $players->count() }}-player grid would be unreadable on A4, so this pack uses the results ledger below.</div>
            <table class="data matrix-ledger">
              <caption>{{ $draw['name'] }} Box {{ $group['name'] }} large-box results ledger</caption>
              <thead><tr><th scope="col">Match</th><th scope="col">Player 1</th><th scope="col">Player 2</th><th scope="col">Result</th></tr></thead>
              <tbody>@foreach($groupFixtures->sortBy('id') as $fixture)<tr><td>M{{ $fixture['id'] }}</td><td>{{ $players->firstWhere('id', $fixture['r1_id'])['display_name'] ?? 'TBD' }}</td><td>{{ $players->firstWhere('id', $fixture['r2_id'])['display_name'] ?? 'TBD' }}</td><td>{{ $fixture['score'] }}</td></tr>@endforeach</tbody>
            </table>
          @endif
        </div>
      @endforeach
    @endif

    @include('backend.draw.pdf.partials.pathway-board')

    @if(count($draw['oops']))
      @php
        $fixtures = collect($draw['oops'])->sortBy(fn ($fixture) => sprintf(
          '%s|%08d',
          $fixture['scheduled_at'] ?: '9999-12-31 23:59:59',
          (int) ($fixture['match_nr'] ?? $fixture['id'])
        ))->values();
      @endphp
        <div class="fixture-list">
        <h3>{{ $draw['name'] }} - Fixtures in scheduled order</h3>
        <table class="data">
          <caption>{{ $draw['name'] }} fixtures in scheduled order</caption>
          <thead><tr><th scope="col" style="width:7%">Match</th><th scope="col" style="width:9%">Stage</th><th scope="col" style="width:19%">Player 1</th><th scope="col" style="width:19%">Player 2</th><th scope="col" style="width:9%">Date</th><th scope="col" style="width:8%">Time</th><th scope="col" style="width:12%">Venue</th><th scope="col" style="width:7%">Court</th><th scope="col" style="width:10%">Result</th></tr></thead>
          <tbody>
          @foreach($fixtures as $fixture)
            <tr>
              <td>M{{ $fixture['match_nr'] ?? $fixture['id'] }}@if($fixture['round'])<br><small>Round {{ $fixture['round'] }}</small>@endif</td>
              <td><span class="stage-label">{{ $stageLabels[$fixture['stage']] ?? str($fixture['stage'] ?: 'Draw')->headline() }}</span></td>
              <td>{{ $fixture['home'] }}</td>
              <td>{{ $fixture['away'] }}</td>
              <td class="nowrap">{{ $fixture['scheduled_at'] ? \Carbon\Carbon::parse($fixture['scheduled_at'])->format('d M Y') : 'TBA' }}</td>
              <td class="nowrap">{{ $fixture['scheduled_at'] ? \Carbon\Carbon::parse($fixture['scheduled_at'])->format('H:i') : 'TBA' }}</td>
              <td>{{ $fixture['venue'] ?: 'TBA' }}</td>
              <td>{{ $fixture['court'] ?: 'TBA' }}</td>
              <td class="result-cell">{{ $fixture['score'] }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
        </div>
    @else
      <div class="empty-note">This draw has no generated fixtures yet.</div>
    @endif

    @if($includeStandings && count($draw['standings']))
      @foreach(collect($draw['groups'])->sortBy('name') as $group)
        @php($standings = collect($draw['standings'][$group['id']] ?? [])->values())
        @if($standings->isNotEmpty())
          <h3>{{ $draw['name'] }} - Box {{ $group['name'] }} standings</h3>
          <table class="data standing">
            <caption>{{ $draw['name'] }} Box {{ $group['name'] }} standings</caption>
            <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">W</th><th scope="col">L</th><th scope="col">Sets won</th><th scope="col">Sets lost</th><th scope="col">Games won</th><th scope="col">Games lost</th></tr></thead>
            <tbody>@foreach($standings as $index => $row)<tr><td>{{ $index + 1 }}</td><td>{{ $row['player'] }}</td><td>{{ $row['wins'] }}</td><td>{{ $row['losses'] }}</td><td>{{ $row['sets_won'] }}</td><td>{{ $row['sets_lost'] }}</td><td>{{ $row['games_won'] ?? 0 }}</td><td>{{ $row['games_lost'] ?? 0 }}</td></tr>@endforeach</tbody>
          </table>
        @endif
      @endforeach
    @endif
  </section>
@endforeach
@endif

@if($autoPrint)
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });</script>
@endif
</body>
</html>
