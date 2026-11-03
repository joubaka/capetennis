<section class="record-sheet" aria-labelledby="opponent-pattern-heading">
  <h2 id="opponent-pattern-heading">Across seasons</h2>
  <p class="record-note">{{ $record['opponent']['name'] }} · Calendar years within the selected season scope only. Blue: wins · Orange: losses. These are recorded outcomes, not a forecast or rating comparison.</p>
  @foreach($record['opponent_trend'] as $trend)
    <div class="record-season"><strong>{{ $trend['year'] }}</strong><div style="flex:1;max-width:30rem"><span>{{ $trend['wins'] }} wins · {{ $trend['losses'] }} losses · {{ $trend['rate'] }}% win rate</span><div aria-hidden="true" style="display:flex;height:8px;border-radius:5px;overflow:hidden;background:#ff9f43;margin-top:.5rem"><span style="background:#004177;width:{{ $trend['rate'] }}%"></span></div>@if($trend['played'] < 5)<small class="record-note">Limited record: {{ $trend['played'] }} {{ $trend['played'] === 1 ? 'meeting' : 'meetings' }}.</small>@endif</div></div>
  @endforeach
  <h3 class="h6 mt-3">Recent meetings in this selection</h3>
  <p class="record-note">Up to five, ordered by recorded date. Estimated dates and same-date meetings do not establish exact playing order; no streak is inferred.</p>
  @foreach($record['opponent_recent'] as $recent)<div class="record-season"><span>{{ $recent['date'] }}<br><small class="record-note">{{ $recent['date_basis'] }}</small></span><span>{{ $recent['won'] ? 'Win' : 'Loss' }} · {{ $recent['score'] }}</span></div>@endforeach
</section>
