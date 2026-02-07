<div class="mb-5">
  <h5 class="mb-3">{{ $age }}</h5>

  @foreach($groups as $gender => $regions)
    <h6 class="text-muted fw-bold mb-2">{{ strtoupper($gender) }}</h6>

    <div class="table-responsive mb-4">
      <table class="table table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Region</th>
            <th class="text-center">Played</th>
            <th class="text-center">Wins</th>
            <th class="text-center">Losses</th>
            <th class="text-center">Points</th>
            <th class="text-center">Breakdown<br><small>(Boys / Girls / Mixed)</small></th>
          </tr>
        </thead>
        <tbody>
          @foreach($regions as $rank => $row)
            @php
              $regionName = $rank;
              $played = $row['Total']['played'] ?? 144;
              $wins = $row['Total']['wins'] ?? 0;
              $losses = $row['Total']['losses'] ?? 0;
              $points = $row['Total']['points'] ?? 0;

              $boys = $row['Breakdown']['boys_points'] ?? 0;
              $girls = $row['Breakdown']['girls_points'] ?? 0;
              $mixed = $row['Breakdown']['mixed_points'] ?? 0;
            @endphp

            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ Str::limit($regionName, 40) }}</td>
              <td class="text-center"><span class="badge bg-label-secondary">{{ $played }}</span></td>
              <td class="text-center"><span class="badge bg-label-success">{{ $wins }}</span></td>
              <td class="text-center"><span class="badge bg-label-danger">{{ $losses }}</span></td>
              <td class="text-center"><span class="badge bg-label-primary">{{ $points }}</span></td>
              <td class="text-center">
                <span class="badge bg-label-info me-1">{{ $boys }}</span>
                <span class="badge bg-label-warning me-1">{{ $girls }}</span>
                <span class="badge bg-label-secondary">{{ $mixed }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endforeach
</div>
