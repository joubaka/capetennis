@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Transactions')

@section('page-style')
<style>
  td.dt-toggle { cursor: pointer; text-align: center; user-select:none; }
  tr.shown td.dt-toggle i { transform: rotate(90deg); }
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

  #transactionsTable td.text-end {
    font-variant-numeric: tabular-nums;
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Tournament Transactions</h4>
      <a href="{{ route('transactions.pdf', $event) }}" class="btn btn-outline-primary">
        Export Transactions
      </a>
      <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>Back to Event
      </a>
    </div>
  </div>

  {{-- SUMMARY --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-start border-primary">
        <div class="card-body">
          <small class="text-muted">Total Gross Income</small>
          <h4>R {{ number_format($totals['gross'], 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-start border-warning">
        <div class="card-body">
          <small class="text-muted">PayFast Fees</small>
          <h4 class="text-warning">− R {{ number_format($totals['payfast_fees'], 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-start border-danger">
        <div class="card-body">
          <small class="text-muted">
            Cape Tennis Fees ({{ $totalEntries }} × R{{ $feePerEntry }})
          </small>
          <h4 class="text-danger">− R {{ number_format($totals['site_fees'], 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card border-start border-success">
        <div class="card-body">
          <small class="text-muted">Net Tournament Income</small>
          <h4 class="text-success">R {{ number_format($totals['net'], 2) }}</h4>
        </div>
      </div>
    </div>
  </div>

  {{-- TABLE --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">PayFast Transactions</h5>
    </div>

    <div class="card-body p-0">
      <table id="transactionsTable" class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;"></th>
            <th>Date</th>
            <th>Reference</th>
            <th>Payer</th>
            <th class="text-end">Gross</th>
            <th class="text-end">PayFast Fee</th>
            <th class="text-end">Cape Tennis Fee</th>
            <th class="text-end">Net to Event</th>
          </tr>
        </thead>

        <tbody>
        @foreach($transactions as $tx)
          @php
            $items = optional($tx->order)->items ?? collect();

            $payload = $items->map(fn($item) => [
              'player'   => trim(($item->player->name ?? '').' '.($item->player->surname ?? '')),
              'category' => optional($item->category_event->category)->name,
              'price'    => number_format($item->item_price ?? 0, 2),
            ]);

            $payfastFee = abs($tx->amount_fee ?? 0);

            $siteFee = $isTeamEvent
              ? $feePerEntry
              : $items->count() * $feePerEntry;

            $net = $tx->amount_gross - $payfastFee - $siteFee;
          @endphp

          <tr data-items='@json($payload)'>
            <td class="dt-toggle">
              @if($items->count())
                <i class="ti ti-chevron-right"></i>
              @endif
            </td>
            <td>{{ $tx->created_at->format('Y-m-d') }}</td>
            <td>{{ $tx->pf_payment_id }}</td>
            <td>{{ optional($tx->user)->name ?? '—' }}</td>
            <td class="text-end">R {{ number_format($tx->amount_gross, 2) }}</td>
            <td class="text-end text-warning">− R {{ number_format($payfastFee, 2) }}</td>
            <td class="text-end text-danger">− R {{ number_format($siteFee, 2) }}</td>
            <td class="text-end text-success">R {{ number_format($net, 2) }}</td>
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
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script>
$(function () {

  const table = $('#transactionsTable').DataTable({
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: 0 }]
  });

  function renderItems(items) {
    if (!items.length) return '';

    let html = `
      <table class="table table-sm mb-0 child-table">
        <thead>
          <tr>
            ${@json($isTeamEvent) ? '<th>Category</th>' : '<th>Player</th><th>Category</th>'}
            <th class="text-end">Item Price</th>
          </tr>
        </thead><tbody>`;

    items.forEach(i => {
      html += `
        <tr>
          ${@json($isTeamEvent)
            ? `<td>${i.category || '—'}</td>`
            : `<td>${i.player || '—'}</td><td>${i.category || '—'}</td>`}
          <td class="text-end">R ${i.price}</td>
        </tr>`;
    });

    return html + '</tbody></table>';
  }

  $('#transactionsTable tbody').on('click', 'td.dt-toggle', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    const items = tr.data('items') || [];

    if (!items.length) return;

    row.child.isShown()
      ? row.child.hide()
      : row.child(renderItems(items)).show();

    tr.toggleClass('shown');
  });

  table.on('page.dt search.dt order.dt', function () {
    table.rows('.shown').every(function () {
      this.child.hide();
      $(this.node()).removeClass('shown');
    });
  });

});
</script>
@endsection
