@extends('layouts.layoutMaster')

@section('title', 'Series Rankings')

@section('content')
<div class="container">
  <h4 class="mb-4">Rankings: {{ $series->name }}</h4>

  <div class="row">
    @forelse ($finalRankings as $categoryKey => $rankings)
      <div class="col-md-6">
        <div class="card mb-4">
          <div class="card-header bg-light">
            <h5 class="mb-0">
              {{ \App\Models\Category::find($categoryKey)?->name ?? 'Unknown Category' }}
            </h5>
          </div>

          <div class="card-body p-0">
            <table class="table mb-0 table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Player</th>
                  <th>Scores</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($rankings as $i => $row)
                  @php
                    $best = collect($row['best']);
                  @endphp
                  <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['player']->name }} {{ $row['player']->surname }}</td>
                    <td>
                      @foreach ($row['scores'] as $scoreData)
                        @php
                          // Check if this score is one of the best (to color it)
                          $isBest = $best->contains(function ($b) use ($scoreData) {
                            return $b['score'] === $scoreData['score'] && $b['event'] === $scoreData['event'];
                          });

                          if ($isBest) {
                            // remove one match to avoid double matches with duplicates
                            $best = $best->reject(function ($b) use ($scoreData) {
                              return $b['score'] === $scoreData['score'] && $b['event'] === $scoreData['event'];
                            });
                          }
                        @endphp
                        <span class="badge {{ $isBest ? 'bg-success' : 'bg-secondary' }} me-1 mb-1">
                          {{ $scoreData['score'] }}
                          <div class="d-block small" style="font-size: 0.7em;"></div>
                        </span>
                      @endforeach
                    </td>
                    <td><strong>{{ $row['total'] }}</strong></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="alert alert-warning">No rankings available yet.</div>
      </div>
    @endforelse
  </div>
</div>
@endsection
