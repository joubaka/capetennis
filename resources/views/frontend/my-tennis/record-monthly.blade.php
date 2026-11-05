@php
  $months = $record['monthly'] ?? [];
  $largest = max([0, ...array_values(array_map(fn ($month) => $month['wins'] + $month['losses'], $months))]);
@endphp
@if($largest > 0)
  <section class="record-sheet" aria-labelledby="record-monthly-heading">
    <h2 id="record-monthly-heading">Recorded matches by month</h2>
    <p class="record-note">Saved singles results · {{ $record['year'] ?: 'Latest 12 saved months' }}. @if($record['as_of'] ?? null) Records as of {{ $record['as_of'] }}. @endif Some match dates are estimated. A blank month means no matches in this record.</p>
    <div aria-hidden="true" class="record-month-chart">
      @foreach($months as $month)
        <div>
          <div class="record-month-column">
            <span style="height:{{ 100 * $month['losses'] / $largest }}%;background:#f4a340"></span>
            <span style="height:{{ 100 * $month['wins'] / $largest }}%;background:#004177"></span>
          </div>
          <small>{{ substr($month['label'], 0, 3) }}</small>
        </div>
      @endforeach
    </div>
    <p class="record-note mt-2"><span class="record-key" style="background:#004177"></span> Wins <span class="record-key ms-2" style="background:#f4a340"></span> Losses</p>
    <details>
      <summary>Monthly counts and match links</summary>
      <table class="table table-sm mb-0">
        <caption class="visually-hidden">Recorded wins and losses by month</caption>
        <thead><tr><th scope="col">Month</th><th scope="col">Wins</th><th scope="col">Losses</th></tr></thead>
        <tbody>
          @foreach($months as $key => $month)
            <tr><th scope="row">
              @if($month['wins'] || $month['losses'])
                <a href="{{ request()->fullUrlWithQuery(['record_month' => $key, 'page' => null]) }}#match-results" @if(($record['record_month'] ?? '') === $key) aria-current="true" @endif>{{ $month['label'] }}</a>
              @else{{ $month['label'] }}@endif
            </th><td>{{ $month['wins'] }}</td><td>{{ $month['losses'] }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </details>
  </section>
@endif
