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

<a href="{{ url()->previous() }}" class="btn btn-warning">Back</a>
<div class="table-responsive">
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
    <td colspan="4" class="text-end fw-bold">Total</td>
    <td class="text-end fw-bold">
      R{{ number_format($total, 2) }}
    </td>
  </tr>
</tfoot>

    </table>
    <br>
    <div class=" p-4">
        {!! $payfast->getForm() !!}
        <button type="submit" form="payfastForm" class="btn btn-danger btn-lg">Pay now with PayFast</button>
</div>






@endsection
