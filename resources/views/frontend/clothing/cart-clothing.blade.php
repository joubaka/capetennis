@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')

@endsection

@section('page-script')

@endsection

@section('content')

<div class="container-xxl py-3 py-md-4">
<a href="{{ url()->previous() }}" class="btn btn-warning mb-3">Back</a>
<div class="table-responsive d-none d-md-block">
    <table class="table">
        <thead>
  <tr>
    <th>Player</th>
    <th>Item</th>
    <th class="text-center">Qty</th>
    <th class="text-end">Unit Price</th>
    <th class="text-end">Line Total</th>
  </tr>
</thead>

       <tbody class="table-border-bottom-0">
@foreach($items as $item)
  <tr>
    <td>
      <span class="badge bg-label-primary">
        {{ $item->order->player->getFullNameAttribute() }}
      </span>
    </td>

    <td>
      {{ $item->item_name ?: optional($item->itemType)->item_type_name }}
      <span class="badge bg-label-warning ms-1">
        Size {{ $item->size_name ?: optional($item->size)->size }}
      </span>
    </td>

    <td class="text-center">
      {{ $item->qty }}
    </td>

    <td class="text-end">
      R{{ number_format($item->price, 2) }}
    </td>

    <td class="text-end fw-bold">
      R{{ number_format($item->line_total, 2) }}
    </td>
  </tr>
@endforeach
</tbody>

<tfoot>
  <tr class="border-top">
    <td colspan="4" class="text-end fw-bold">Total payable</td>
    <td class="text-end fw-bold">
      R{{ number_format($total, 2) }}
    </td>
  </tr>
</tfoot>

    </table>
</div>

<div class="d-md-none">
  <div class="d-grid gap-3">
    @foreach($items as $item)
      <article class="card border shadow-none">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
            <div>
              <div class="small text-muted">Player</div>
              <div class="fw-semibold">{{ $item->order->player->getFullNameAttribute() }}</div>
            </div>
            <span class="badge bg-label-warning">Size {{ $item->size_name ?: optional($item->size)->size }}</span>
          </div>
          <div class="fw-medium mb-3">{{ $item->item_name ?: optional($item->itemType)->item_type_name }}</div>
          <dl class="row g-2 mb-0 small">
            <dt class="col-5 text-muted fw-normal">Quantity</dt>
            <dd class="col-7 text-end mb-0">{{ $item->qty }}</dd>
            <dt class="col-5 text-muted fw-normal">Unit price</dt>
            <dd class="col-7 text-end mb-0">R{{ number_format($item->price, 2) }}</dd>
            <dt class="col-5 fw-semibold">Line total</dt>
            <dd class="col-7 text-end fw-bold mb-0">R{{ number_format($item->line_total, 2) }}</dd>
          </dl>
        </div>
      </article>
    @endforeach
  </div>
  <div class="d-flex justify-content-between align-items-center border-top border-bottom py-3 mt-3 fs-5">
    <strong>Total payable</strong>
    <strong>R{{ number_format($total, 2) }}</strong>
  </div>
</div>

    <div class="d-grid d-sm-block px-0 py-4">
        {!! $payfast->getForm() !!}
        <button type="submit" form="payfastForm" class="btn btn-danger btn-lg">Pay now with PayFast</button>
</div>
</div>
@endsection
