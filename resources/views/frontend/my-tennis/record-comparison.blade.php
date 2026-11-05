<section class="record-sheet" aria-labelledby="season-compare-heading">
  <style>.ct-record .record-comparison-table{min-width:420px;font-size:.85rem}.ct-record .record-comparison-table th,.ct-record .record-comparison-table td{white-space:nowrap;overflow-wrap:normal;letter-spacing:normal;text-transform:none;padding:.5rem .35rem}.ct-record .record-comparison-table caption{white-space:normal;overflow-wrap:normal}</style>
  <h2 id="season-compare-heading">Compare seasons</h2>
  <form method="get" class="record-filter mb-3" action="{{ route('my.tennis.record', ['player' => $recordPlayer->id]) }}">
    @if($record['year'] !== '')<input type="hidden" name="year" value="{{ $record['year'] }}">@endif
    @foreach(['first' => 'Starting season', 'second' => 'Comparison season'] as $side => $label)
      <div><label class="form-label" for="compare-{{ $side }}">{{ $label }}</label><select id="compare-{{ $side }}" class="form-select" name="compare_{{ $side }}"><option value="">Choose a season</option>
        @if($record['comparison'][$side.'_year'] !== '' && !in_array($record['comparison'][$side.'_year'], array_map('strval', $record['years']), true))<option value="{{ $record['comparison'][$side.'_year'] }}" selected>{{ $record['comparison'][$side.'_year'] }} · no available results</option>@endif
        @foreach($record['years'] as $availableYear)<option value="{{ $availableYear }}" @selected((string)$availableYear === $record['comparison'][$side.'_year'])>{{ $availableYear }}</option>@endforeach
      </select></div>
    @endforeach
    <button class="btn btn-primary" type="submit">Compare seasons</button>
  </form>
  @if($record['comparison']['reason'])<p class="record-note mb-0" role="status">{{ $record['comparison']['reason'] }}</p>@endif
  @if($record['comparison']['delta'] !== null)
    <div class="row g-3 mb-3" aria-hidden="true">
      @foreach(['played' => 'Recorded matches', 'rate' => 'Win rate'] as $metric => $label)
        @php($maximum = $metric === 'rate' ? 100 : max($record['comparison']['first']['played'], $record['comparison']['second']['played']))
        <div class="col-sm-6">
          <strong>{{ $label }}</strong>
          @foreach(['first', 'second'] as $side)
            @php($value = $record['comparison'][$side][$metric])
            <div class="record-note mt-2">{{ $record['comparison'][$side.'_year'] }} · {{ $value }}{{ $metric === 'rate' ? '%' : '' }}</div>
            <div class="rounded" style="height:.5rem;background:#e8eef5"><div class="rounded h-100" style="width:{{ 100 * $value / $maximum }}%;background:{{ $side === 'first' ? '#004177' : '#f4a340' }}"></div></div>
          @endforeach
        </div>
      @endforeach
    </div>
  @endif
  <p class="record-note d-sm-none mb-2">Scroll across to see both seasons and the change.</p>
  <div class="table-responsive" role="region" aria-label="Season comparison table; scroll horizontally on small screens" tabindex="0"><table class="table table-sm mb-0 record-comparison-table">
    <caption>Win-rate change is shown in percentage points (pp).</caption>
    <thead><tr><th scope="col">Measure</th><th scope="col">{{ $record['comparison']['first_year'] ?: 'Starting' }}</th><th scope="col">{{ $record['comparison']['second_year'] ?: 'Comparison' }}</th><th scope="col">Change</th></tr></thead>
    <tbody>@foreach(['played' => 'Matches', 'wins' => 'Wins', 'losses' => 'Losses', 'rate' => 'Win rate', 'events' => 'Events', 'opponents' => 'Opponents'] as $metric => $label)
      <tr><th scope="row">{{ $label }}</th>@foreach(['first', 'second'] as $side)<td>{{ $record['comparison'][$side]['played'] ? ($metric === 'rate' ? $record['comparison'][$side][$metric].'%' : $record['comparison'][$side][$metric]) : '—' }}</td>@endforeach<td>@if($record['comparison']['delta'] !== null){{ $record['comparison']['delta'][$metric] > 0 ? '+' : '' }}{{ $record['comparison']['delta'][$metric] }}{{ $metric === 'rate' ? ' pp' : '' }}@else—@endif</td></tr>
    @endforeach</tbody>
  </table></div>
  @foreach(['first', 'second'] as $side)@if($record['comparison'][$side]['played'])<p class="record-note mt-2 mb-0">{{ $record['comparison'][$side.'_year'] }} recorded dates: {{ $record['comparison'][$side]['from'] }} to {{ $record['comparison'][$side]['through'] }}.</p>@endif @endforeach
  @if(($record['comparison']['first']['played'] > 0 && $record['comparison']['first']['played'] < 5) || ($record['comparison']['second']['played'] > 0 && $record['comparison']['second']['played'] < 5))<p class="record-note mt-2 mb-0">Small sample · fewer than five saved matches in at least one season.</p>@endif
</section>
