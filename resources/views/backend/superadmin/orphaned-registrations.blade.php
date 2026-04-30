@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-alert-triangle me-2 text-danger"></i>Orphaned Registrations</h4>
      <p class="text-muted mb-0">Paid orders where registration records were not created — likely due to an ITN processing failure.</p>
    </div>
    <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i>Back to Super Admin
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      <i class="ti ti-check me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="ti ti-x me-2"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show">
      <i class="ti ti-alert-triangle me-2"></i>{{ session('warning') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="ti ti-x me-2"></i>
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- ── SANDBOX / TEST RECORDS ─────────────────────────────────────── --}}
  @if($sandboxOrphans->isNotEmpty())
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
      <i class="ti ti-flask" style="font-size:1.4rem;"></i>
      <div>
        <strong>{{ $sandboxOrphans->count() }} sandbox/test order(s) detected.</strong>
        These were paid via the PayFast sandbox and should never create real registrations.
        You can safely delete all traces of these test records below.
      </div>
    </div>

    @foreach($sandboxOrphans as $orphan)
      <div class="card mb-3 border-warning">
        <div class="card-header bg-label-warning d-flex justify-content-between align-items-center">
          <div>
            <span class="badge bg-warning text-dark me-2"><i class="ti ti-flask me-1"></i>SANDBOX</span>
            <strong>Order #{{ $orphan->order->id }}</strong>
            <span class="text-muted ms-2 small">PF Ref: {{ $orphan->pf_payment_id ?? '—' }}</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">By: {{ optional($orphan->order->user)->name ?? 'Unknown' }}</span>
            <span class="fw-bold">R{{ number_format($orphan->total, 2) }}</span>
            <form method="POST" action="{{ route('superadmin.orphans.purge', $orphan->order) }}"
                  onsubmit="return confirm('Permanently delete ALL test data for order #{{ $orphan->order->id }}?\n\nThis will remove:\n- The order & order items\n- The registration(s)\n- The player_registrations pivot rows\n- Any category_event_registrations rows\n- The sandbox transaction record\n\nThis cannot be undone.')">
              @csrf
              @method('DELETE')
              <button class="btn btn-warning btn-sm text-dark">
                <i class="ti ti-trash me-1"></i>Delete Test Data
              </button>
            </form>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Player</th><th>Event</th><th>Category</th><th>Price</th><th>CER</th></tr>
            </thead>
            <tbody>
              @foreach($orphan->order->items as $item)
                @php
                  $isMissing = $orphan->missing_items->contains('id', $item->id);
                  $player    = $item->player;
                  $event     = optional($item->category_event)->event;
                  $cat       = optional(optional($item->category_event)->category)->name ?? '—';
                @endphp
                <tr>
                  <td>{{ optional($player)->name }} {{ optional($player)->surname }}</td>
                  <td><small>{{ optional($event)->name ?? '—' }}</small></td>
                  <td>{{ $cat }}</td>
                  <td>R{{ number_format($item->item_price, 2) }}</td>
                  <td>
                    @if($isMissing)
                      <span class="badge bg-secondary">No CER (expected)</span>
                    @else
                      <span class="badge bg-warning text-dark">CER exists (test data)</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach

    <hr class="my-4">
  @endif

  {{-- ── REAL ORPHANS ────────────────────────────────────────────────── --}}
  @if($orphans->isEmpty())
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="ti ti-circle-check text-success" style="font-size:3rem;"></i>
        <h5 class="mt-3 text-success">All real paid orders have complete registration records.</h5>
        <p class="text-muted">No orphaned registrations found.</p>
      </div>
    </div>
  @else
    <div class="alert alert-danger">
      <i class="ti ti-alert-circle me-2"></i>
      <strong>{{ $orphans->count() }} real order(s)</strong> have missing registration records.
      Each represents a player who paid but was not registered. Use <strong>Repair</strong> to fix.
    </div>

    @foreach($orphans as $orphan)
      <div class="card mb-4 border-danger">
        <div class="card-header bg-label-danger d-flex justify-content-between align-items-center">
          <div>
            <strong>Order #{{ $orphan->order->id }}</strong>
            <span class="badge bg-success ms-2">Paid</span>
            <span class="text-muted ms-2 small">PF Ref: {{ $orphan->pf_payment_id ?? '—' }}</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">By: {{ optional($orphan->order->user)->name ?? 'Unknown' }}</span>
            <span class="fw-bold text-success">R{{ number_format($orphan->total, 2) }}</span>
            <form method="POST" action="{{ route('superadmin.orphans.repair', $orphan->order) }}"
                  onsubmit="return confirm('Repair {{ $orphan->missing_items->count() }} missing registration(s) for order #{{ $orphan->order->id }}?')">
              @csrf
              <button class="btn btn-danger btn-sm">
                <i class="ti ti-tool me-1"></i>Repair ({{ $orphan->missing_items->count() }} missing)
              </button>
            </form>
            <form method="POST" action="{{ route('superadmin.orphans.delete-real', $orphan->order) }}"
                  onsubmit="return confirm('DELETE order #{{ $orphan->order->id }} permanently?\n\nThis will remove:\n- The order & order items\n- The registration(s) & player links\n\nThe PayFast transaction record (PF: {{ $orphan->pf_payment_id }}) will be KEPT for financial audit.\n\nThis cannot be undone.')">
              @csrf
              @method('DELETE')
              <button class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-trash me-1"></i>Delete
              </button>
            </form>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Player</th><th>Event</th><th>Category</th><th>Price</th><th>CER Status</th></tr>
            </thead>
            <tbody>
              @foreach($orphan->order->items as $item)
                @php
                  $isMissing = $orphan->missing_items->contains('id', $item->id);
                  $player    = $item->player;
                  $event     = optional($item->category_event)->event;
                  $cat       = optional(optional($item->category_event)->category)->name ?? '—';
                @endphp
                <tr class="{{ $isMissing ? 'table-danger' : '' }}">
                  <td>{{ optional($player)->name }} {{ optional($player)->surname }}</td>
                  <td><small>{{ optional($event)->name ?? '—' }}</small></td>
                  <td>{{ $cat }}</td>
                  <td>R{{ number_format($item->item_price, 2) }}</td>
                  <td>
                    @if($isMissing)
                      <span class="badge bg-danger"><i class="ti ti-x me-1"></i>Missing</span>
                    @else
                      <span class="badge bg-success"><i class="ti ti-check me-1"></i>OK</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  @endif

</div>
@endsection
