<div class="mb-5">
  <h4 class="text-center my-3">🏆 {{ $age }} Scoreboard</h4>

  <table class="table table-bordered align-middle text-center">
    <thead class="table-dark">
      <tr>
        <th>Region</th>
        <th>Singles</th>
        <th>Doubles</th>
        <th>Mixed</th>
        <th>Reverse</th>
        <th>Total</th>
      </tr>
    </thead>

    <tbody>
      @foreach (['Girls', 'Boys', 'Overall'] as $gender)
        @if(!empty($regions[$gender]))
          <tr class="table-secondary">
            <td colspan="6" class="fw-bold text-start">{{ strtoupper($gender) }}</td>
          </tr>

          @foreach ($regions[$gender] as $region => $types)
            <tr>
              <td class="fw-semibold">{{ $region }}</td>
              <td>{{ $types['Singles']['points'] ?? 0 }}</td>
              <td>{{ $types['Doubles']['points'] ?? 0 }}</td>
              <td>{{ $types['Mixed']['points'] ?? 0 }}</td>
              <td>{{ $types['Reverse']['points'] ?? 0 }}</td>
              <td class="fw-bold">{{ $types['Total']['points'] ?? 0 }}</td>
            </tr>
          @endforeach
        @endif
      @endforeach
    </tbody>
  </table>
</div>
