@extends('layouts.backend')

@section('title', $event->name . ' – Transactions')

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endsection

@section('page-style')
<style>
  td.dt-toggle { cursor:pointer; text-align:center; user-select:none; }
  tr.shown td.dt-toggle i { transform:rotate(90deg); }
  td.dt-toggle i { font-size:1.1rem; color:#696cff; transition:.2s; }

  .child-table {
    background:#f9fafc;
    border-radius:.375rem;
  }
  .child-table thead th {
    background:#eef1ff;
    font-size:.75rem;
    text-transform:uppercase;
  }
  .child-table td { font-size:.8rem; }

  tr.refund-row {
    background:#fff4f4 !important;
  }

  tr.admin-entry-row {
    background:#fffbf0 !important;
    opacity: 0.85;
  }
  tr.admin-entry-row td {
    color: #888 !important;
  }
  tr.admin-entry-row td.text-warning,
  tr.admin-entry-row td.text-danger {
    color: #aaa !important;
  }



  #transactionsTable td.text-end {
    font-variant-numeric: tabular-nums;
  }
  #transactionsTable {
    table-layout: fixed;
    width: 100%;
  }

  #transactionsTable th,
  #transactionsTable td {
    white-space: nowrap;
  }

  /* Tighten up the DataTables control row */
  .dt-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
    padding: .75rem 1rem;
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Tournament Transactions</h4>
      <div class="d-flex gap-2">
        <a href="{{ route('transactions.pdf', $event) }}" class="btn btn-outline-primary btn-sm">
          Export Transactions
        </a>
        <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back to Event
        </a>
      </div>
    </div>
  </div>

  {{-- SUMMARY --}}
  @php $totalWithdrawnCount = ($refundCount ?? 0) + ($noRefundCount ?? 0); @endphp
  <div class="row g-3 mb-4">

    {{-- 1. GROSS INCOME --}}
    <div class="col-md-2">
      <div class="card border-start border-primary h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Gross Income</small>
          <h4 class="mb-1">R {{ number_format($totalGross, 2) }}</h4>
          <small class="text-muted d-block">
            {{ $totalEntries }} total {{ $totalEntries === 1 ? 'entry' : 'entries' }}
            @if(($totalWithdrawnCount ?? 0) > 0) · {{ $totalWithdrawnCount }} withdrew @endif
            @if($refundCount > 0) · {{ $refundCount }} {{ $refundCount === 1 ? 'refund' : 'refunds' }} @endif
          </small>
          @php $payfastEntries = $totalEntries - $adminEntriesCount; @endphp
          @if($adminEntriesCount > 0)
            <hr class="my-2">
            <small class="text-muted d-block">
              <i class="ti ti-credit-card text-primary me-1"></i>
              {{ $payfastEntries }} via PayFast · <strong>R {{ number_format($totalGross, 2) }}</strong>
            </small>
            <small class="text-warning d-block mt-1">
              <i class="ti ti-cash text-warning me-1"></i>
              {{ $adminEntriesCount }} collected privately · <strong>R 0.00 to event</strong>
            </small>
          @endif
        </div>
      </div>
    </div>

    {{-- 2. WITHDRAWALS --}}
    <div class="col-md-2">
      <div class="card border-start border-danger h-100">
        <div class="card-body">
          @php
            $activeEntries = $totalEntries - ($completedRefundCount ?? 0);
          @endphp
          <small class="text-muted d-block mb-1">
            Withdrawals ({{ $totalWithdrawnCount }})
            @if(($pendingRefundCount ?? 0) > 0)
              <span class="badge bg-warning text-dark ms-1">{{ $pendingRefundCount }} pending</span>
            @endif
          </small>
          @if(($totalWithdrawals ?? 0) > 0)
            <h4 class="text-danger mb-1">− R {{ number_format($totalWithdrawals, 2) }}</h4>
            @if(($completedWithdrawalsTotal ?? 0) > 0)
              <small class="text-muted d-block">R {{ number_format($completedWithdrawalsTotal, 2) }} refunded</small>
            @endif
            @if(($pendingWithdrawalsTotal ?? 0) > 0)
              <small class="text-muted d-block text-warning">R {{ number_format($pendingWithdrawalsTotal, 2) }} pending</small>
            @endif
          @else
            <h4 class="text-danger mb-1">− R 0.00</h4>
          @endif
          @if(($noRefundCount ?? 0) > 0)
            <small class="text-muted d-block mt-1">
              {{ $noRefundCount }} withdrew · fees not refunded
            </small>
          @endif
        </div>
      </div>
    </div>

    {{-- 3. PAYFAST FEES --}}
    <div class="col-md-2">
      <div class="card border-start border-warning h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">PayFast Fees (net)</small>
          <h4 class="text-warning mb-1">− R {{ number_format(abs($totalPayfastFees), 2) }}</h4>
          @php $activePayfastEntries = $totalEntries - $completedRefundCount - $adminEntriesCount; @endphp
          @if($adminEntriesCount > 0)
            <small class="text-muted d-block">{{ $activePayfastEntries }} active PayFast {{ $activePayfastEntries === 1 ? 'entry' : 'entries' }}</small>
            <small class="text-muted d-block">{{ $adminEntriesCount }} admin = R 0.00 fee</small>
          @else
            <small class="text-muted d-block">{{ $totalEntries - $completedRefundCount }} active {{ ($totalEntries - $completedRefundCount) === 1 ? 'entry' : 'entries' }}</small>
          @endif
          @if($completedRefundCount > 0)
            <small class="text-muted d-block">{{ $completedRefundCount }} refunded — no PF fee</small>
          @endif
        </div>
      </div>
    </div>

    {{-- 4. CAPE TENNIS FEES --}}
    <div class="col-md-2">
      <div class="card border-start border-danger h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Cape Tennis Fees (net)</small>
          <h4 class="text-danger mb-1">− R {{ number_format(abs($totalCapeTennisFees), 2) }}</h4>
          @php
            $chargedEntries = max(0, $totalEntries - ($completedRefundCount ?? 0));
          @endphp
          <small class="text-muted d-block">
            {{ $chargedEntries }} fee-bearing {{ $chargedEntries === 1 ? 'entry' : 'entries' }} × R {{ number_format($feePerEntry, 2) }}
          </small>
          @if(($noRefundCount ?? 0) > 0)
            <small class="text-muted d-block">Includes {{ $noRefundCount }} withdrawn (not refunded)</small>
          @endif
          @if($adminEntriesCount > 0)
            <small class="text-muted d-block">Includes {{ $adminEntriesCount }} admin {{ $adminEntriesCount === 1 ? 'entry' : 'entries' }} charged Cape fee</small>
          @endif
        </div>
      </div>
    </div>

    {{-- 5. PAYOUTS --}}
    <div class="col-md-2">
      <div class="card border-start border-secondary h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Payouts</small>
          <h4 class="text-secondary mb-1">{{ $totalPayouts > 0 ? '− ' : '' }}R {{ number_format(abs($totalPayouts), 2) }}</h4>
          <small class="text-muted d-block">Paid to organiser</small>
        </div>
      </div>
    </div>

    {{-- 6. NET TOURNAMENT INCOME --}}
    <div class="col-md-2">
      <div class="card border-start border-success h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Net Tournament Income</small>
          <h4 class="{{ $netTournamentIncome >= 0 ? 'text-success' : 'text-danger' }} mb-1">R {{ number_format($netTournamentIncome, 2) }}</h4>
          @if($adminEntriesCount > 0)
            @php $privatelyCollected = round($adminEntriesCount * (float) $event->entryFee, 2); @endphp
            <small class="text-warning d-block">
              <i class="ti ti-alert-triangle me-1"></i>
              Excludes R {{ number_format($privatelyCollected, 2) }} privately collected
            </small>
          @endif
          <small class="text-muted d-block">After all fees &amp; payouts</small>
        </div>
      </div>
    </div>

  </div>


  {{-- TABLE --}}
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table id="transactionsTable" class="table table-striped mb-0 w-100">
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

            // PAYMENT CHILD DATA
          // =========================
