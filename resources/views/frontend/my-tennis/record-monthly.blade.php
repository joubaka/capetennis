@php
  $months = $record['monthly'] ?? [];
  $largest = max([0, ...array_values(array_map(fn ($month) => $month['wins'] + $month['losses'], $months))]);
@endphp
<section class="overview-chart" aria-labelledby="record-monthly-heading" data-match-activity>
  <h3 id="record-monthly-heading">Match activity</h3>
  <p class="record-note mb-0">Wins and losses · {{ $record['year'] ?: 'Latest 12 saved months' }}@if($record['year'] === '' && $months) · {{ reset($months)['label'] }} – {{ end($months)['label'] }} @endif</p>
  @if($largest > 0)
    <ul class="record-month-chart" aria-label="Monthly match activity">
      @foreach($months as $key => $month)
        @php($count = $month['wins'] + $month['losses'])
        <li>
          @if($count)<a class="record-month-link" href="{{ request()->fullUrlWithQuery(['record_month' => $key, 'page' => null]) }}#match-results" aria-label="{{ $month['label'] }}: {{ $month['wins'] }} wins, {{ $month['losses'] }} losses. View saved matches" @if(($record['record_month'] ?? '') === $key) aria-current="true" @endif>
          @else<div class="record-month-link" aria-label="{{ $month['label'] }}: no saved matches">@endif
            <span class="record-month-count" aria-hidden="true">{{ $count }}</span>
            <span class="record-month-column" aria-hidden="true"><span class="record-month-bars">
              <span style="height:{{ 100 * $month['losses'] / $largest }}%;background:#f4a340"></span>
              <span style="height:{{ 100 * $month['wins'] / $largest }}%;background:#145c8d"></span>
            </span></span>
            <small aria-hidden="true">{{ substr($month['label'], 0, 3) }}</small>
          @if($count)</a>@else</div>@endif
        </li>
      @endforeach
    </ul>
    <p class="record-note mt-2 mb-0"><span class="record-key" style="background:#145c8d"></span> Wins <span class="record-key ms-2" style="background:#f4a340"></span> Losses <span class="ms-2">Select a month to view matches.</span></p>
    <details>
      <summary>Monthly counts and match links</summary>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <caption class="visually-hidden">Recorded wins and losses by month</caption>
        <thead><tr><th scope="col">Month</th><th scope="col">Wins</th><th scope="col">Losses</th></tr></thead>
        <tbody>
          @foreach($months as $key => $month)
            <tr><th scope="row">@if($month['wins'] || $month['losses'])<a href="{{ request()->fullUrlWithQuery(['record_month' => $key, 'page' => null]) }}#match-results" @if(($record['record_month'] ?? '') === $key) aria-current="true" @endif>{{ $month['label'] }}</a>@else{{ $month['label'] }}@endif</th><td>{{ $month['wins'] }}</td><td>{{ $month['losses'] }}</td></tr>
          @endforeach
        </tbody>
      </table></div>
    </details>
  @else
    <p class="record-note mt-3 mb-0" role="status">No saved match activity in this chart’s time window.</p>
  @endif
</section>
