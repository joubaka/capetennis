<section aria-labelledby="opponent-pattern-heading">
  <h3 class="h6" id="opponent-pattern-heading">Across seasons</h3>
  <p class="record-note">Blue shows wins and orange shows losses in your saved matches.</p>
  @foreach($record['opponent_trend'] as $trend)
    <div class="record-season"><strong>{{ $trend['year'] }}</strong><div style="flex:1;max-width:30rem"><span>{{ $trend['wins'] }} wins · {{ $trend['losses'] }} losses · {{ $trend['rate'] }}% win rate</span><div aria-hidden="true" style="display:flex;height:8px;border-radius:5px;overflow:hidden;background:#ff9f43;margin-top:.5rem"><span style="background:#004177;width:{{ $trend['rate'] }}%"></span></div>@if($trend['played'] < 5)<small class="record-note">Limited record: {{ $trend['played'] }} {{ $trend['played'] === 1 ? 'meeting' : 'meetings' }}.</small>@endif</div></div>
  @endforeach
</section>
