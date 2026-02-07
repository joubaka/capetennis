{{-- resources/views/frontend/event/partials/_my_clothing_orders.blade.php --}}
@php
  // show only paid orders (remove ->filter(...) if you want all)
  $ordersToShow = $myClothingOrders->filter(fn($o) => (int)$o->pay_status === 1);

  // precompute a grand total if prices are available
  $grandTotal = 0;
  foreach ($ordersToShow as $order) {
    $orderTotal = 0;
    foreach ($order->items as $it) {
      $qty  = $it->quantity ?? 1;
      $unit = $it->price ?? optional($it->itemType)->price;
      if (is_numeric($unit)) $orderTotal += $qty * $unit;
    }
    $grandTotal += $orderTotal;
    $order->computed_total = $orderTotal; // attach for display
  }
@endphp

@if($ordersToShow->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex align-items-center gap-2">
        <h5 class="m-0">My Clothing Orders</h5>
        <span class="badge bg-label-primary">{{ $ordersToShow->count() }}</span>
      </div>
      @if($grandTotal > 0)
        <div class="text-muted small">
          <span class="me-2">Total paid:</span>
          <span class="fw-semibold">R {{ number_format($grandTotal, 2) }}</span>
        </div>
      @endif
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="w-25">Team / Region</th>
              <th>Items</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
          @foreach($ordersToShow as $order)
            @php
              $teamName   = optional($order->team)->name;
              $regionName = optional(optional($order->team)->region)->region_name;
              $lines = [];

              foreach ($order->items as $it) {
                $typeName = optional($it->itemType)->item_type_name ?? 'Item';
                $sizeName = optional($it->size)->name ?? optional($it->size)->label ?? null;
                $qty      = $it->quantity ?? 1;

                $lines[] = [
                  'name' => $typeName,
                  'size' => $sizeName,
                  'qty'  => $qty,
                ];
              }
            @endphp

            <tr>
              <td class="align-middle">
                <div class="fw-medium text-wrap">{{ $teamName }}</div>
                <small class="text-muted text-wrap d-block">{{ $regionName }}</small>
                <small class="text-muted">Ref: <span class="badge bg-label-secondary">{{ $order->player->full_name }}</span></small>
              </td>

              <td class="align-middle">
                <ul class="list-unstyled mb-0">
                  @foreach($lines as $line)
                    <li class="mb-1 text-wrap">
                      <span class="fw-medium">{{ $line['name'] }}</span>
                      @if($line['size'])
                        <span class="badge bg-label-secondary ms-1">{{ $line['size'] }}</span>
                      @endif
                      <span class="text-muted ms-1">×{{ $line['qty'] }}</span>
                    </li>
                  @endforeach
                </ul>
              </td>

              <td class="text-end align-middle">
                @if(($order->computed_total ?? 0) > 0)
                  <span class="fw-semibold">R{{ number_format($order->computed_total, 2) }}</span>
                @elseif(!empty($order->total_amount))
                  <span class="fw-semibold">R{{ number_format($order->total_amount, 2) }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>

              <td class="text-center align-middle">
                <span class="badge {{ (int)$order->pay_status === 1 ? 'bg-label-success' : 'bg-label-danger' }}">
                  {{ (int)$order->pay_status === 1 ? 'Paid' : 'X' }}
                </span>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endif
