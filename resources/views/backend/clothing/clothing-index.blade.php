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
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h5 class="mb-0 text-uppercase">Clothing Orders — {{ $region->region_name ?? '' }}</h5>

    <div class="btn-group mt-2 mt-md-0">
      <a href="{{ route('backend.region.clothing.edit', array_filter(['region' => $region, 'event_id' => request()->integer('event_id') ?: null])) }}" class="btn btn-sm btn-outline-secondary">Back to clothing</a>
      <a href="{{ route('export.pdf.clothing.order', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null])) }}" target="_blank" class="btn btn-sm btn-outline-danger">
        <i class="ti ti-file-text"></i> PDF
      </a>
      <a href="{{ route('export.excel.clothing', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null])) }}" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="ti ti-file-spreadsheet"></i> Excel
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Customer payments received</small><strong class="fs-5 text-success">R{{ number_format($clothingFinancials['received'], 2) }}</strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">PayFast fees</small><strong class="fs-5 text-warning">− R{{ number_format($clothingFinancials['payfast_fees'], 2) }}</strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Net clothing proceeds before supplier costs</small><strong class="fs-5">R{{ number_format($clothingFinancials['net'], 2) }}</strong></div></div>
    </div>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Player</th>
            <th>Item</th>
            <th>Size</th>
            <th>Team</th>
            <th>Payfast ID</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Line Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @php 
            $grandTotal = 0;
            $rowNum         = 1;
          @endphp

          @forelse($clothings as $order)
            @foreach($order->items as $item)
                @php
                  $price = (float) $item->price;
                  $qty = (int) ($item->qty ?: 1);
                  $lineTotal = (float) ($item->line_total ?: $price * $qty);
                @endphp
                <tr>
                  <td>{{ $rowNum++ }}</td>
                  <td>{{ $order->created_at->format('d-m-Y') }}</td>
                  <td>{{ optional($order->player)->name }}</td>
                  <td>{{ $item->item_name ?: optional($item->itemType)->item_type_name }}</td>
                  <td>{{ $item->size_name ?: optional($item->size)->size }}</td>
                  <td>{{ optional($order->team)->name }}</td>
                  <td>{{ $order->pf_id }}</td>
                  <td>{{ $qty }}</td>
                  <td>R{{ number_format($price, 2) }}</td>
                  <td>R{{ number_format($lineTotal, 2) }}</td>
                  <td><span class="badge bg-label-success">Paid</span></td>
                </tr>

                @php
                  $grandTotal += $lineTotal;
                @endphp
            @endforeach
          @empty
            <tr>
              <td colspan="11" class="text-center text-muted py-3">No clothing orders found for this region</td>
            </tr>
          @endforelse
        </tbody>

        <tfoot class="table-light">
          <tr>
            <td colspan="7" class="text-end fw-bold">Totals:</td>
            <td></td>
            <td></td>
            <td class="fw-bold">R{{ number_format($grandTotal, 2) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
@endsection
