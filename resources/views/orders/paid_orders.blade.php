@extends('layouts.layoutMaster')
@section('title', 'Paid Hoodie Orders')

@section('content')
<div class="container mt-4">
    <h2>Paid Hoodie Orders</h2>

    @if($orders->isEmpty())
        <p>No paid orders found.</p>
    @else
      <table class="table table-bordered table-striped mt-3">
    <thead class="table-dark">
        <tr>
            <th>Customer</th>
            <th>Email</th>
            <th>Items Ordered</th>
            <th>Total</th>
            <th>Order Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($orders as $order)
        <tr>
            <td>{{ $order->user_text }}</td>
            <td>{{ $order->email_text }}</td>
            <td>
                <ul class="mb-0">
                    @foreach($order->items as $item)
                        <li>
                            {{ $item->itemType->item_type_name ?? '—' }} ({{ $item->size->size ?? '-' }}) - R{{ number_format($item->itemType->price ?? 0, 2) }}
                        </li>
                    @endforeach
                </ul>
            </td>
            <td>
                R{{ number_format($order->items->sum(fn($item) => $item->itemType->price ?? 0), 2) }}
            </td>
            <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="table-light">
        <tr>
            <th colspan="3" class="text-end">Grand Total:</th>
            <th>
                R{{ number_format($orders->sum(fn($order) => $order->items->sum(fn($item) => $item->itemType->price ?? 0)), 2) }}
            </th>
            <th></th>
        </tr>
    </tfoot>
</table>

    @endif
</div>
@endsection
