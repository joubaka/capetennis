@php
  $pathwayStages = collect($draw['oops'])
    ->reject(fn ($fixture) => ($fixture['stage'] ?? 'RR') === 'RR')
    ->groupBy(fn ($fixture) => $fixture['stage'] ?: 'DRAW');
@endphp
@if($pathwayStages->isNotEmpty())
  <h3>Bracket and placement pathways</h3>
  @foreach($pathwayStages as $stage => $stageFixtures)
    @php
      $rounds = $stageFixtures
        ->groupBy(fn ($fixture) => (int) ($fixture['round'] ?: 1))
        ->sortKeys();
    @endphp
    <h4>{{ $stageLabels[$stage] ?? str($stage)->headline() }}</h4>
    <table class="pathway">
      <caption>{{ $draw['name'] }} {{ $stageLabels[$stage] ?? str($stage)->headline() }} pathway</caption>
      <tbody><tr>@foreach($rounds as $round => $roundFixtures)<td><div class="round-heading">Round {{ $round }}</div>@foreach($roundFixtures as $fixture)
        <div class="match-card">
          <strong>M{{ $fixture['match_nr'] ?? $fixture['id'] }}</strong><br>
          {{ $fixture['home'] }}<br>{{ $fixture['away'] }}
          <div class="advance">Result: {{ $fixture['score'] ?: '________________' }}@if($fixture['winner_to'])<br>Winner to M{{ $fixture['winner_to'] }}@endif @if($fixture['loser_to'])<br>Loser to M{{ $fixture['loser_to'] }}@endif</div>
        </div>
      @endforeach</td>@endforeach</tr></tbody>
    </table>
  @endforeach
@elseif($showEmptyPathway ?? false)
  <div class="empty-note">This draw does not have a generated bracket yet.</div>
@endif
