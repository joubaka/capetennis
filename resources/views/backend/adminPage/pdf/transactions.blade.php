<!DOCTYPE html>
<html>
<head>
    <title>Transactions PDF</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
        th { background-color: #f5f5f5; }
        .refund-row td { background-color: #fff4f4; }
        ul { margin: 0; padding-left: 15px; font-size: 10px; }
    </style>
</head>
<body>
    <h2>Transactions - {{ $event->name }}</h2>

    @foreach($receiptSections as $key => $section)
    <h3>{{ $key === 'clothing' ? 'Regional clothing funds — payable to convenors' : 'Registration reconciliation' }}</h3>
    <table>
      <thead>
        <tr>
          <th>Region</th>
          <th>Received</th>
          <th>Fees deducted (net)</th>
          <th>Completed refunds</th>
          <th>Pending refunds</th>
          <th>{{ $key === 'clothing' ? 'Net payable to convenor' : 'Registration net income' }}</th>
        </tr>
      </thead>
      <tbody>
      @forelse($section['groups'] as $region => $group)
        <tr>
          <td>{{ $region }}</td>
          <td>R{{ number_format($group['totals']['gross'], 2) }}</td>
          <td>R{{ number_format(abs($group['rows']->reject(fn ($row) => $row->type === 'withdrawal' || ($row->type === 'refund' && ($row->refund_status ?? '') !== 'completed'))->sum(fn ($row) => ($row->fee ?? 0) + ($row->capeFee ?? 0))), 2) }}</td>
          <td>R{{ number_format($group['totals']['completed_refunds'], 2) }}</td>
          <td>R{{ number_format($group['totals']['pending_refunds'], 2) }}</td>
          <td>R{{ number_format($group['totals']['net'], 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="6">No transactions.</td></tr>
      @endforelse
      </tbody>
    </table>
    @if($key === 'registrations')
      <p>Registration payouts: R{{ number_format($totalPayouts, 2) }}. Registration balance: R{{ number_format($section['totals']['net'] - $totalPayouts, 2) }}.</p>
    @endif
    @endforeach
    <p>Clothing funds are excluded from registration income and payouts. Pending refunds do not reduce funds until completed.</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Participant / Items</th>
                <th>Method</th>
                <th>Gross</th>
                <th>PayFast Fee</th>
                <th>Cape Tennis Fee</th>
                <th>Net</th>

            </tr>
        </thead>
        <tbody>
            @foreach($ledger as $t)
            @php

                $isRefund = $t->type === 'refund';
            @endphp
            <tr class="{{ $isRefund ? 'refund-row' : '' }}">
                <td>{{ $t->pf_payment_id ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($t->created_at)->format('d M Y') }}</td>
                <td>{{ $t->type === 'clothing_payment' ? 'Clothing received' : ($t->type === 'refund' ? (($t->subtype ?? '') === 'clothing_refund' ? 'Clothing refund' : 'Registration refund') : ($t->type === 'payment' ? 'Registration received' : ucfirst($t->type))) }}</td>
                <td style="text-align: left;">
                    <strong>{{ $t->player ?? '-' }}</strong>
                    @if(!$isRefund && isset($t->order) && $t->order && $t->order->items && $t->order->items->count())
                        <ul>
                            @foreach($t->order->items as $item)
                            <li>
                                @if($t->type === 'clothing_payment')
                                    {{ $item->item_name ?? optional($item->itemType)->item_type_name ?? 'Clothing' }}
                                    ({{ $item->size_name ?? optional($item->size)->size ?? '-' }}, qty {{ $item->qty ?: 1 }})
                                    &mdash; R{{ number_format($item->line_total ?? (($item->price ?? 0) * ($item->qty ?: 1)), 2) }}
                                @else
                                    {{ $item->player->name ?? '' }} {{ $item->player->surname ?? '' }}
                                    ({{ optional(optional($item->category_event)->category)->name ?? '-' }})
                                    &mdash; R{{ number_format($item->item_price ?? 0, 2) }}
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    @elseif($isRefund && isset($t->category))
                        <ul><li>{{ $t->category ?? '-' }}</li></ul>
                    @endif
                </td>
                <td>{{ $t->method ?? '-' }}</td>
                <td>{{ in_array($t->type, ['refund', 'payout']) ? '-' : '' }} R{{ number_format(abs($t->gross), 2) }}</td>
                <td>
                    @if($t->fee != 0)
                        {{ $t->fee > 0 ? '+' : '-' }} R{{ number_format(abs($t->fee), 2) }}
                    @else &mdash;
                    @endif
                </td>
                <td>
                    @if($t->capeFee != 0)
                        {{ $t->capeFee > 0 ? '+' : '-' }} R{{ number_format(abs($t->capeFee), 2) }}
                    @else &mdash;
                    @endif
                </td>
                <td>{{ $t->net < 0 ? '-' : '' }} R{{ number_format(abs($t->net), 2) }}</td>

            </tr>
            @endforeach
        </tbody>

    </table>
</body>
</html>
