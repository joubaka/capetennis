@foreach($row['shared_contributions'] ?? [] as $contribution)
  <small class="d-block text-muted text-wrap">Includes {{ \App\Support\StandingsPoints::format($contribution['points']) }} shared points from {{ $contribution['source'] }} ({{ $contribution['mode'] === 'full' ? 'full points' : 'equal split' }}).</small>
@endforeach
