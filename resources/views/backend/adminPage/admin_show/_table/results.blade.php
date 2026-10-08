<h3 class="h5">Player points ranking</h3>
<p class="small text-muted">Completed singles only. Roster bands come first: 1–2, 3–4, 5–6, then 7–8. Within each band, each match win earns 100, 35, 12, or 2 points respectively. Equal points within the same band share the same position.</p>
<ol class="list-group mb-4" aria-label="Player points ranking">
  @php $position = 0; $previousPoints = null; @endphp
  @foreach($ranking as $index => $player)
    @php
      $positionKey = [(int) ceil($player['rank'] / 2), $player['points']];
      if ($previousPoints !== $positionKey) $position = $index + 1;
      $previousPoints = $positionKey;
    @endphp
    <li class="list-group-item d-flex align-items-center gap-3">
      <span class="badge bg-label-primary">{{ $position }}</span>
      <span class="flex-grow-1">{{ $player['name'] }}<small class="d-block text-muted">{{ $player['region'] }} · Roster rank {{ $player['rank'] }}</small></span>
      <strong class="text-nowrap">{{ $player['points'] }} points</strong>
    </li>
  @endforeach
</ol>
<h3 class="h5">Supporting match results</h3>
@foreach($playerFixtures as $teamName => $team)
  <details class="roster-team">
    <summary class="roster-team-summary"><strong>{{ $teamName }}</strong><span aria-hidden="true">⌄</span></summary>
    <div class="roster-team-body">
      @foreach($team as $rank => $playerDetails)
        <h4 class="h6">{{ $rank + 1 }}. {{ $playerDetails['name'] }}</h4>
        <ul class="list-group mb-3">
          @foreach($playerDetails['results']['opponents'] as $key => $opponent)
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
              <span class="flex-grow-1">{{ $opponent['player']['name'] }} {{ $opponent['player']['surname'] }}</span>
              <span class="badge {{ $playerDetails['results']['w/l'][$key] === 1 ? 'bg-label-success' : 'bg-label-danger' }}">{{ $playerDetails['results']['w/l'][$key] === 1 ? 'Won' : 'Lost' }}</span>
              <span class="small" aria-label="Home–away set scores">Home–away:
                @foreach($opponent['score'] as $match)
                  @foreach($match as $set)<span class="text-nowrap">{{ $set->team1_score }}–{{ $set->team2_score }}</span>@unless($loop->last), @endunless @endforeach
                @endforeach
              </span>
            </li>
          @endforeach
        </ul>
      @endforeach
    </div>
  </details>
@endforeach
