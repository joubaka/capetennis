<div class="p-3 border-bottom mb-4">
  <h5 class="mb-3 text-{{ $color }}">{{ $title }}</h5>

  <div class="table-responsive">
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Region</th>
          <th class="text-end">Singles</th>
          <th class="text-end">Doubles</th>
          <th class="text-end">Reverse</th>
          <th class="text-end">Mixed</th>
          <th class="text-end">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($regions as $regionName => $types)
          <tr>
            <td class="fw-semibold">{{ $regionName }}</td>
            <td class="text-end"><span class="badge bg-label-secondary">{{ $types['Singles']['points'] ?? 0 }}</span></td>
            <td class="text-end"><span class="badge bg-label-secondary">{{ $types['Doubles']['points'] ?? 0 }}</span></td>
            <td class="text-end"><span class="badge bg-label-secondary">{{ $types['Reverse']['points'] ?? 0 }}</span></td>
            <td class="text-end"><span class="badge bg-label-secondary">{{ $types['Mixed']['points'] ?? 0 }}</span></td>
            <td class="text-end"><span class="badge bg-label-{{ $color }} fs-6">{{ $types['Total']['points'] ?? 0 }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
