<details class="result-rules">
  <summary>How the ranking works</summary>
  <p>Roster bands come first: 1–2, then 3–4, 5–6, and 7–8. A player remains in their band even with zero wins. Within each band, points equal completed match wins multiplied by its weight: 100, 35, 12, or 2. Equal points are ordered by completed-match sets won minus sets lost. If tied players all come from the same team, the higher roster position comes first. Players from different teams are compared by direct wins when every tied pair has the same nonzero number of counted matches. Unresolved ties require review at the selection cutoff. Candidate regions choose players; excluded result regions remove entire matches involving either side. Adjacent-band comparisons support manual review and do not promote players automatically. Explain departures from the suggestions before saving a draft.</p>
</details>
@if($ranking->isEmpty())
  <div class="result-empty" role="status"><i class="ti ti-scoreboard" aria-hidden="true"></i><strong>No completed singles results</strong><p>Check the regions and match formats in Setup, or choose another age group. Incomplete matches and doubles are excluded.</p></div>
@else
  @if($ranking->contains('cutoff_tie', true))
    <div class="result-review-notice" role="status"><strong>Review the tenth-place tie.</strong> Both points and set difference are equal. Record a selection decision and reasons.</div>
  @endif
  <ol class="result-player-list" aria-label="Suggested top 10 and player ranking">
  @foreach($ranking as $player)
    @php $wonAllCountedBandMatches = !$player['band_attribution_uncertain'] && $player['own_band_record']['wins'] > 0 && $player['own_band_record']['losses'] === 0; @endphp
    <li @class(['result-player-card', 'result-player-card-lost-all' => $player['lost_all_counted_band_matches'], 'result-player-card-won-all' => $wonAllCountedBandMatches])>
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
        @if($player['lost_all_counted_band_matches'])<span class="result-tag result-tag-review">Review: lost all counted matches in Band {{ $player['band'] }} · 0W / {{ $player['own_band_record']['losses'] }}L</span>@endif
        @if($wonAllCountedBandMatches)<span class="result-tag result-tag-selected">Won all counted band matches · {{ $player['own_band_record']['wins'] }}W / 0L</span>@endif
      </div>
      <dl class="result-metrics">
        <div><dt>Points per win</dt><dd>{{ $player['points_per_win'] }}</dd></div>
        <div><dt>Singles wins</dt><dd>{{ $player['singles_wins'] }}</dd></div>
        <div><dt>Reverse wins</dt><dd>{{ $player['reverse_singles_wins'] }}</dd></div>
        <div><dt>Match wins</dt><dd>{{ $player['wins'] }}</dd></div>
        <div><dt>Sets won / lost</dt><dd>{{ $player['sets_won'] }} / {{ $player['sets_lost'] }}</dd></div>
        <div><dt>Set difference</dt><dd>{{ $player['set_difference'] > 0 ? '+' : '' }}{{ $player['set_difference'] }}</dd></div>
      </dl>
      @if($player['head_to_head']['tied_players'] > 1 && !$player['same_team_tiebreak'])
        <p class="result-review-notice">Head-to-head among tied players: {{ $player['head_to_head']['wins'] }}W / {{ $player['head_to_head']['losses'] }}L. {{ $player['head_to_head']['reason'] }}</p>
      @endif
      <details class="result-review-notice result-review-details"><summary>Compare adjacent bands</summary><p>Direct records below use counted matches only. Overall records may involve different opponents and do not establish relative strength. These comparisons do not change the band order.</p>
        @foreach($player['adjacent_band_comparisons'] as $comparison)
          <div class="result-review-records"><strong>{{ ucfirst($comparison['direction']) }} Band {{ $comparison['band'] }}</strong>
          @if($comparison['direct_matches'])
            <span>Direct meetings: {{ $comparison['direct_wins'] }}W / {{ $comparison['direct_losses'] }}L · Sets {{ $comparison['sets_won'] }} / {{ $comparison['sets_lost'] }}</span>
            @if($comparison['direction'] === 'higher' && $comparison['direct_wins'])<strong>Review: {{ $comparison['direct_wins'] }} direct win(s) over the next higher band</strong>@endif
            @foreach($comparison['opponents'] as $opponent)<span>{{ $opponent['name'] }} · Roster {{ implode(', ', $opponent['ranks']) }} · {{ $opponent['wins'] }}W / {{ $opponent['losses'] }}L · Sets {{ $opponent['sets_won'] }} / {{ $opponent['sets_lost'] }} from this player's perspective</span>@endforeach
          @else<p>No direct meetings with this band in the counted matches.</p>@endif
          @foreach($comparison['candidate_records'] as $record)<span>{{ $record['name'] }} · Roster {{ implode(', ', $record['ranks']) }} · Overall {{ $record['wins'] }}W / {{ $record['losses'] }}L · Sets {{ $record['sets_won'] }} / {{ $record['sets_lost'] }}</span>@endforeach
          </div>
        @endforeach
      </details>
      @foreach($player['cross_band_review'] as $review)
        <details class="result-review-notice result-review-details"><summary><strong>Review consecutive roster records</strong> · {{ $review['team'] }} · Ranks {{ implode(', ', $review['ranks']) }}</summary><p>Each consecutive player has at least two completed matches and more wins than losses in this setup. Different opponents can affect these records; the band-first order is retained.</p><div class="result-review-records">@foreach($review['records'] as $rank => $record)<span>Rank {{ $rank }}: {{ $record['wins'] }}W / {{ $record['losses'] }}L</span>@endforeach</div></details>
      @endforeach
      @if(count($player['ranks']) > 1)<p class="result-review-notice">Roster ranks differ across teams. Ranking uses rank {{ $player['rank'] }}; review before selection.@if($player['band_attribution_uncertain']) Ranks span different bands, so the lost-all-in-band flag is withheld.@endif</p>@endif
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
