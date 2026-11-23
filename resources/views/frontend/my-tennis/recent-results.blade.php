@forelse($recentResults as $result)
  <div class="border-bottom py-2">
    <div class="fw-semibold">{{ $result['event'] }}</div>
    <div class="small text-muted">{{ $result['category'] }} · @if($result['position'] !== null)Place {{ $result['position'] }}@else<a href="{{ route('events.show', $result['event_id']) }}#event-draws-match-times">Draw results</a>@endif</div>
  </div>
@empty
  <p class="text-muted mb-0">No published results yet.</p>
@endforelse
