@php
  $trialFixtures = \App\Services\Draw\InterproRoundRobinFormat::projectChallenges($draw,
    $draw->drawFixtures()->with(['registration1.players', 'registration2.players', 'fixtureResults', 'draw.settings'])->get())->keyBy('match_nr');
  $trialTemplate = \App\Services\Draw\InterproRoundRobinFormat::template($draw);
  $trialOutcomes = app(\App\Services\IndividualMatchOutcomeService::class);
  $trialSchedule = ($emptyBracket ?? false) ? collect() : app(\App\Services\Draw\InterproBracketScheduleProjection::class)->forDraw($draw);
  $trialSourceLabel = static fn ($source) => preg_match('/^([WL])(\d+)$/', $source, $parts)
    ? ($parts[1] === 'W' ? 'Winner' : 'Loser').' match '.$parts[2]
    : 'Group '.$source[0].' position '.$source[1];
  $trialScore = static fn ($fixture) => $fixture ? (\App\Services\Draw\InterproRoundRobinFormat::score($fixture)
    ?? ($fixture->isWalkover() ? 'W/O' : $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->registration1_score.'-'.$set->registration2_score)->implode(', ').($fixture->isRetired() ? ' RET' : ''))) : '';
  $trialMatches = [];
  foreach ($trialTemplate as $number => $definition) {
    $fixture = ($emptyBracket ?? false) ? null : $trialFixtures->get($number);
    $score = $trialScore($fixture);
    $original = $fixture ? \App\Services\Draw\InterproRoundRobinFormat::source($fixture) : null;
    $hasCarry = $fixture && \App\Services\Draw\InterproRoundRobinFormat::hasReference($fixture);
    $scoreFixture = $hasCarry ? $original : $fixture;
    $reverse = $original && \App\Services\Draw\InterproRoundRobinFormat::winnerForFixture($fixture, (int) $original->registration1_id) !== (int) $fixture->registration1_id;
    $sets = $scoreFixture?->fixtureResults->sortBy('set_nr')->values() ?? collect();
    $note = $original ? ($original->isWalkover() ? 'W/O · ' : ($original->isRetired() ? 'RET · ' : '')).substr($score, (int) strpos($score, ' · ') + strlen(' · '))
      : ($hasCarry || $sets->isEmpty() ? $score : ($fixture?->isRetired() ? 'RET' : ''));
    $schedule = $fixture && !\App\Services\Draw\InterproRoundRobinFormat::noMatchRequired($fixture) ? $trialSchedule->get($fixture->id) : null;
    $sources = [];
    foreach ($definition[2] as $source) {
      $sources[] = preg_match('/^([WL])(\d+)$/', $source, $parts)
        ? ['type' => $parts[1] === 'W' ? 'winner' : 'loser', 'match' => $parts[2], 'label' => ($parts[1] === 'W' ? 'Winner' : 'Loser').' of Match '.$parts[2]]
        : ['type' => 'group', 'label' => $trialSourceLabel($source)];
    }
    $trialMatches[$number] = ['number' => $number, 'fixture_id' => $fixture?->id,
      'can_lucky_loser' => $fixture && request()->routeIs('backend.*') && (auth()->user()?->can('replacePlayoffLuckyLoser', $draw) ?? false)
        && !$fixture->winner_registration && (int) $fixture->match_status === 0 && !\App\Services\Draw\InterproRoundRobinFormat::noMatchRequired($fixture),
      'stage' => $definition[0], 'round' => $definition[1], 'position' => $definition[3] ?? null, 'sources' => $sources,
      'players' => collect([$fixture?->registration1, $fixture?->registration2])->map(fn ($registration) => $registration
        ? ['id' => (int) $registration->id, 'name' => $registration->display_name,
            'lucky_loser' => (bool) ($registration->competition_replacement['show_lucky_loser'] ?? false)] : null)->all(),
      'winner' => $fixture ? $trialOutcomes->winner($fixture) : null,
      'sets' => $sets->map(fn ($set) => $reverse ? [$set->registration2_score, $set->registration1_score] : [$set->registration1_score, $set->registration2_score])->all(),
      'note' => $note, 'schedule' => $schedule];
  }
  $trialProjection = ['matches' => $trialMatches, 'boards' => [
    ['key' => 'main', 'title' => 'Championship and lower eight-player bracket', 'sections' => [
      ['title' => 'Championship · 4 players', 'matches' => [2001, 2002, 2003], 'roundLabels' => ['Semifinals', 'Positions 1–2'], 'champion' => 2003],
      ['title' => 'Lower bracket · 8 players', 'matches' => [3001, 3002, 3003, 3004, 3005, 3006, 3007, 3008, 3009], 'roundLabels' => ['Round 1', 'Round 2', 'Round 3 · crossed feed-in', 'Positions 3–4']],
    ]],
    ['key' => 'placements', 'title' => 'Placement matches · 5th–12th', 'sections' => [
      ['title' => 'Positions 5–6', 'matches' => [3011], 'roundLabels' => ['Final']],
      ['title' => 'Positions 7–8', 'matches' => [3010], 'roundLabels' => ['Final']],
      ['title' => 'Consolation · 9th–12th', 'matches' => [4001, 4002, 4003], 'roundLabels' => ['Semifinals', 'Positions 9–10']],
      ['title' => 'Positions 11–12', 'matches' => [4004], 'roundLabels' => ['Final']],
    ]],
  ]];
  $challengeSections = [];
  foreach (\App\Services\Draw\InterproRoundRobinFormat::challengePairs($draw) as $upper) {
    $challengeSections[] = ['title' => 'Base positions '.$upper.' v '.($upper + 1), 'matches' => [5000 + $upper], 'roundLabels' => ['Challenge']];
  }
  if ($challengeSections) $trialProjection['boards'][] = ['key' => 'challenges', 'title' => 'Optional challenge playoffs', 'sections' => $challengeSections];
