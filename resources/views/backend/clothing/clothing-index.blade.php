@extends('layouts/layoutMaster')

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
            <th>Unit (Net)</th>
            <th>Payfast Fee</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @php 
            $unitPriceTotal = 0; 
            $payfastTotal   = 0; 
            $netTotal       = 0; 
            $rowNum         = 1;
          @endphp

          @forelse($clothings as $order)
            @if($order->pay_status == 1)
              @foreach($order->items as $item)
                @php
                  $price   = optional($item->itemType)->price ?? 0;
                  $unit    = $price * 0.95; // after Payfast deduction
                  $payfast = $price * 0.05; // Payfast fee
                @endphp
                <tr>
                  <td>{{ $rowNum++ }}</td>
                  <td>{{ $order->created_at->format('d-m-Y') }}</td>
                  <td>{{ optional($order->player)->name }}</td>
                  <td>{{ optional($item->itemType)->item_type_name }}</td>
                  <td>{{ optional($item->size)->size }}</td>
                  <td>{{ optional($order->team)->name }}</td>
                  <td>{{ $order->pf_id }}</td>
                  <td>{{ $item->quantity }}</td>
                  <td>R{{ number_format($unit, 2) }}</td>
                  <td>R{{ number_format($payfast, 2) }}</td>
                  <td><span class="badge bg-label-success">Paid</span></td>
                </tr>

                @php
                  $unitPriceTotal += $price;
                  $payfastTotal   += $payfast;
                  $netTotal       += $unit;
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
            <td class="fw-bold text-success">R{{ number_format($netTotal, 2) }}</td>
            <td class="fw-bold text-danger">R{{ number_format($payfastTotal, 2) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
@endsection
