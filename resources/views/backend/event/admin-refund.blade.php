@extends('layouts/layoutMaster')

@section('title', 'Admin Refund – ' . $event->name)

@section('content')
<div class="container-xl">

  {{-- BREADCRUMB --}}
  <div class="d-flex align-items-center gap-2 mb-3 text-muted" style="font-size:.85rem;">
    <a href="{{ route('admin.events.entries.new', $event) }}" class="text-decoration-none">
      {{ $event->name }}
    </a>
    <span>›</span>
    <span>Admin Refund</span>
  </div>

  <div class="card" style="max-width:600px;">
    <div class="card-header bg-warning text-dark d-flex align-items-center gap-2">
      <i class="ti ti-cash-banknote fs-5"></i>
      <strong>Issue Refund</strong>
    </div>

    <div class="card-body">

      {{-- Flash messages --}}
      @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif

      {{-- Summary --}}
      <dl class="row mb-4">
        <dt class="col-sm-4">Event</dt>
        <dd class="col-sm-8">{{ $event->name }}</dd>

        <dt class="col-sm-4">Category</dt>
        <dd class="col-sm-8">{{ $category }}</dd>

        <dt class="col-sm-4">Player(s)</dt>
        <dd class="col-sm-8">
          @forelse($players as $p)
            {{ trim($p->name . ' ' . $p->surname) }}@unless($loop->last), @endunless
          @empty
            —
          @endforelse
        </dd>

        <dt class="col-sm-4">Amount Paid</dt>
        <dd class="col-sm-8 fw-bold text-success">R{{ number_format($gross, 2) }}</dd>

        @if($walletPaid > 0)
          <dt class="col-sm-4 text-muted">‌ ↳ Wallet</dt>
          <dd class="col-sm-8 text-muted">R{{ number_format($walletPaid, 2) }}</dd>
        @endif

        @if($payfastGross > 0)
          <dt class="col-sm-4 text-muted">‌ ↳ PayFast</dt>
          <dd class="col-sm-8 text-muted">
            R{{ number_format($payfastGross, 2) }}
            @if($pfPaymentId)
              <code class="ms-1">{{ $pfPaymentId }}</code>
            @endif
          </dd>
        @endif

        <dt class="col-sm-4">Refund Status</dt>
        <dd class="col-sm-8">
          <span class="badge bg-secondary">{{ $registration->refund_status ?? 'not_refunded' }}</span>
        </dd>
      </dl>

      <hr>

      {{-- Refund Method Form --}}
      <form method="POST"
            action="{{ route('admin.registration.refund.store', [$event, $registration]) }}"
            onsubmit="return confirm('Issue this refund now?');">
        @csrf

        <p class="fw-semibold mb-3">Choose a refund method:</p>

        <div class="mb-3">

          {{-- Wallet option --}}
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="method" id="method_wallet" value="wallet"
                   {{ old('method') === 'wallet' ? 'checked' : '' }} required>
            <label class="form-check-label" for="method_wallet">
              <i class="ti ti-wallet me-1 text-primary"></i>
              <strong>Wallet</strong> — credit R{{ number_format($gross, 2) }} to the player's Cape Tennis wallet
            </label>
          </div>

          {{-- PayFast option (only if there is a pf_payment_id) --}}
          @if($pfPaymentId)
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="method" id="method_payfast" value="payfast"
                     {{ old('method') === 'payfast' ? 'checked' : '' }}>
              <label class="form-check-label" for="method_payfast">
                <i class="ti ti-credit-card me-1 text-success"></i>
                <strong>PayFast</strong> — refund R{{ number_format($payfastGross, 2) }} back to the original payment card/account
                <small class="text-muted d-block ms-4">PayFast ID: <code>{{ $pfPaymentId }}</code></small>
              </label>
            </div>
          @endif

          {{-- No refund option --}}
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="method" id="method_none" value="none"
                   {{ old('method') === 'none' ? 'checked' : '' }}>
            <label class="form-check-label" for="method_none">
              <i class="ti ti-ban me-1 text-danger"></i>
              <strong>No Refund</strong> — record withdrawal only, no money returned
            </label>
          </div>

        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-warning">
            <i class="ti ti-check me-1"></i> Process Refund
          </button>
          <a href="{{ route('admin.events.entries.new', $event) }}" class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>

      </form>

    </div>
  </div>

</div>
@endsection
