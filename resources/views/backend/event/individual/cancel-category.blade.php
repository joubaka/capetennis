@extends('layouts.backend')
@section('title', 'Cancel category')
@section('content')
<div class="card"><div class="card-body">
  <h1 class="h4">Cancel {{ $categoryEvent->category->name }}</h1>
  <p>{{ $event->name }}</p>
  <a href="{{ route('admin.events.entries.new', $event) }}" class="btn btn-outline-secondary mb-3">Back to entries</a>
  @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
  @if($categoryEvent->isCancelled())
    <div class="alert alert-info">This category was cancelled on {{ $categoryEvent->cancelled_at->format('d M Y H:i') }}. It is closed to new entries.</div>
    <p>{{ $categoryEvent->cancellation['snapshot']['reason'] ?? '' }}</p>
    <p>{{ $categoryEvent->cancellation['entries'] ?? 0 }} entries · R{{ number_format($categoryEvent->cancellation['total'] ?? 0, 2) }} refunded to original payer wallets.</p>
    <a href="{{ route('backend.event-communications.index', $event) }}?batch={{ $categoryEvent->cancellation['batch_id'] ?? '' }}">View email report</a>
  @elseif(!$preview)
    <p>Review the affected players and contacts before cancelling. Confirmation closes this category, withdraws its entries, refunds full verified entry fees to the original payers’ wallets with no handling fee, and queues the notices. This option is available before a draw is created; unresolved payments require finance review first.</p>
    <form method="POST" action="{{ route('admin.category.cancel.preview', [$event, $categoryEvent]) }}">@csrf
      <label class="form-label" for="reason">Reason for cancellation</label>
      <textarea id="reason" name="reason" class="form-control mb-3" required maxlength="2000" rows="4">{{ old('reason', 'Unfortunately, we have not received sufficient entries to run this category.') }}</textarea>
      <button class="btn btn-danger">Review cancellation</button>
    </form>
  @else
    <h2 class="h5">Review cancellation</h2>
    <p><strong>{{ count($preview['rows']) }} entries · R{{ number_format($preview['total'], 2) }} full wallet refunds</strong></p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Player</th><th>Wallet credit</th><th>Original payer</th><th>Notification contacts</th></tr></thead><tbody>
    @foreach($preview['rows'] as $row)<tr><td>{{ $row['player'] }} @if($row['status'] === 'withdrawn')<small>(already withdrawn)</small>@endif</td><td>R{{ number_format($row['amount'], 2) }}</td><td>{{ $row['funding']['payer_name'] ?? 'Free entry — no refund' }}<br>{{ $row['funding']['to'] ?? '' }}</td><td>@foreach($row['emails'] as $email)<div>{{ $email }}</div>@endforeach</td></tr>@endforeach
    </tbody></table></div>
    <p>Player contacts receive a notification-only cancellation notice, once per address. Each original payer receives their own refund confirmation. Free entries receive no wallet credit.</p>
    <h2 class="h5">Email preview</h2><p><strong>{{ $preview['subject'] }}</strong></p><div class="border rounded p-3 mb-3" style="white-space:pre-wrap">{{ $preview['message'] }}</div>
    <p class="small text-muted">From: {{ $preview['sender']['from_name'] }} · Reply to: {{ $preview['sender']['reply_to'] }}</p>
    <form method="POST" action="{{ route('admin.category.cancel.store', [$event, $categoryEvent]) }}">@csrf
      <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
      <label class="d-block mb-3"><input type="checkbox" name="confirm" value="1" required> I confirm cancellation, full wallet refunds and the emails shown above.</label>
      <button class="btn btn-danger">Cancel category and refund wallets</button>
      <a href="{{ route('admin.category.cancel.show', [$event, $categoryEvent]) }}" class="btn btn-outline-secondary">Edit reason</a>
    </form>
  @endif
</div></div>
@endsection
