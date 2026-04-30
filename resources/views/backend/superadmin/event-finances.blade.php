@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Finances (Super Admin)')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('page-style')
<style>
  td.dt-toggle { cursor:pointer; text-align:center; user-select:none; }
  tr.shown td.dt-toggle i { transform:rotate(90deg); }
  td.dt-toggle i { font-size:1.1rem; color:#696cff; transition:.2s; }

  .child-table { background:#f9fafc; border-radius:.375rem; }
  .child-table thead th { background:#eef1ff; font-size:.75rem; text-transform:uppercase; }
  .child-table td { font-size:.8rem; }

  tr.refund-row { background:#fff4f4 !important; }
  tr.payout-row { background:#f0f7ff !important; }

  #txTable td.text-end { font-variant-numeric: tabular-nums; }
  #txTable { table-layout: fixed; width: 100%; }
  #txTable th, #txTable td { white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        {{ $event->name }}
      </h4>
      <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('transactions.pdf', $event) }}" class="btn btn-outline-primary btn-sm">
          Export Transactions
        </a>
        <a href="{{ route('superadmin.finances') }}" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back to Dashboard
        </a>
      </div>
    </div>
  </div>

  {{-- ALERTS --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="ti ti-circle-check me-1"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- SUMMARY CARDS --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-start border-primary">
        <div class="card-body">
          <small class="text-muted">
            Gross Income ({{ $totalEntries }} entries{{ isset($refundCount) && $refundCount > 0 ? ", {$refundCount} refunds" : '' }})
          </small>
          <h4>R {{ number_format($totalGross, 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-warning">
        <div class="card-body">
          <small class="text-muted">PayFast Fees (net)</small>
          <h4 class="text-warning">− R {{ number_format(abs($totalPayfastFees), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-danger">
        <div class="card-body">
          <small class="text-muted">Cape Tennis Fees (net)</small>
          <h4 class="text-danger">− R {{ number_format(abs($totalCapeTennisFees), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-success">
        <div class="card-body">
          <small class="text-muted">Net Tournament Income</small>
          <h4 class="text-success">R {{ number_format($netTournamentIncome, 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-danger">
        <div class="card-body">
          <small class="text-muted">Total Paid Out to Convenors</small>
          <h4 class="text-danger">− R {{ number_format($totalPaidOut, 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-info">
        <div class="card-body">
          <small class="text-muted">Unpaid Balance</small>
          <h4 class="{{ $balance < 0 ? 'text-danger' : 'text-info' }}">R {{ number_format($balance, 2) }}</h4>
        </div>
      </div>
    </div>
  </div>

  {{-- TRANSACTIONS TABLE --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Transactions</h5>
    </div>
    <div class="card-body p-0">
      <table id="txTable" class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:32px;"></th>
            <th style="width:90px;">Date</th>
            <th style="width:80px;">Type</th>
            <th style="width:180px;">Participant</th>
            <th style="width:90px;">Method</th>
            <th style="width:80px;" class="text-end">Gross</th>
            <th style="width:100px;" class="text-end">PayFast Fee</th>
            <th style="width:110px;" class="text-end">Cape Tennis Fee</th>
            <th style="width:110px;" class="text-end">Net to Event</th>
          </tr>
        </thead>
        <tbody>
        @foreach($transactions as $tx)
          @php
            $payload = collect();

            if ($tx->type === 'payment' && isset($tx->order)) {
              $payload = collect([
                [
                  'mode'           => 'payment_summary',
                  'pf_payment_id'  => $tx->pf_payment_id ?? '—',
                  'entries'        => $tx->entryCount ?? 1,
                  'gross'          => number_format($tx->gross, 2),
                  'payfast_gross'  => number_format($tx->payfastGross ?? $tx->gross, 2),
                  'wallet_used'    => number_format($tx->walletUsed ?? 0, 2),
                  'pf_fee'         => number_format(abs($tx->fee), 2),
                  'cape_fee'       => number_format(abs($tx->capeFee), 2),
                  'net'            => number_format($tx->net, 2),
                ]
              ])->merge(
                collect($tx->order->items ?? [])->map(fn ($item) => [
                  'mode'     => 'payment_item',
                  'player'   => trim(($item->player->name ?? '') . ' ' . ($item->player->surname ?? '')),
                  'category' => optional($item->category_event->category)->name,
                  'price'    => number_format($item->item_price ?? 0, 2),
                ])
              );
            }

            if ($tx->type === 'refund') {
              $payload = collect([[
                'mode'           => 'refund',
                'pf_payment_id'  => $tx->pf_payment_id ?? '—',
                'paid_at'        => optional($tx->paid_at)->format('Y-m-d'),
                'category'       => $tx->category ?? '—',
                'gross_original' => number_format($tx->gross, 2),
                'payfast_fee'    => number_format(abs($tx->fee), 2),
                'cape_fee'       => number_format(abs($tx->capeFee), 2),
                'refund_total'   => number_format(abs($tx->net), 2),
              ]]);
            }

            if ($tx->type === 'payout') {
              $payload = collect([[
                'mode'        => 'payout',
                'description' => $tx->description ?? '—',
                'reference'   => $tx->reference ?? '—',
                'amount'      => number_format(abs($tx->amount ?? $tx->gross), 2),
              ]]);
            }
          @endphp

          <tr class="{{ $tx->type === 'refund' ? 'refund-row' : ($tx->type === 'payout' ? 'payout-row' : '') }}"
              @if($payload->count()) data-items='@json($payload)' @endif>

            <td class="dt-toggle">
              @if($payload->count())
                <i class="ti ti-chevron-right"></i>
              @endif
            </td>

            <td>{{ \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d') }}</td>

            <td>
              @php
                $badgeClass = match($tx->type) {
                  'payment' => 'bg-success',
                  'refund'  => 'bg-danger',
                  'payout'  => 'bg-info',
                  default   => 'bg-secondary',
                };
              @endphp
              <span class="badge {{ $badgeClass }}">{{ ucfirst($tx->type) }}</span>
            </td>

            <td>{{ $tx->player ?? '—' }}</td>
            <td>{{ $tx->method }}</td>

            <td class="text-end">
              @if($tx->type === 'refund' || $tx->type === 'payout')
                − R {{ number_format(abs($tx->gross), 2) }}
              @else
                R {{ number_format($tx->gross, 2) }}
              @endif
            </td>

            <td class="text-end {{ $tx->fee >= 0 ? 'text-success' : 'text-warning' }}">
              @if($tx->fee != 0)
                {{ $tx->fee > 0 ? '+ ' : '− ' }} R {{ number_format(abs($tx->fee), 2) }}
              @else —
              @endif
            </td>

            <td class="text-end {{ $tx->capeFee >= 0 ? 'text-success' : 'text-danger' }}">
              @if($tx->capeFee != 0)
                {{ $tx->capeFee > 0 ? '+ ' : '− ' }} R {{ number_format(abs($tx->capeFee), 2) }}
              @else —
              @endif
            </td>

            <td class="text-end {{ $tx->net < 0 ? 'text-danger' : 'text-success' }}">
              {{ $tx->net < 0 ? '− ' : '' }} R {{ number_format(abs($tx->net), 2) }}
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- PAYOUTS SECTION --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ti ti-cash-banknote me-2 text-info"></i>Convenor Payouts</h5>
      <button class="btn btn-success btn-sm" data-bs-toggle="collapse" data-bs-target="#payoutFormCollapse">
        <i class="ti ti-plus me-1"></i>Add Payout
      </button>
    </div>

    {{-- ADD PAYOUT FORM --}}
    <div class="collapse" id="payoutFormCollapse">
      <div class="card-body border-bottom bg-light">
        <form method="POST" action="{{ route('superadmin.finances.payout.store', $event) }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Convenor</label>
              <select name="convenor_id" class="form-select">
                <option value="">— Select convenor —</option>
                @foreach($convenors as $c)
                  <option value="{{ $c->id }}">{{ $c->user->name ?? 'Unknown' }} ({{ ucfirst($c->role) }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Recipient Name <small class="text-muted">(if no convenor)</small></label>
              <input type="text" name="recipient_name" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-2">
              <label class="form-label">Amount (R) <span class="text-danger">*</span></label>
              <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Payment Method <span class="text-danger">*</span></label>
              <select name="payment_method" class="form-select" required>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cash">Cash</option>
                <option value="eft">EFT</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Date</label>
              <input type="date" name="paid_at" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Tournament payout – expenses">
            </div>
            <div class="col-md-3">
              <label class="form-label">Reference / Proof</label>
              <input type="text" name="reference" class="form-control" placeholder="e.g. EFT#12345">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-success">
                <i class="ti ti-check me-1"></i>Save Payout
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    {{-- PAYOUTS TABLE --}}
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Recipient</th>
            <th>Method</th>
            <th>Reference</th>
            <th>Description</th>
            <th class="text-end">Amount</th>
            <th>Paid By</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($payoutModels as $payout)
            <tr>
              <td>{{ optional($payout->paid_at)->format('Y-m-d') ?? '—' }}</td>
              <td class="fw-semibold">{{ $payout->display_name }}</td>
              <td>{{ ucfirst(str_replace('_', ' ', $payout->payment_method)) }}</td>
              <td><code>{{ $payout->reference ?? '—' }}</code></td>
              <td>{{ $payout->description ?? '—' }}</td>
              <td class="text-end text-danger fw-bold">R {{ number_format($payout->amount, 2) }}</td>
              <td>{{ optional($payout->paidByUser)->name ?? '—' }}</td>
              <td>
                <form method="POST" action="{{ route('superadmin.finances.payout.destroy', $payout) }}"
                      onsubmit="return confirm('Delete this payout?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-icon btn-sm btn-outline-danger" title="Delete">
                    <i class="ti ti-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-3">No payouts recorded yet.</td>
            </tr>
          @endforelse
        </tbody>
        @if($payoutModels->count())
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="5">Total Paid Out</td>
              <td class="text-end text-danger">R {{ number_format($totalPaidOut, 2) }}</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>

  {{-- FULL PLAYER REFUNDS SECTION --}}
  @if($eligibleForRefund->count() || $eligibleTeamOrders->count())
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="ti ti-receipt-refund me-2 text-warning"></i>
        Full Player Refunds
        <span class="badge bg-warning text-dark ms-2">{{ $eligibleForRefund->count() + $eligibleTeamOrders->count() }}</span>
      </h5>
      <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#fullRefundCollapse">
        <i class="ti ti-chevron-down me-1"></i>Show / Hide
      </button>
    </div>

    <div class="collapse show" id="fullRefundCollapse">
      <div class="card-body pb-1">
        <p class="text-muted small mb-3">
          Issue a <strong>full refund (no handling fee deducted)</strong> to a player's wallet or via bank transfer.
          Normal player-initiated refunds deduct a handling fee; this option bypasses that fee.
        </p>
      </div>

      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Player(s)</th>
              <th>Category</th>
              <th>Status</th>
              <th class="text-end">Amount Paid</th>
              <th class="text-end">Refund Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>

            {{-- Individual registrations --}}
            @foreach($eligibleForRefund as $reg)
              @php
                $payment = $reg->paymentInfo();
                $refundGross = round(($payment['gross'] ?? 0) + ($payment['wallet_paid'] ?? 0), 2);
              @endphp
              <tr>
                <td class="fw-semibold">{{ $reg->display_name }}</td>
                <td>{{ optional($reg->categoryEvent->category)->name ?? '—' }}</td>
                <td>
                  @if($reg->status === 'withdrawn')
                    <span class="badge bg-secondary">Withdrawn</span>
                  @else
                    <span class="badge bg-success">Active</span>
                  @endif
                </td>
                <td class="text-end">R {{ number_format($refundGross, 2) }}</td>
                <td class="text-end">
                  @if($reg->refund_status === 'pending')
                    <span class="badge bg-warning text-dark">Pending</span>
                  @else
                    <span class="badge bg-light text-muted border">None</span>
                  @endif
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-outline-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#fullRefundModal"
                          data-player="{{ $reg->display_name }}"
                          data-amount="{{ number_format($refundGross, 2) }}"
                          data-route="{{ route('superadmin.finances.full-refund.registration', [$event, $reg]) }}">
                    <i class="ti ti-cash-banknote me-1"></i>Full Refund
                  </button>
                </td>
              </tr>
            @endforeach

            {{-- Team payment orders --}}
            @foreach($eligibleTeamOrders as $order)
              <tr>
                <td class="fw-semibold">{{ optional($order->player)->full_name ?? '—' }}</td>
                <td><span class="badge bg-info">Team</span></td>
                <td><span class="badge bg-success">Active</span></td>
                <td class="text-end">R {{ number_format($order->total_amount, 2) }}</td>
                <td class="text-end">
                  @if($order->refund_status === 'pending')
                    <span class="badge bg-warning text-dark">Pending</span>
                  @else
                    <span class="badge bg-light text-muted border">None</span>
                  @endif
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-outline-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#fullRefundModal"
                          data-player="{{ optional($order->player)->full_name ?? '—' }}"
                          data-amount="{{ number_format($order->total_amount, 2) }}"
                          data-route="{{ route('superadmin.finances.full-refund.team', [$event, $order]) }}">
                    <i class="ti ti-cash-banknote me-1"></i>Full Refund
                  </button>
                </td>
              </tr>
            @endforeach

          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  {{-- FULL REFUND MODAL --}}
  <div class="modal fade" id="fullRefundModal" tabindex="-1" aria-labelledby="fullRefundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="fullRefundForm" method="POST" action="">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="fullRefundModalLabel">
              <i class="ti ti-receipt-refund me-2 text-warning"></i>Issue Full Refund
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
              <i class="ti ti-alert-triangle fs-5"></i>
              <span>This will refund the <strong>full amount</strong> — no handling fee will be deducted.</span>
            </div>

            <dl class="row mb-3">
              <dt class="col-sm-4">Player</dt>
              <dd class="col-sm-8 fw-semibold" id="modalPlayerName">—</dd>
              <dt class="col-sm-4">Refund Amount</dt>
              <dd class="col-sm-8">
                <span class="fs-5 text-success fw-bold">R <span id="modalAmount">0.00</span></span>
                <small class="text-muted d-block">No handling fee deducted</small>
              </dd>
            </dl>

            <hr>

            <div class="mb-3">
              <label class="form-label fw-semibold">Refund Method <span class="text-danger">*</span></label>

              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="method" id="methodWallet" value="wallet" required>
                <label class="form-check-label" for="methodWallet">
                  <i class="ti ti-wallet me-1 text-success"></i>
                  <strong>Wallet</strong> — instant credit to player's Cape Tennis wallet
                </label>
              </div>

              <div class="form-check">
                <input class="form-check-input" type="radio" name="method" id="methodBank" value="bank">
                <label class="form-check-label" for="methodBank">
                  <i class="ti ti-building-bank me-1 text-primary"></i>
                  <strong>Bank Transfer</strong> — marked as pending; process payment manually (or via PayFast if applicable)
                </label>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" id="fullRefundSubmit" class="btn btn-warning fw-semibold">
              <i class="ti ti-cash-banknote me-1"></i>Confirm Full Refund
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<script>
$(function () {
  const table = $('#txTable').DataTable({
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: 0 }],
    autoWidth: false
  });

  function renderItems(items) {
    if (!items.length) return '';

    if (items[0].mode === 'refund') {
      const r = items[0];
      return `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>PayFast ID</th><td><code>${r.pf_payment_id}</code></td></tr>
          <tr><th>Original Payment Date</th><td>${r.paid_at}</td></tr>
          <tr><th>Category</th><td>${r.category}</td></tr>
          <tr><th>Gross Paid</th><td>R ${r.gross_original}</td></tr>
          <tr><th>PayFast Fee (recovered)</th><td>R ${r.payfast_fee}</td></tr>
          <tr><th>Cape Tennis Fee (recovered)</th><td>R ${r.cape_fee}</td></tr>
          <tr class="fw-bold text-danger"><th>Total Refund Impact</th><td>R ${r.refund_total}</td></tr>
        </tbody></table>`;
    }

    if (items[0].mode === 'payout') {
      const p = items[0];
      return `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>Description</th><td>${p.description}</td></tr>
          <tr><th>Reference</th><td><code>${p.reference}</code></td></tr>
          <tr class="fw-bold text-info"><th>Amount Paid Out</th><td>R ${p.amount}</td></tr>
        </tbody></table>`;
    }

    if (items[0].mode === 'payment_summary') {
      const s = items[0];
      const players = items.filter(i => i.mode === 'payment_item');

      const walletRow = parseFloat(s.wallet_used) > 0
        ? `<tr><th>Wallet Credit Applied</th><td class="text-info">R ${s.wallet_used}</td></tr>
           <tr><th>PayFast Amount</th><td>R ${s.payfast_gross}</td></tr>`
        : '';

      let html = `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>PayFast Reference</th><td><code>${s.pf_payment_id}</code></td></tr>
          <tr><th>Entries</th><td>${s.entries}</td></tr>
          <tr><th>Gross Paid</th><td>R ${s.gross}</td></tr>
          ${walletRow}
          <tr><th>PayFast Fee</th><td class="text-danger">− R ${s.pf_fee}</td></tr>
          <tr><th>Cape Tennis Fee</th><td class="text-danger">− R ${s.cape_fee}</td></tr>
          <tr class="fw-bold text-success"><th>Net to Event</th><td>R ${s.net}</td></tr>
        </tbody></table>`;

      if (players.length) {
        html += `<table class="table table-sm mb-0 child-table mt-2">
          <thead><tr><th>Player</th><th>Category</th><th class="text-end">Entry Price</th></tr></thead>
          <tbody>`;
        players.forEach(p => {
          html += `<tr><td>${p.player || '—'}</td><td>${p.category || '—'}</td><td class="text-end">R ${p.price}</td></tr>`;
        });
        html += `</tbody></table>`;
      }
      return html;
    }
    return '';
  }

  $('#txTable tbody').on('click', 'td.dt-toggle', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    const items = tr.data('items') || [];
    if (!items.length) return;
    row.child.isShown()
      ? (row.child.hide(), tr.removeClass('shown'))
      : (row.child(renderItems(items)).show(), tr.addClass('shown'));
  });
});

// Full Refund Modal: populate form action and display fields from button data attributes
const fullRefundModal = document.getElementById('fullRefundModal');
if (fullRefundModal) {
  fullRefundModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('modalPlayerName').textContent = btn.dataset.player || '—';
    document.getElementById('modalAmount').textContent = btn.dataset.amount || '0.00';
    document.getElementById('fullRefundForm').action = btn.dataset.route || '';

    // Reset radio buttons and re-enable submit button on each open
    fullRefundModal.querySelectorAll('input[name="method"]').forEach(r => r.checked = false);
    document.getElementById('fullRefundSubmit').disabled = false;
  });

  document.getElementById('fullRefundForm').addEventListener('submit', function () {
    document.getElementById('fullRefundSubmit').disabled = true;
  });
}
</script>
@endsection
