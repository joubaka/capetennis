@extends('layouts.backend')
@section('title', 'Manage Player Plus access')
@section('content')
<div class="container-xxl container-p-y" style="max-width:1150px;overflow-wrap:anywhere" data-plus-management>
  @include('backend.player-plus.manage-style')
  <a href="{{ route('superadmin.player-plus.manage.index') }}">← Player Plus management</a>
  <h1 class="h3 mt-3">{{ $player->full_name }}</h1><p class="text-muted">Profile #{{ $player->id }} · Player Plus</p>
  @if(session('success'))<p class="alert alert-success" role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><strong>The change was not saved.</strong>@foreach($errors->all() as $error)<p class="mb-0">{{ $error }}</p>@endforeach</div>@endif
  <div class="card card-body mb-4">
    <h2 class="h5">{{ $player->plusOverride?->mode === 'suspended' ? 'Access deactivated' : ($active ? 'Access active' : 'No current access') }}</h2>
    @if($player->plusMembership)<p>Recorded membership term: {{ $player->plusMembership->starts_at->format('d M Y H:i') }} – {{ $player->plusMembership->ends_at->format('d M Y H:i') }} · Order P{{ $player->plusMembership->order_id }}</p>@else<p>No paid membership term recorded.</p>@endif
    @if($player->plusOverride?->starts_at)<p>Admin grant: {{ $player->plusOverride->starts_at->format('d M Y H:i') }} – {{ $player->plusOverride->ends_at->format('d M Y H:i') }}. {{ $player->plusOverride->mode === 'inherit' ? 'Removed.' : ($player->plusOverride->mode === 'suspended' ? 'Suspended.' : 'Adds access during these dates.') }}</p>@endif
    <p class="mb-0 small text-muted">Access belongs to this player and their linked accounts. It does not give the payer player ownership.</p>
  </div>
  <section class="card card-body mb-4" aria-labelledby="access-heading">
    <h2 id="access-heading" class="h5">Change access</h2>
    <p class="alert alert-warning">Deactivating access does not stop automatic billing or refund payments. Paid records remain unchanged. Stop automatic renewal separately under the subscription order below when appropriate.</p>
    <form method="post" action="{{ route('superadmin.player-plus.manage.update', $player) }}">
      @csrf
      <input type="hidden" name="version" value="{{ $player->plusOverride?->version ?? 0 }}"><input type="hidden" name="request_token" value="{{ \Illuminate\Support\Str::uuid() }}">
      <label for="plus-action" class="form-label">Action</label>
      <select name="action" id="plus-action" class="form-select mb-3"><option value="grant" @selected(old('action') === 'grant')>Activate or edit dated admin access</option><option value="suspend" @selected(old('action') === 'suspend')>Deactivate all Player Plus access</option><option value="restore" @selected(old('action') === 'restore')>Restore underlying paid / admin access</option><option value="inherit" @selected(old('action') === 'inherit')>Remove admin grant · use paid access only</option></select>
      <p class="small text-muted">A dated admin grant adds access without recording a payment. Editing its dates can extend or shorten that grant. Restoring access uses existing term dates; expired terms remain expired. Removing a grant also clears deactivation.</p>
      <div class="row g-3 mb-3"><div class="col-md-6"><label for="plus-start" class="form-label">Admin access starts</label><input class="form-control" type="datetime-local" id="plus-start" name="starts_at" value="{{ old('starts_at', $player->plusOverride?->starts_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"></div><div class="col-md-6"><label for="plus-end" class="form-label">Admin access ends</label><input class="form-control" type="datetime-local" id="plus-end" name="ends_at" value="{{ old('ends_at', $player->plusOverride?->ends_at?->format('Y-m-d\TH:i') ?? now()->addYear()->format('Y-m-d\TH:i')) }}"></div></div>
      <p class="small text-muted">Dates use {{ config('app.timezone') }}. End time is exclusive. Dates apply only to dated admin access.</p>
      <label for="plus-reason" class="form-label">Reason / support reference</label><textarea class="form-control mb-3" id="plus-reason" name="reason" minlength="10" maxlength="1500" rows="3" required>{{ old('reason') }}</textarea>
      <label class="d-flex align-items-start gap-2"><input type="checkbox" name="confirm" value="1" required><span>I checked the player and dates and understand this changes access only.</span></label>
      <button class="btn btn-primary mt-3">Save access change</button>
    </form>
  </section>
  <section class="card card-body mb-4" aria-labelledby="orders-heading">
    <h2 id="orders-heading" class="h5">Orders and payment problems</h2>
    @forelse($orders as $order)<div class="plus-order"><strong>P{{ $order->id }} · {{ $order->status }}</strong><p class="mb-1">{{ $order->plan }} · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} · {{ $order->created_at->format('d M Y H:i') }}</p><p class="mb-1">Original payer: {{ $order->user?->name ?? 'Unavailable' }} · {{ $order->user?->email }}<br>Payment: {{ $order->payfast_paid ? 'Verified' : 'Not verified' }} @if($order->payfast_pf_payment_id) · {{ $order->payfast_pf_payment_id }} @endif</p>
      @if($order->reconciliation_reason)<p class="text-warning mb-1">{{ $order->reconciliation_reason }}</p><a class="btn btn-outline-primary" href="{{ route('superadmin.player-plus.show', $order) }}">Review receipts and record resolution</a>@endif
      @if($order->status === 'pending')<p class="small mt-2 mb-0">{{ $order->expires_at->isFuture() ? 'Payment window ends '.$order->expires_at->format('d M Y H:i').'. Allow verified payment to finish.' : 'Checkout expired. The payer can start a fresh checkout; delayed payments require reconciliation.' }}</p>@endif
      @php($subscription = $order->subscription_order_id ? $order->subscriptionOrder?->subscription : $order->subscription)
      @if($subscription)<p class="mt-2 mb-1">Subscription: {{ $subscription->status }} · Next billing: {{ $subscription->next_billing_at?->format('d M Y H:i') ?? 'Not recorded' }}@if($subscription->cancellation_requested_at)<br>Cancellation requested {{ $subscription->cancellation_requested_at->format('d M Y H:i') }}@endif @if($subscription->cancelled_at)<br>Provider cancellation confirmed {{ $subscription->cancelled_at->format('d M Y H:i') }}@endif</p>@endif
      @if(($order->status === 'pending') || ($subscription && ! $subscription->cancelled_at && ! $order->subscription_order_id))
      <details class="mt-3"><summary class="py-2">Resolve this order</summary><form method="post" action="{{ route('superadmin.player-plus.manage.order-action', [$player, $order]) }}" class="mt-2">@csrf
        @if($subscription && ! $subscription->cancelled_at)
          <input type="hidden" name="action" value="stop-renewal"><p>This asks PayFast to stop future automatic charges. Existing paid access remains unchanged. If PayFast cannot confirm cancellation, charges may continue; retry after checking the status.</p>
        @else<input type="hidden" name="action" value="close"><p>Close an unpaid checkout so its payer can retry. Checkouts with payment evidence or a live five-minute payment window cannot be closed.</p>@endif
        <label class="form-label" for="order-reason-{{ $order->id }}">Reason / support reference</label><textarea id="order-reason-{{ $order->id }}" class="form-control mb-3" name="reason" rows="2" minlength="10" maxlength="1500" required></textarea>
        <label class="d-flex gap-2"><input type="checkbox" name="confirm" value="1" required><span>I checked this order, its payer and the requested action.</span></label><button class="btn btn-outline-danger mt-3">{{ $subscription && ! $subscription->cancelled_at ? 'Stop automatic renewal / retry cancellation' : 'Close unpaid checkout' }}</button>
      </form></details>@endif
    </div>@empty<p>No orders. You can grant dated admin access above.</p>@endforelse
    {{ $orders->links() }}
  </section>
  <section class="card card-body" aria-labelledby="history-heading"><h2 id="history-heading" class="h5">Access audit history</h2>
    @forelse($history as $item)<div class="plus-order"><strong>{{ $item->created_at }} · {{ $item->actor_name ?? 'Recorded actor' }}</strong><p class="mb-1">{{ $item->metadata['operation'] ?? '' }} · {{ $item->before['mode'] ?? 'No override' }} → {{ $item->after['mode'] ?? '' }}</p><p class="plus-history mb-0">{{ $item->reason }}</p><p class="small mb-0">Grant dates: {{ $item->after['starts_at'] ?? '—' }} – {{ $item->after['ends_at'] ?? '—' }}</p></div>@empty<p>No admin access changes recorded.</p>@endforelse
    {{ $history->links() }}
  </section>
</div>
@endsection
