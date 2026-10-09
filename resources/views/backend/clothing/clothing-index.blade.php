@extends('layouts.backend')
@section('title', 'Clothing Orders — ' . ($region->region_name ?? 'Region'))
@section('content')
@php
  $clothingFinancials = $clothingFinancials ?? [
    'received' => round($clothings->sum(fn ($order) => (float) ($order->amount_paid ?? $order->total)), 2),
    'payfast_fees' => round($clothings->sum(fn ($order) => (float) $order->payfast_fee), 2),
    'net' => round($clothings->sum(fn ($order) => (float) ($order->amount_paid ?? $order->total) - (float) $order->payfast_fee), 2),
  ];
@endphp
<div class="card mb-4 clothing-orders-admin">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <div>@if($clothingEvent ?? null)<p class="text-muted mb-1">{{ $clothingEvent->name }}</p>@else<p class="text-muted mb-1">All events in this region</p>@endif<h5 class="mb-0 text-uppercase">Clothing Orders — {{ $region->region_name ?? '' }}</h5></div>
    <div class="btn-group mt-2 mt-md-0">
      <a href="{{ route('backend.region.clothing.edit', array_filter(['region' => $region, 'event_id' => request()->integer('event_id') ?: null])) }}" class="btn btn-sm btn-outline-secondary">Back to clothing</a>
      <a href="{{ route('export.pdf.clothing.order', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null])) }}" target="_blank" class="btn btn-sm btn-outline-danger">
        <i class="ti ti-file-text"></i> Print / PDF
      </a>
      <a href="{{ route('export.excel.clothing', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null])) }}" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="ti ti-file-spreadsheet"></i> Excel
      </a>
    </div>
  </div>
  <div class="card-body">
    <p class="text-muted">{{ $clothings->count() }} paid orders · {{ $clothings->sum(fn ($order) => $order->items->count()) }} item lines. Downloads include all paid orders in the current region and event scope.</p>
    <div class="row g-2 mb-3">
      <div class="col-md-8"><label class="form-label" for="clothing-order-search">Find a player, team, item or size</label><input type="search" class="form-control" id="clothing-order-search" placeholder="Search these orders"></div>
      <div class="col-md-4"><label class="form-label" for="clothing-team-filter">Team</label><select class="form-select" id="clothing-team-filter"><option value="">All teams in {{ $region->region_name }}</option>@foreach($clothings->pluck('team')->filter()->unique('id')->sortBy('name') as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select></div>
    </div>
    <p class="small text-muted" id="clothing-order-count" role="status" aria-live="polite"></p>
    <div class="alert alert-light border" id="clothing-order-empty" hidden>No item lines match these filters.</div>
    <div class="table-responsive">
      <table class="table table-striped align-middle clothing-fulfillment-table">
        <thead class="table-light">
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Item</th>
            <th>Size</th>
            <th>Qty</th>
            <th>Status</th>
            <th>Order details</th>
          </tr>
        </thead>
        <tbody>
          @php 
            $grandTotal = 0;
          @endphp
          @forelse($clothings as $order)
            @foreach($order->items as $item)
                @php
                  $price = (float) $item->price;
                  $qty = (int) ($item->qty ?: 1);
                  $lineTotal = (float) ($item->line_total ?: $price * $qty);
                @endphp
                <tr data-clothing-order-line data-team-id="{{ $order->team_id }}">
                  <td data-label="Player">{{ optional($order->player)->name }}</td>
                  <td data-label="Team">{{ optional($order->team)->name }}</td>
                  <td data-label="Item">{{ $item->item_name ?: optional($item->itemType)->item_type_name }}</td>
                  <td data-label="Size">{{ $item->size_name ?: optional($item->size)->size }}</td>
                  <td data-label="Qty">{{ $qty }}</td>
                  <td data-label="Status"><span class="badge bg-label-success">Paid</span></td>
                  <td data-label="Order details"><details><summary>Details</summary><dl class="mt-2 mb-0 text-break">
                    <dt>Order</dt><dd>#{{ $order->id }}</dd>
                    <dt>Date</dt><dd>{{ $order->created_at->format('d-m-Y') }}</dd>
                    <dt>Payfast ID</dt><dd>{{ $order->pf_id }}</dd>
                    <dt>Unit Price</dt><dd>R{{ number_format($price, 2) }}</dd>
                    <dt>Line Total</dt><dd>R{{ number_format($lineTotal, 2) }}</dd>
                  </dl></details></td>
                </tr>
                @php
                  $grandTotal += $lineTotal;
                @endphp
            @endforeach
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-3">No clothing orders found for this region</td>
            </tr>
          @endforelse
        </tbody>

      </table>
    </div>
    <details class="border rounded p-3 mt-3"><summary>Financial summary</summary><div class="row g-3 mt-1">
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Customer payments received</small><strong class="fs-5 text-success">R{{ number_format($clothingFinancials['received'], 2) }}</strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">PayFast fees</small><strong class="fs-5 text-warning">− R{{ number_format($clothingFinancials['payfast_fees'], 2) }}</strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Net clothing proceeds before supplier costs</small><strong class="fs-5">R{{ number_format($clothingFinancials['net'], 2) }}</strong></div></div>
    </div>
    <p class="mt-3 mb-0"><strong>Totals: R{{ number_format($grandTotal, 2) }}</strong></p>
    </details>
  </div>
</div>
<style>
.clothing-orders-admin summary { min-height:44px !important; cursor:pointer; align-content:center; }
.clothing-orders-admin .btn { min-height:44px !important; }
.clothing-orders-admin :is(input,select) { min-height:44px !important; }
.clothing-orders-admin [hidden] { display:none !important; }
.clothing-orders-admin .btn-group { display:flex; flex-wrap:wrap; gap:.4rem; }
.clothing-fulfillment-table td { white-space:normal; overflow-wrap:anywhere; }
@media(max-width:767px) {
 .clothing-fulfillment-table thead { display:none; }
 .clothing-fulfillment-table tbody, .clothing-fulfillment-table tr { display:block; }
 .clothing-fulfillment-table tr { border-bottom:1px solid #d9e1eb; padding:.75rem; }
 .clothing-fulfillment-table td { display:grid; grid-template-columns:80px minmax(0,1fr); gap:.5rem; border:0; padding:.35rem 0; }
 .clothing-fulfillment-table td::before { content:attr(data-label); font-weight:600; }
 .clothing-fulfillment-table td[colspan] { display:block; }
 .clothing-fulfillment-table td[colspan]::before { display:none; }
}
</style>
<script>
(() => {
  const search = document.getElementById('clothing-order-search');
  const team = document.getElementById('clothing-team-filter');
  const lines = [...document.querySelectorAll('[data-clothing-order-line]')];
  const filter = () => {
    const term = search.value.trim().toLocaleLowerCase();
    let shown = 0;
    lines.forEach(line => {
      const matches = (!team.value || line.dataset.teamId === team.value) && line.textContent.toLocaleLowerCase().includes(term);
      line.hidden = !matches;
      if (matches) shown++;
    });
    document.getElementById('clothing-order-count').textContent = `${shown} of ${lines.length} item lines shown`;
    document.getElementById('clothing-order-empty').hidden = shown > 0 || lines.length === 0;
  };
  search.addEventListener('input', filter);
  team.addEventListener('change', filter);
  filter();
})();
</script>
@endsection
