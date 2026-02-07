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
      {{ $item->itemType->item_type_name }}
      <span class="badge bg-label-warning ms-1">
        Size {{ $item->size->size }}
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
      R{{ number_format($payfast->amount, 2) }}
    </td>
  </tr>
</tfoot>

    </table>
    <br>
    <div class=" p-4">
        <form action="{{$payfast->url}}" method="post">
            <input type="hidden" name="merchant_id" value="{{$payfast->id}}">
            <input type="hidden" name="merchant_key" value="{{$payfast->key}}">

            <input type="hidden" name="return_url" value="{{$payfast->return_url}}">
            <input type="hidden" name="cancel_url" value="{{$payfast->cancel_url}}">
            <input type="hidden" name="notify_url" value="{{$payfast->notify_url}}">



            <input type="hidden" id="amount" name="amount" value="{{$payfast->amount}}">
            <input type="hidden" id="item_name" name="item_name" value="{{$payfast->item_name}}">

            <!--  team  -->
            <input type="hidden" name="custom_int1" value="{{$payfast->custom_int1}}">
            <input type="hidden" name="custom_str1" value="Team">

            <!-- Player -->
            <input type="hidden" name="custom_int2" value="{{$payfast->custom_int2}}">
            <input type="hidden" name="custom_str2" value="Player">

            <!-- Event -->
            <input type="hidden" name="custom_int3" value="{{$payfast->custom_int3}}">
            <input type="hidden" name="custom_str3" value="Event">

            <!--  User -->
            <input type="hidden" name="custom_int4" value="{{$payfast->custom_int4}}">
            <input type="hidden" name="custom_str4" value="User">

            <!--  order -->
            <input type="hidden" name="custom_int5" value="{{$payfast->custom_int5}}">
            <input type="hidden" name="custom_str5" value="ClothingOrder">

            <button class="btn btn-danger btn-lg">Pay now with Payfast</button>

        </form>
</div>






@endsection
