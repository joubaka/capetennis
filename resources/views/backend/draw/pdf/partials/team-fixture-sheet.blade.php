<table class="team-fixture-sheet">
  <thead><tr><th style="width:5%">#</th><th style="width:6%">Match</th><th style="width:28%">Home</th><th style="width:28%">Away</th><th style="width:8%">Result</th><th style="width:12%">Scheduled</th><th style="width:13%">Venue / Court</th></tr></thead>
  <tbody>
  @php
    $previousSheetDrawRound = null;
  @endphp
  @forelse($fixtures as $fx)
    @php [$homeClass, $awayClass] = \App\Support\ResultPresentation::classes($fx); @endphp
    @php
      $sheetDrawRound = $fx->draw_id.':'.$fx->round_nr;
    @endphp
    @if($sheetDrawRound !== $previousSheetDrawRound)
    <tr class="sheet-round-heading" data-print-draw-round="{{ $sheetDrawRound }}"><th colspan="7">{{ $fx->draw?->drawName }} · Round {{ $fx->round_nr }}</th></tr>
    @php
      $previousSheetDrawRound = $sheetDrawRound;
    @endphp
    @endif
    <tr id="fixture-{{ $fx->id }}">
      <td>{{ $fx->id }}</td>
      <td>{{ $fx->rubber_sequence ?? $fx->match_nr ?? $fx->home_rank_nr ?? '—' }}</td>
      @foreach(['home', 'away'] as $side)
      <td style="color:{{ ($side === 'home' ? $homeClass : $awayClass) === 'winner-home' ? '#166534' : (($side === 'home' ? $homeClass : $awayClass) === 'loser-home' ? '#b91c1c' : '#172033') }};"><span class="sheet-team">{{ $fx->lineup_display[$side]['region'] ?: $fx->tie_display[$side] }} · </span>
        @forelse($fx->lineup_display[$side]['players'] as $player)
          @if(!$loop->first) + @endif
          @if($player['rank'])({{ $player['rank'] }}) @endif{{ $player['name'] }}
        @empty
          Players to be confirmed
        @endforelse
      </td>
      @endforeach
      <td>@foreach($fx->fixtureResults as $result){{ $result->team1_score }}-{{ $result->team2_score }}@if(!$loop->last), @endif @endforeach</td>
      <td><span class="sheet-time">{{ $fx->scheduled_at?->format('d M H:i') ?? 'Time to follow' }}</span></td>
      <td>{{ $fx->venue?->name ?? 'Venue to follow' }}@if($fx->court_label)<br>Court {{ $fx->court_label }}@endif</td>
    </tr>
  @empty
    <tr><td colspan="7">No fixtures found.</td></tr>
  @endforelse
  </tbody>
</table>
