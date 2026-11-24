@extends('layouts.backend')
@section('title', $event->name . ' – Withdrawals')
@section('content')
<div class="container-xl">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div><h1 class="h4">{{ $event->name }} — Withdrawals</h1><p class="text-muted mb-0">{{ number_format($withdrawals->total()) }} withdrawn entry records. Refund status is separate from withdrawal.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.events.finances', $event) }}">Back to finances</a>
  </div>
  <div class="card"><div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Player(s)</th><th>Division</th><th>Withdrawn</th><th>Refund status</th><th>Record</th></tr></thead>
    <tbody>@forelse($withdrawals as $withdrawal)
      <tr><td>{{ $withdrawal->players ?: 'Player unavailable' }}@if(!empty($withdrawal->team))<small class="d-block text-muted">{{ $withdrawal->team }}</small>@endif</td><td>{{ $withdrawal->division ?? 'Unnamed division' }}</td><td>{{ $withdrawal->withdrawn_at ? \Illuminate\Support\Carbon::parse($withdrawal->withdrawn_at)->timezone(config('app.timezone'))->format('d M Y H:i') : 'Date unavailable' }}</td><td>{{ $withdrawal->refund_label }}</td><td>{{ $withdrawal->source }} #{{ $withdrawal->id }}@if($withdrawal->archived)<small class="d-block text-muted">Archived withdrawal</small>@endif</td></tr>
    @empty<tr><td colspan="5">No participant withdrawals recorded.</td></tr>@endforelse</tbody>
  </table></div><div class="card-body">{{ $withdrawals->links() }}</div></div>
</div>
@endsection
