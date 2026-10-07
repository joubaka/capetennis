<details class="result-rules">
  <summary>How the ranking works</summary>
  <p>Roster-weighted points come first, including one starting win credit for odd roster positions. Equal points are ordered by completed-match sets won minus sets lost. Region filters choose candidates; their results against all event opponents in the selected formats are retained. Explain departures from the suggestions before saving a draft.</p>
</details>
@if($ranking->isEmpty())
  <div class="result-empty" role="status"><i class="ti ti-scoreboard" aria-hidden="true"></i><strong>No completed singles results</strong><p>Check the regions and match formats in Setup, or choose another age group. Incomplete matches and doubles are excluded.</p></div>
@else
  @if($ranking->contains('cutoff_tie', true))
    <div class="result-review-notice" role="status"><strong>Review the tenth-place tie.</strong> Both points and set difference are equal. Record a selection decision and reasons.</div>
  @endif
  <ol class="result-player-list" aria-label="Suggested top 10 and player ranking">
  @foreach($ranking as $player)
    <li class="result-player-card">
      <div class="result-player-heading">
        <label class="result-player-select"><input type="checkbox" class="form-check-input" data-selection-player value="{{ $player['id'] }}" {{ $player['suggested'] ? 'checked' : '' }}><span class="visually-hidden">Select {{ $player['name'] }}</span></label>
        <span class="result-position">{{ $player['position'] }}</span>
        <div class="result-player-identity"><strong>{{ $player['name'] }}</strong><span>{{ $player['region'] }} · {{ implode(', ', $player['teams']) }}</span><span>Roster {{ implode(', ', $player['ranks']) }} · Band {{ $player['band'] }} · {{ $player['wins'] }}W / {{ $player['losses'] }}L</span></div>
        <div class="result-player-points"><strong>{{ $player['points'] }}</strong><span>points</span></div>
      </div>
      <div class="result-player-tags">
        @if($player['suggested'])<span class="result-tag result-tag-selected">Suggested top 10</span>@endif
        @if($player['cutoff_tie'])<span class="result-tag result-tag-review">Review cutoff tie</span>@endif
        @if($player['higher_rank_wins'])<span class="result-tag">{{ $player['higher_rank_wins'] }} win(s) over higher-rostered opponents</span>@endif
      </div>
      <dl class="result-metrics">
        <div><dt>Starting credit</dt><dd>{{ $player['starting_credit'] }}</dd></div>
        <div><dt>Singles wins</dt><dd>{{ $player['singles_wins'] }}</dd></div>
        <div><dt>Reverse wins</dt><dd>{{ $player['reverse_singles_wins'] }}</dd></div>
        <div><dt>Credited wins</dt><dd>{{ $player['credited_wins'] }}</dd></div>
        <div><dt>Sets won / lost</dt><dd>{{ $player['sets_won'] }} / {{ $player['sets_lost'] }}</dd></div>
        <div><dt>Set difference</dt><dd>{{ $player['set_difference'] > 0 ? '+' : '' }}{{ $player['set_difference'] }}</dd></div>
      </dl>
      @foreach($player['cross_band_review'] as $review)
        <details class="result-review-notice result-review-details"><summary><strong>REVIEW across bands</strong> · {{ $review['team'] }} · Ranks {{ implode(', ', $review['ranks']) }}</summary><p>Each consecutive player has at least two completed matches and more wins than losses in this setup. Starting credits are excluded. Review the team strength; the weighted order is retained.</p><div class="result-review-records">@foreach($review['records'] as $rank => $record)<span>Rank {{ $rank }}: {{ $record['wins'] }}W / {{ $record['losses'] }}L</span>@endforeach</div></details>
      @endforeach
      @if(count($player['ranks']) > 1)<p class="result-review-notice">Roster ranks differ across teams. Weighting uses rank {{ $player['rank'] }}; review before selection.</p>@endif
      <div class="result-player-disclosures">
        <details class="result-reason"><summary>Selection reason <span>Required for changes or cutoff ties</span></summary><label>Reason for {{ $player['name'] }}<input class="form-control" type="text" maxlength="2000" data-selection-reason="{{ $player['id'] }}" placeholder="Record the results supporting your decision"></label></details>
        <details class="result-matches"><summary>Match results <span>{{ count($player['matches']) }} completed</span></summary><ul>
          @foreach($player['matches'] as $match)
            <li><span class="result-match-outcome {{ $match['won'] ? 'is-win' : 'is-loss' }}">{{ $match['won'] ? 'Won' : 'Lost' }}</span><div><strong>{{ $match['opponent'] }}</strong><span>{{ $match['region'] }} · Roster {{ $match['opponent_rank'] }} · {{ $match['format'] === 'reverse_singles' ? 'Reverse Singles' : 'Singles' }}</span></div><span class="result-match-score">Home–away: {{ $match['score'] }}</span></li>
          @endforeach
        </ul></details>
      </div>
    </li>
  @endforeach
  </ol>
@endif
