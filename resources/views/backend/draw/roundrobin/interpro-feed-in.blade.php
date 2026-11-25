@php
  $trialFixtures = $draw->drawFixtures()->with(['registration1.players', 'registration2.players', 'fixtureResults', 'draw.settings'])->get()->keyBy('match_nr');
  $trialTemplate = \App\Services\Draw\InterproRoundRobinFormat::template();
  $trialPanels = [
    ['title' => 'Championship · 1st / 2nd', 'width' => 610, 'height' => 370,
      'matches' => [2001 => [10, 10], 2002 => [10, 200], 2003 => [340, 105]]],
    ['title' => 'Crossovers and feed-in · 3rd–8th', 'width' => 1270, 'height' => 790,
      'matches' => [3001 => [10, 10], 3002 => [10, 210], 3003 => [10, 410], 3004 => [10, 610],
        3005 => [340, 110], 3006 => [340, 510], 3007 => [670, 110], 3008 => [670, 510],
        3010 => [670, 310], 3009 => [1000, 210], 3011 => [1000, 410]]],
    ['title' => 'Crossover losers · 9th–12th', 'width' => 610, 'height' => 370,
      'matches' => [4001 => [10, 10], 4002 => [10, 200], 4003 => [340, 10], 4004 => [340, 200]]],
  ];
  $trialSourceLabel = static fn ($source) => preg_match('/^([WL])(\d+)$/', $source, $parts)
    ? ($parts[1] === 'W' ? 'Winner' : 'Loser').' match '.$parts[2]
    : 'Group '.$source[0].' position '.$source[1];
  $trialScore = static fn ($fixture) => $fixture ? (\App\Services\Draw\InterproRoundRobinFormat::score($fixture)
    ?? ($fixture->isWalkover() ? 'W/O' : $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->registration1_score.'-'.$set->registration2_score)->implode(', ').($fixture->isRetired() ? ' RET' : ''))) : '';
@endphp
<style>
  .interpro-feed-in { font-size: 16px; line-height: 1.5; }
  .interpro-feed-in h4 { font-size: 22px; }
  .interpro-feed-in h5 { font-size: 18px; margin: 24px 0 10px; }
  .interpro-feed-in .interpro-bracket-scroll { overflow-x: auto; max-width: 100%; padding-bottom: 8px; }
  .interpro-feed-in .interpro-bracket-svg { display: block; min-width: 0 !important; max-width: none; flex-shrink: 0; font-family: Arial, sans-serif; }
  .interpro-feed-in .interpro-edge { fill: none; stroke: #6b8095; stroke-width: 1.5; }
  .interpro-feed-in .interpro-edge-loser { stroke: #bc742b; stroke-dasharray: 5 4; }
  .interpro-feed-in .interpro-match { height: 160px; box-sizing: border-box; border: 1px solid #bccbd9; border-radius: 6px; background: #fff; color: #20364e; overflow: hidden; font: 14px/1.35 Arial, sans-serif; }
  .interpro-feed-in .interpro-match-heading { display: flex; justify-content: space-between; padding: 6px 10px; background: #edf3f8; font-size: 13px; font-weight: 700; }
  .interpro-feed-in .interpro-match-player { padding: 5px 10px; border-bottom: 1px solid #e5ebf1; }
  .interpro-feed-in .interpro-match-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
  .interpro-feed-in .interpro-match-source { display: block; font-size: 12px; color: #52677b; }
  .interpro-feed-in .interpro-match-score { padding: 5px 10px; font-size: 12px; color: #176448; }
  .interpro-feed-in .interpro-route-table { display: none; }
  @media print {
    .interpro-feed-in .interpro-diagrams { display: none !important; }
    .interpro-feed-in .interpro-route-table { display: block !important; }
  }
</style>
<section class="interpro-feed-in">
  <h4>Interpro trials — fixed feed-in</h4>
  <p>Four groups A–D. Fourth place finishes after the round robin. Group winners play for the title; second and third places cross over and feed into positions 3–12. <strong>First result stands:</strong> players who meet again use their original result without replaying.</p>
  <div class="interpro-diagrams">
    <p class="text-muted">Follow the lines from left to right. Dashed lines carry the loser. Scroll sideways for later rounds.</p>
    @foreach($trialPanels as $panel)
      <h5>{{ $panel['title'] }}</h5>
      <div class="interpro-bracket-scroll" tabindex="0" role="region" aria-label="{{ $panel['title'] }} bracket">
        <svg class="interpro-bracket-svg" width="{{ $panel['width'] }}" height="{{ $panel['height'] }}" viewBox="0 0 {{ $panel['width'] }} {{ $panel['height'] }}" role="group" aria-label="{{ $panel['title'] }} match routes">
          <title>{{ $panel['title'] }} — solid lines advance winners; dashed lines advance losers</title>
          @foreach($panel['matches'] as $number => [$x, $y])
            @foreach($trialTemplate[$number][2] as $slot => $source)
              @if(preg_match('/^([WL])(\d+)$/', $source, $parts) && isset($panel['matches'][(int) $parts[2]]))
                @php
                  [$fromX, $fromY] = $panel['matches'][(int) $parts[2]];
                  $startX = $fromX + 260;
                  $startY = $fromY + ($parts[1] === 'W' ? 55 : 105);
                  $endY = $y + ($slot === 0 ? 55 : 105);
                  $bendX = $x - ($slot === 0 ? 32 : 18);
                @endphp
                <path class="interpro-edge {{ $parts[1] === 'L' ? 'interpro-edge-loser' : '' }}" data-from="{{ $parts[2] }}" data-to="{{ $number }}" d="M {{ $startX }} {{ $startY }} H {{ $bendX }} V {{ $endY }} H {{ $x }}" />
              @endif
            @endforeach
          @endforeach
          @foreach($panel['matches'] as $number => [$x, $y])
            @php
              $definition = $trialTemplate[$number];
              $fixture = ($emptyBracket ?? false) ? null : $trialFixtures->get($number);
            @endphp
            <foreignObject x="{{ $x }}" y="{{ $y }}" width="260" height="160" data-match="{{ $number }}">
              <div xmlns="http://www.w3.org/1999/xhtml" class="interpro-match">
                <div class="interpro-match-heading"><span>Match {{ $number }}</span><span>{{ isset($definition[3]) ? 'Places '.$definition[3].'/'.($definition[3] + 1) : '' }}</span></div>
                @foreach($definition[2] as $slot => $source)
                  @php
                    $registration = $slot === 0 ? $fixture?->registration1 : $fixture?->registration2;
                    $label = $trialSourceLabel($source);
                  @endphp
                  <div class="interpro-match-player">
                    <span class="interpro-match-name" title="{{ $registration?->display_name ?: $label }}">{{ $registration?->display_name ?: $label }}</span>
                    <span class="interpro-match-source">{{ $registration ? $label : "\u{00A0}" }}</span>
                  </div>
                @endforeach
                <div class="interpro-match-score">{{ $trialScore($fixture) }}</div>
              </div>
            </foreignObject>
          @endforeach
        </svg>
      </div>
    @endforeach
  </div>
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
</section>
@unless($emptyBracket ?? false)
  @include('draw.partials.final-positions', ['draw' => $draw])
@endunless
