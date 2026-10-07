<p class="small text-muted">Roster-weighted points come first, including one starting win credit for odd roster positions. Equal points are ordered by completed-match sets won minus sets lost. Selected regions choose candidates; results against all event opponents in the selected formats count. Choose up to 10 players and explain departures from the suggestions.</p>
@if($ranking->isEmpty())
  <div class="alert alert-light border" role="status">No results recorded for this setup.</div>
@else
  @if($ranking->contains('cutoff_tie', true))
    <div class="alert alert-warning">Players tied at tenth place on both points and set difference need a selection decision. No tied player is automatically selected.</div>
  @endif
  <ol class="list-group" aria-label="Suggested top 10 and player ranking">
  @foreach($ranking as $player)
    <li class="list-group-item">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <label class="form-check py-2"><input type="checkbox" class="form-check-input" data-selection-player value="{{ $player['id'] }}" {{ $player['suggested'] ? 'checked' : '' }}><span class="visually-hidden">Select {{ $player['name'] }}</span></label>
        <span class="badge bg-label-primary">{{ $player['position'] }}</span>
        <strong class="flex-grow-1">{{ $player['name'] }}</strong>
        @if($player['suggested'])<span class="badge bg-label-success">Suggested top 10</span>@endif
        @if($player['cutoff_tie'])<span class="badge bg-label-warning">Review cutoff tie</span>@endif
        <strong>{{ $player['points'] }} points</strong>
      </div>
      <div class="small text-muted">{{ $player['region'] }} · {{ implode(', ', $player['teams']) }} · Roster rank {{ implode(', ', $player['ranks']) }} · {{ $player['wins'] }} wins / {{ $player['losses'] }} losses</div>
      <div class="small">Band {{ $player['band'] }} · Starting credit {{ $player['starting_credit'] }} · Singles wins {{ $player['singles_wins'] }} · Reverse Singles wins {{ $player['reverse_singles_wins'] }} · Total credited wins {{ $player['credited_wins'] }}</div>
      <div class="small">Sets won {{ $player['sets_won'] }} · Sets lost {{ $player['sets_lost'] }} · Set difference {{ $player['set_difference'] > 0 ? '+' : '' }}{{ $player['set_difference'] }}</div>
      @foreach($player['cross_band_review'] as $review)
        <p class="small text-warning mb-0"><strong>REVIEW across bands:</strong> {{ $review['team'] }}, consecutive roster ranks {{ implode(', ', $review['ranks']) }} each have at least two completed matches and more wins than losses in this setup. Starting credits are excluded. Review the team strength; the weighted order is retained.
          @foreach($review['records'] as $rank => $record)<span class="text-nowrap">Rank {{ $rank }}: {{ $record['wins'] }}W / {{ $record['losses'] }}L.</span> @endforeach
        </p>
      @endforeach
      @if(count($player['ranks']) > 1)<p class="small text-warning mb-0">Roster ranks differ across teams. Weighting uses rank {{ $player['rank'] }}; review before selection.</p>@endif
      @if($player['higher_rank_wins'])<p class="small mb-0">Selection evidence: {{ $player['higher_rank_wins'] }} win(s) over higher-rostered opponents.</p>@endif
      <label class="d-block small mt-2">Selection reason (required for changes or cutoff ties)<input class="form-control" type="text" maxlength="2000" data-selection-reason="{{ $player['id'] }}" placeholder="Record the results supporting your decision"></label>
      <details class="mt-2">
        <summary style="min-height:44px; cursor:pointer">Supporting match results</summary>
        <ul class="list-group">
          @foreach($player['matches'] as $match)
            <li class="list-group-item small">{{ $match['won'] ? 'Won' : 'Lost' }} against {{ $match['opponent'] }} ({{ $match['region'] }}, rank {{ $match['opponent_rank'] }}) · {{ $match['format'] === 'reverse_singles' ? 'Reverse Singles' : 'Singles' }} · Home–away: {{ $match['score'] }}</li>
          @endforeach
        </ul>
      </details>
    </li>
  @endforeach
  </ol>
@endif
