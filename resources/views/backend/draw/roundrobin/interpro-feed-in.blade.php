@php
  $trialFixtures = $draw->drawFixtures()->with(['registration1.players', 'registration2.players', 'fixtureResults', 'draw.settings'])->get()->keyBy('match_nr');
@endphp
<section class="interpro-feed-in">
  <h4>Interpro trials — fixed feed-in</h4>
  <p>Four groups A–D. Fourth place finishes after the round robin. Group winners play for the title; second and third places cross over and feed into positions 3–12. <strong>First result stands:</strong> players who meet again use their original result without replaying.</p>
  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead><tr><th>Match</th><th>Player / source</th><th>Player / source</th><th>Result</th><th>Positions</th></tr></thead>
      <tbody>
        @foreach(\App\Services\Draw\InterproRoundRobinFormat::template() as $number => $definition)
          @php
            $fixture = ($emptyBracket ?? false) ? null : $trialFixtures->get($number);
          @endphp
          <tr>
            <td>{{ $number }}</td>
            @foreach($definition[2] as $slot => $source)
              @php
                $registration = $slot === 0 ? $fixture?->registration1 : $fixture?->registration2;
                $label = preg_match('/^([WL])(\d+)$/', $source, $parts) ? ($parts[1] === 'W' ? 'Winner' : 'Loser').' match '.$parts[2] : 'Group '.$source[0].' position '.$source[1];
              @endphp
              <td>{{ $registration?->display_name ?: $label }}</td>
            @endforeach
            <td>{{ $fixture ? (\App\Services\Draw\InterproRoundRobinFormat::score($fixture) ?? ($fixture->isWalkover() ? 'W/O' : $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->registration1_score.'-'.$set->registration2_score)->implode(', ').($fixture->isRetired() ? ' RET' : ''))) : '' }}</td>
            <td>{{ isset($definition[3]) ? $definition[3].'/'.($definition[3] + 1) : '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
