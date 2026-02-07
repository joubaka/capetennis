<div class="p-3 border-bottom">
  <h5 class="mb-3 text-{{ $color ?? 'secondary' }}">{{ $age }}</h5>

  <div class="table-responsive-sm">
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="text-center" style="width: 40px;">#</th>
          <th>Region</th>
          <th class="text-end">Played</th>
          <th class="text-end">Wins</th>
          <th class="text-end">Losses</th>
          <th class="text-end">Points</th>
        </tr>
      </thead>
      <tbody>
        @php $rank = 1; @endphp
        @foreach($regions as $regionName => $stats)
          <tr>
            <td class="text-center fw-bold">{{ $rank++ }}</td>
            <td class="fw-semibold text-truncate" style="max-width:120px">{{ $regionName }}</td>
            <td class="text-end"><span class="badge bg-label-secondary">{{ $stats['played'] }}</span></td>
            <td class="text-end"><span class="badge bg-label-success">{{ $stats['wins'] }}</span></td>
            <td class="text-end"><span class="badge bg-label-danger">{{ $stats['losses'] }}</span></td>
            <td class="text-end"><span class="badge bg-label-{{ $color ?? 'primary' }} fs-6">{{ $stats['points'] }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