@endphp
@include('draw.partials.interpro-bracket-assets')
<style>
  .bracket-zoom-inner:has(> .interpro-feed-in) { display: block; width: 100%; }
  .interpro-feed-in { font-size: 16px; line-height: 1.5; }
  .interpro-feed-in h4 { font-size: 22px; }
  .interpro-board-host { display: block; background: #fff; }
  .interpro-board-host[data-rendered="true"] [data-bracket-loading],
  .interpro-board-host[data-rendered="error"] [data-bracket-loading] { display: none; }
  .interpro-board-host .interpro-bracket-scroll { max-width: 100%; margin-bottom: 20px; }
  .interpro-feed-in .interpro-route-details { margin-top: 16px; }
  .interpro-feed-in .interpro-route-details summary { cursor: pointer; font-weight: 600; }
  .interpro-feed-in .fm-match-schedule.is-preview { color: #b45309; }
  .interpro-feed-in .fm-match-schedule.is-published { color: #176448; }
  @media print {
    @page { size: A4 landscape; margin: 8mm; }
    .interpro-feed-in { font-size: 11px; line-height: 1.3; }
    .interpro-feed-in h4 { font-size: 15px; margin: 4px 0; }
    .interpro-feed-in > p { font-size: 11px; margin: 0 0 6px; }
    .interpro-feed-in .interpro-route-details, .interpro-feed-in .interpro-screen-hint { display: none !important; }
    .interpro-board-host .fm-links { width: 100% !important; height: 100% !important; }
    .interpro-board-host .interpro-bracket-scroll { break-inside: avoid; margin: 0; }
    .interpro-board-host .interpro-bracket-scroll + .interpro-bracket-scroll { break-before: page; }
    .interpro-feed-in + .ct-final-positions { break-before: page; }
  }
</style>
<section class="interpro-feed-in">
  <h4>Interpro trials — fixed feed-in</h4>
  <p>Four groups A–D. Fourth place finishes after the round robin. Group winners play for the title; second and third places cross over and feed into positions 3–12. <strong>First result stands:</strong> players who meet again in the playoff bracket use their first playoff result without replaying. Round-robin results do not carry into playoffs.</p>
  <p class="text-muted interpro-screen-hint">The four group winners play at the top. In the lower bracket, the championship semifinal losers flip into Round 3. Scroll across to later rounds.</p>
  <ct-interpro-bracket class="interpro-board-host"><script type="application/json" data-interpro-payload>{!! json_encode($trialProjection, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script><p data-bracket-loading>Loading playoff bracket…</p></ct-interpro-bracket>
  <details class="interpro-route-details"><summary>Match sources and results</summary>
  <div class="table-responsive interpro-route-table">
    <table class="table table-bordered align-middle">
      <thead><tr><th>Match</th><th>Player / source</th><th>Player / source</th><th>Result</th><th>Positions</th></tr></thead>
      <tbody>
        @foreach($trialTemplate as $number => $definition)
          @php
            $fixture = ($emptyBracket ?? false) ? null : $trialFixtures->get($number);
          @endphp
          <tr>
            <td>{{ $number }}</td>
            @foreach($definition[2] as $slot => $source)
              @php
                $registration = $slot === 0 ? $fixture?->registration1 : $fixture?->registration2;
                $label = $trialSourceLabel($source);
              @endphp
              <td>{{ $registration?->display_name ?: $label }}</td>
            @endforeach
            <td>{{ $trialScore($fixture) }}</td>
            <td>{{ isset($definition[3]) ? $definition[3].'/'.($definition[3] + 1) : '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  </details>
</section>
@unless($emptyBracket ?? false)
  @php
    $knownPlacements = app(\App\Services\Draw\DrawFinalPlacementService::class)->forDraw($draw)->keyBy('position');
    $trialPlacements = collect(range(1, 12))->map(fn ($position) => $knownPlacements->get($position)
      ?? ['position' => $position, 'name' => 'Awaiting result', 'status' => 'awaiting']);
  @endphp
  @include('draw.partials.final-positions', ['draw' => $draw, 'placements' => $trialPlacements])
@endunless