// PAYMENT CHILD DATA
// =========================
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
      'player'   => trim(($item->player?->name ?? '') . ' ' . ($item->player?->surname ?? '')),
      'category' => $item->category_event?->category?->name ?? '—',
      'price'    => number_format($item->item_price ?? 0, 2),
    ])
  );
}


            // REFUND CHILD DATA
            if ($tx->type === 'refund') {
              $payload = collect([[
                'mode'              => 'refund',
                'refund_status'     => $tx->refund_status ?? '—',
                'pf_payment_id'     => $tx->pf_payment_id ?? '—',
                'paid_at'           => optional($tx->paid_at)->format('Y-m-d'),
                'category'          => $tx->category ?? '—',
                'gross_original'    => number_format(abs($tx->gross), 2),
                'payfast_fee'       => number_format($tx->displayFee ?? 0, 2),
                'cape_fee'          => number_format($tx->displayCapeFee ?? 0, 2),
                'withdrawal_fee'    => number_format($tx->withdrawalFee ?? 0, 2),
                'refund_total'      => number_format(abs($tx->net), 2),
              ]]);
            }

            // WITHDRAWAL (NO-REFUND) CHILD DATA
            if ($tx->type === 'withdrawal') {
              $payload = collect([[
                'mode'           => 'withdrawal',
                'category'       => $tx->category ?? '—',
                'paid_at'        => optional($tx->paid_at)->format('Y-m-d'),
                'original_gross' => number_format($tx->original_gross ?? 0, 2),
                'refund_status'  => 'No Refund Issued',
              ]]);
            }
          @endphp

          <tr class="{{ $tx->type === 'refund' ? 'refund-row' : ($tx->type === 'withdrawal' ? 'withdrawal-row text-muted' : ($tx->method === 'Admin Entry' ? 'admin-entry-row' : '')) }}"
              @if($payload->count()) data-items='@json($payload)' @endif
              @if($tx->method === 'Admin Entry') title="Collected privately — no refund possible" @endif
              @if($tx->type === 'withdrawal') title="Withdrawn — no refund issued" @endif>

            <td class="dt-toggle">
              @if($payload->count())
                <i class="ti ti-chevron-right"></i>
              @endif
            </td>

            <td>{{ \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d') }}</td>

            <td>
              @if($tx->type === 'payment' && $tx->method === 'Admin Entry')
                <span class="badge bg-secondary">Admin</span>
              @elseif($tx->type === 'payment')
                <span class="badge bg-success">Payment</span>
              @elseif($tx->type === 'refund')
                <span class="badge bg-danger">Refunded</span>
                @if(($tx->refund_status ?? '') === 'pending')
                  <span class="badge bg-warning text-dark ms-1"><i class="ti ti-clock me-1"></i>Bank Pending</span>
                @endif
              @elseif($tx->type === 'withdrawal')
                <span class="badge bg-secondary">Withdrawn</span>
              @elseif($tx->type === 'payout')
                <span class="badge bg-secondary">Payout</span>
              @else
                <span class="badge bg-secondary">{{ ucfirst($tx->type) }}</span>
              @endif
            </td>

            <td>{{ $tx->player ?? '—' }}</td>
            <td>
              @php
                $m = $tx->method ?? '';
              @endphp
              @if($m === 'PayFast')
                <span class="badge bg-success">PayFast</span>
              @elseif($m === 'Wallet')
                <span class="badge bg-info text-dark">Wallet</span>
              @elseif(str_contains($m, 'Wallet'))
                <span class="badge bg-success">PayFast</span>
                <span class="badge bg-info text-dark">+ Wallet</span>
              @elseif($m === 'Admin Entry')
                <span class="badge bg-secondary">Admin</span>
              @elseif($m)
                <span class="badge bg-light text-dark border">{{ $m }}</span>
              @else
                —
              @endif
            </td>

            {{-- Gross --}}
            <td class="text-end {{ $tx->type === 'payout' ? 'text-secondary' : ($tx->type === 'withdrawal' ? 'text-muted' : '') }}">
              @if($tx->type === 'withdrawal')
                @if(($tx->original_gross ?? 0) > 0)
                  <span class="text-muted">R {{ number_format($tx->original_gross, 2) }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              @else
                {{ in_array($tx->type, ['refund', 'payout']) ? '− ' : '' }}
                R {{ number_format(abs($tx->gross), 2) }}
              @endif
            </td>

            {{-- PayFast / Withdrawal Fee --}}
            <td class="text-end">
              @if($tx->fee != 0)
                <span class="{{ $tx->fee > 0 ? 'text-success' : 'text-warning' }}">
                  {{ $tx->fee > 0 ? '+ ' : '− ' }}R {{ number_format(abs($tx->fee), 2) }}
                </span>
              @else
                —
              @endif
            </td>

            {{-- Cape Tennis Fee --}}
            <td class="text-end">
              @if($tx->capeFee != 0)
                <span class="{{ $tx->capeFee > 0 ? 'text-success' : 'text-danger' }}">
                  {{ $tx->capeFee > 0 ? '+ ' : '− ' }}R {{ number_format(abs($tx->capeFee), 2) }}
                </span>
              @else
                —
              @endif
            </td>


            {{-- Net --}}
            <td class="text-end {{ $tx->net < 0 ? 'text-danger' : 'text-success' }}">
              {{ $tx->net < 0 ? '− ' : '' }}
              R {{ number_format(abs($tx->net), 2) }}
            </td>
          </tr>

        @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </div>

</div>
@endsection

@section('vendor-script')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
@endsection

@section('page-script')
<script>
$(function () {

  const table = $('#transactionsTable').DataTable({
    order: [[1, 'desc']],
    columnDefs: [
      { orderable: false, targets: 0 }
    ],
    autoWidth: false,
    dom:
      "<'dt-controls-row'<'d-flex align-items-center gap-2'l><'ms-auto'f>>" +
      "<'row'<'col-12'tr>>" +
      "<'dt-controls-row'<'text-muted small'i><'ms-auto'p>>",
    language: {
      search:         '',
      searchPlaceholder: 'Search…',
      lengthMenu:     '_MENU_ per page',
      info:           'Showing _START_–_END_ of _TOTAL_',
      paginate: {
        previous: '<i class="ti ti-chevron-left"></i>',
        next:     '<i class="ti ti-chevron-right"></i>'
      }
    }
  });


  function renderItems(items) {
    if (!items.length) return '';

    /* =========================
       REFUND
    ========================= */
    if (items[0].mode === 'refund') {
      const r = items[0];
      const statusBadge = r.refund_status === 'pending'
        ? `<span class="badge bg-warning text-dark">Pending</span>`
        : `<span class="badge bg-success">Completed</span>`;
      return `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            <tr><th>Status</th><td>${statusBadge}</td></tr>
            <tr><th>PayFast ID</th><td><code>${r.pf_payment_id}</code></td></tr>
            <tr><th>Original Payment Date</th><td>${r.paid_at}</td></tr>
            <tr><th>Category</th><td>${r.category}</td></tr>
            <tr><th>Gross Paid</th><td>R ${r.gross_original}</td></tr>
            <tr><th>PayFast Fee (recovered)</th><td>R ${r.payfast_fee}</td></tr>
            <tr><th>Cape Tennis Fee (recovered)</th><td>R ${r.cape_fee}</td></tr>
            <tr class="fw-bold text-danger">
              <th>Total Refund Impact</th>
              <td>R ${r.refund_total}</td>
            </tr>
          </tbody>
        </table>
      `;
    }

    /* =========================
       WITHDRAWAL (NO REFUND)
    ========================= */
    if (items[0].mode === 'withdrawal') {
      const w = items[0];
      return `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            <tr><th>Status</th><td><span class="badge bg-secondary">No Refund Issued</span></td></tr>
            <tr><th>Category</th><td>${w.category}</td></tr>
            <tr><th>Original Payment Date</th><td>${w.paid_at || '—'}</td></tr>
            <tr><th>Original Amount Paid</th><td class="text-muted">R ${w.original_gross}</td></tr>
            <tr><td colspan="2" class="text-muted small">This withdrawal was processed without a refund. The entry fee is retained.</td></tr>
          </tbody>
        </table>
      `;
    }

    /* =========================
       PAYMENT (SUMMARY + ITEMS)
    ========================= */
    if (items[0].mode === 'payment_summary') {

      const s = items[0];
      const players = items.filter(i => i.mode === 'payment_item');

      const walletRow = parseFloat(s.wallet_used) > 0
        ? `<tr><th>Wallet Credit Applied</th><td class="text-info">R ${s.wallet_used}</td></tr>
           <tr><th>PayFast Amount</th><td>R ${s.payfast_gross}</td></tr>`
        : '';

      let html = `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            ${s.pf_payment_id ? `<tr><th>PayFast Reference</th><td><code>${s.pf_payment_id}</code></td></tr>` : ''}
            <tr><th>Entries</th><td>${s.entries}</td></tr>
            <tr><th>Gross Paid</th><td>R ${s.gross}</td></tr>
            ${walletRow}
            <tr><th>PayFast Fee</th><td class="text-danger">− R ${s.pf_fee}</td></tr>
            <tr><th>Cape Tennis Fee</th><td class="text-danger">− R ${s.cape_fee}</td></tr>
            <tr class="fw-bold text-success">
              <th>Net to Event</th>
              <td>R ${s.net}</td>
            </tr>
          </tbody>
        </table>
      `;

      if (players.length) {
        html += `
          <table class="table table-sm mb-0 child-table mt-2">
            <thead>
              <tr>
                <th>Player</th>
                <th>Category</th>
                <th class="text-end">Entry Price</th>
              </tr>
            </thead>
            <tbody>
        `;

        players.forEach(p => {
          html += `
            <tr>
              <td>${p.player || '—'}</td>
              <td>${p.category || '—'}</td>
              <td class="text-end">R ${p.price}</td>
            </tr>
          `;
        });

        html += `</tbody></table>`;
      }

      return html;
    }

    return '';
  }

  $('#transactionsTable tbody').on('click', 'td.dt-toggle', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    const items = tr.data('items') || [];

    if (!items.length) return;

    row.child.isShown()
      ? (row.child.hide(), tr.removeClass('shown'))
      : (row.child(renderItems(items)).show(), tr.addClass('shown'));
  });

});
</script>

@endsection
