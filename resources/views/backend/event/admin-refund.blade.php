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

      {{-- Errors are shown via the global toastr flash handler --}}

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
              <strong>Wallet</strong> — credit R{{ number_format($gross, 2) }} to
              @if($payer)
                <strong>{{ trim($payer->name . ' ' . $payer->surname) }}</strong>'s Cape Tennis wallet
              @else
                the payer's Cape Tennis wallet
              @endif
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

          {{-- Reason (shown when "No Refund" selected) --}}
          <div class="mt-3" id="no-refund-reason-block" style="display:none;">
            <label for="reason" class="form-label fw-semibold">Reason for no refund <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text"
                   id="reason"
                   name="reason"
                   class="form-control"
                   maxlength="255"
                   placeholder="e.g. Late withdrawal, post-deadline, event already started"
                   value="{{ old('reason') }}">
          </div>

        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-warning">
            <i class="ti ti-check me-1"></i> Process Refund
          </button>
          <button type="button" class="btn btn-outline-secondary" id="cancel-withdraw-btn">
            <i class="ti ti-x me-1"></i> Cancel
          </button>
        </div>

      </form>

      <form method="POST"
            id="cancel-withdraw-form"
            action="{{ route('admin.registration.refund.cancel', [$event, $registration]) }}"
            style="display:none;">
        @csrf
        @method('DELETE')
      </form>

    </div>
  </div>

</div>

@endsection

@section('page-script')
<script>
document.getElementById('cancel-withdraw-btn').addEventListener('click', function () {
  if (confirm('Cancel this withdrawal? The player will be restored to active status.')) {
    document.getElementById('cancel-withdraw-form').submit();
  }
});

// Show/hide reason field based on "No Refund" selection
document.querySelectorAll('input[name="method"]').forEach(function (radio) {
  radio.addEventListener('change', function () {
    var block = document.getElementById('no-refund-reason-block');
    block.style.display = this.value === 'none' ? 'block' : 'none';
  });
});

// Show on page load if "none" already selected (e.g. validation error)
(function () {
  var selected = document.querySelector('input[name="method"]:checked');
  if (selected && selected.value === 'none') {
    document.getElementById('no-refund-reason-block').style.display = 'block';
  }
})();
</script>
@endsection
