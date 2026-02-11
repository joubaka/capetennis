@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Transactions')

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
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-start border-primary">
        <div class="card-body">
          <small class="text-muted">Total Gross Income</small>
          <h4>R {{ number_format($totalGross, 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-start border-warning">
        <div class="card-body">
          <small class="text-muted">PayFast Fees</small>
          <h4 class="text-warning">− R {{ number_format(abs($totalPayfastFees), 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-start border-danger">
        <div class="card-body">
          <small class="text-muted">
            Cape Tennis Fees ({{ $totalEntries }} × R{{ $feePerEntry }})
          </small>
          <h4 class="text-danger">− R {{ number_format($totalCapeTennisFees, 2) }}</h4>
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
  </div>

  {{-- TABLE --}}
  <div class="card">
    <div class="card-body p-0">
      <table id="transactionsTable" class="table table-striped mb-0">
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
      'mode'          => 'payment_summary',
      'pf_payment_id' => $tx->pf_payment_id ?? '—',
      'entries'       => $tx->entryCount ?? 1,
      'gross'         => number_format($tx->gross, 2),
      'pf_fee'        => number_format(abs($tx->fee), 2),
      'cape_fee'      => number_format(abs($tx->capeFee), 2),
      'net'           => number_format($tx->net, 2),
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


            // REFUND CHILD DATA
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
          @endphp

          <tr class="{{ $tx->type === 'refund' ? 'refund-row' : '' }}"
              @if($payload->count()) data-items='@json($payload)' @endif>

            <td class="dt-toggle">
              @if($payload->count())
                <i class="ti ti-chevron-right"></i>
              @endif
            </td>

            <td>{{ \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d') }}</td>

            <td>
              <span class="badge {{ $tx->type === 'payment' ? 'bg-success' : 'bg-danger' }}">
                {{ ucfirst($tx->type) }}
              </span>
            </td>

            <td>{{ $tx->player ?? '—' }}</td>
            <td>{{ $tx->method }}</td>

            {{-- Gross --}}
            <td class="text-end">
              {{ $tx->type === 'refund' ? '− ' : '' }}
              R {{ number_format($tx->gross, 2) }}
            </td>

            {{-- PayFast Fee --}}
        <td class="text-end {{ $tx->fee >= 0 ? 'text-success' : 'text-warning' }}">
  @if($tx->fee != 0)
    {{ $tx->fee > 0 ? '+ ' : '− ' }}
    R {{ number_format(abs($tx->fee), 2) }}
  @else
    —
  @endif
</td>


            {{-- Cape Tennis Fee --}}
      <td class="text-end {{ $tx->capeFee >= 0 ? 'text-success' : 'text-danger' }}">
  @if($tx->capeFee != 0)
    {{ $tx->capeFee > 0 ? '+ ' : '− ' }}
    R {{ number_format(abs($tx->capeFee), 2) }}
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
@endsection

@section('page-script')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script>
$(function () {

  const table = $('#transactionsTable').DataTable({
  order: [[1, 'desc']],
  columnDefs: [
    { orderable: false, targets: 0 }
  ],
  autoWidth: false   // ← THIS IS THE MISSING PIECE
});


  function renderItems(items) {
    if (!items.length) return '';

    /* =========================
       REFUND
    ========================= */
    if (items[0].mode === 'refund') {
      const r = items[0];
      return `
        <table class="table table-sm mb-0 child-table">
          <tbody>
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
       PAYMENT (SUMMARY + ITEMS)
    ========================= */
    if (items[0].mode === 'payment_summary') {

      const s = items[0];
      const players = items.filter(i => i.mode === 'payment_item');

      let html = `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            <tr><th>PayFast Reference</th><td><code>${s.pf_payment_id}</code></td></tr>
            <tr><th>Entries</th><td>${s.entries}</td></tr>
            <tr><th>Gross Paid</th><td>R ${s.gross}</td></tr>
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
