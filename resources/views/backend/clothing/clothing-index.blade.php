@extends('layouts.backend')

@section('title', 'Clothing Orders — ' . ($region->region_name ?? 'Region'))

@section('content')
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h5 class="mb-0 text-uppercase">Clothing Orders — {{ $region->region_name ?? '' }}</h5>

    <div class="btn-group mt-2 mt-md-0">
      <a href="{{ route('export.pdf.clothing.order', $region->id) }}" target="_blank" class="btn btn-sm btn-outline-danger">
        <i class="ti ti-file-text"></i> PDF
      </a>
      <a href="{{ route('export.excel.clothing', $region->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="ti ti-file-spreadsheet"></i> Excel
      </a>
    </div>
  </div>

  <div class="card-body">
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
            @if($order->pay_status == 1)
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
            @endif
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
