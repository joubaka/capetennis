<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothing Orders</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>My Clothing Orders</h2>
    <table>
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Player</th>
                <th>Item</th>
                <th>Size</th>
                <th>Team</th>
                <th>Payfast Id</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
          @foreach($clothings as $order)
          @if($order->pay_status == 1)
              @foreach($order->items as $item)
                  <tr>
                      <td>{{ $order->id }}</td>
                      <td>{{ optional($item->created_at)->format('d M Y') }}</td>
                      <td>
                          <span class="badge bg-label-primary me-1">
                              {{ optional($order->player)->getFullNameAttribute() }}
                          </span>
                      </td>
                      <td>{{ optional($item->itemType)->item_type_name }}</td>
                      <td>{{ optional($item->size)->size }}</td>
                      <td>{{ optional($order->team)->name }}</td>
                      <td>{{ $order->pf_id }}</td>
                      <td><span class="badge bg-label-success">Paid</span></td>
                  </tr>
              @endforeach
          @endif
      @endforeach
        </tbody>
    </table>
</body>
</html>
