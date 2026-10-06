<style>
  table { width: 100%; border-collapse: collapse; font-size: 11px; }
  th, td { border: 1px solid #d9dee3; padding: 6px; vertical-align: top; }
  th { background: #eef3fa; text-align: left; }
  .fixture-player-label { line-height: 1.6; }
</style>
<h1>{{ $name }}</h1>
<table>
  <thead>
    <tr><th>Match</th><th>Home</th><th>Away</th><th>Not before</th><th>Venue / Court</th><th>Result</th></tr>
  </thead>
  <tbody>
    @foreach($fixtures as $fx)
      <tr id="fixture-{{ $fx->id }}">
        <td>{{ $fx->match_nr ?: $fx->id }}<br>Round {{ $fx->round_nr }} · Tie {{ $fx->tie_nr }}</td>
        <td>@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])</td>
        <td>@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])</td>
        <td>{{ $fx->scheduled_at?->format('D d M @ H:i') ?? 'Time to follow' }}</td>
        <td>{{ $fx->venue?->name ?? 'Venue to follow' }}@if($fx->court_label)<br>Court {{ $fx->court_label }}@endif</td>
        <td>@foreach($fx->fixtureResults as $result){{ $result->team1_score }} - {{ $result->team2_score }}@if(!$loop->last), @endif @endforeach</td>
      </tr>
    @endforeach
  </tbody>
</table>
