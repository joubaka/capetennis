@if($clothingReceipts['count'] > 0)
      <div class="px-3 py-3 border-top">
        <h6 class="mb-1">Clothing payable by region</h6>
        <p class="text-muted small mb-0">Regional funds only; excluded from registration income and profit. Net payable deducts recorded fees and completed refunds. Pending refunds remain a liability, not a completed payment. Payable does not confirm a transfer to the convenor.</p>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0" aria-label="Clothing payable by region">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Region</th>
              <th class="text-center">Paid orders</th>
              <th class="text-end">Clothing received</th>
              <th class="text-end">Recorded PayFast fee</th>
              <th class="text-end">Completed refunds (net)</th>
                <th class="text-end">Pending refunds (net)</th>
                <th class="text-end pe-3">Net payable to convenor</th>
            </tr>
          </thead>
          <tbody>
            @foreach($clothingReceipts['groups'] as $region => $group)
              <tr>
                <td class="ps-3">{{ $region }}</td>
                <td class="text-center">{{ $group['rows']->where('type', 'clothing_payment')->count() }}</td>
                <td class="text-end text-nowrap">R {{ number_format($group['totals']['gross'], 2) }}</td>
                <td class="text-end text-nowrap">&minus;R {{ number_format(abs($group['totals']['fees']), 2) }}</td>
                <td class="text-end text-nowrap">&minus;R {{ number_format($group['totals']['completed_refund_net'], 2) }}</td>
                  <td class="text-end text-nowrap">R {{ number_format($group['totals']['pending_refund_net'], 2) }}</td>
                  <td class="text-end text-nowrap fw-semibold pe-3">R {{ number_format($group['totals']['net'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot class="table-light fw-semibold">
            <tr>
              <td class="ps-3">Total clothing</td>
              <td class="text-center">{{ $clothingReceipts['groups']->sum(fn ($group) => $group['rows']->where('type', 'clothing_payment')->count()) }}</td>
              <td class="text-end text-nowrap">R {{ number_format($clothingReceipts['totals']['gross'], 2) }}</td>
              <td class="text-end text-nowrap">&minus;R {{ number_format(abs($clothingReceipts['totals']['fees']), 2) }}</td>
              <td class="text-end text-nowrap">&minus;R {{ number_format($clothingReceipts['totals']['completed_refund_net'], 2) }}</td>
                <td class="text-end text-nowrap">R {{ number_format($clothingReceipts['totals']['pending_refund_net'], 2) }}</td>
                <td class="text-end text-nowrap pe-3">R {{ number_format($clothingReceipts['totals']['net'], 2) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      @if($clothingReceipts['groups']->has('Region not recorded'))
        <p class="small text-warning px-3 py-2 mb-0">Confirm the region for unassigned receipts before paying a convenor.</p>
      @endif
@else
  <div class="p-3 text-muted">No clothing receipts or refunds recorded for this event.</div>
@endif

