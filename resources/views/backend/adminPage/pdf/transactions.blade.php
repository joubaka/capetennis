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

    @php $runningBalance = 0; @endphp

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
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ledger as $t)
            @php
                $runningBalance = round($runningBalance + $t->net, 2);
                $isRefund = $t->type === 'refund';
            @endphp
            <tr class="{{ $isRefund ? 'refund-row' : '' }}">
                <td>{{ $t->pf_payment_id ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($t->created_at)->format('d M Y') }}</td>
                <td>{{ ucfirst($t->type) }}</td>
                <td style="text-align: left;">
                    <strong>{{ $t->player ?? '-' }}</strong>
                    @if(!$isRefund && isset($t->order) && $t->order && $t->order->items && $t->order->items->count())
                        <ul>
                            @foreach($t->order->items as $item)
                            <li>
                                {{ $item->player->name ?? '' }} {{ $item->player->surname ?? '' }}
                                ({{ optional(optional($item->category_event)->category)->name ?? '-' }})
                                — R{{ number_format($item->item_price ?? 0, 2) }}
                            </li>
                            @endforeach
                        </ul>
                    @elseif($isRefund && isset($t->category))
                        <ul><li>{{ $t->category ?? '-' }}</li></ul>
                    @endif
                </td>
                <td>{{ $t->method ?? '-' }}</td>
                <td>{{ $isRefund ? '−' : '' }} R{{ number_format(abs($t->gross), 2) }}</td>
                <td>
                    @if($t->fee != 0)
                        {{ $t->fee > 0 ? '+' : '−' }} R{{ number_format(abs($t->fee), 2) }}
                    @else —
                    @endif
                </td>
                <td>
                    @if($t->capeFee != 0)
                        {{ $t->capeFee > 0 ? '+' : '−' }} R{{ number_format(abs($t->capeFee), 2) }}
                    @else —
                    @endif
                </td>
                <td>{{ $t->net < 0 ? '−' : '' }} R{{ number_format(abs($t->net), 2) }}</td>
                <td>R{{ number_format($runningBalance, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right;">Totals:</th>
                <th>R{{ number_format($totalGross, 2) }}</th>
                <th>R{{ number_format($totalPayfastFees, 2) }}</th>
                <th>R{{ number_format($totalCapeTennisFees, 2) }}</th>
                <th>R{{ number_format($netTournamentIncome, 2) }}</th>
                <th>R{{ number_format($runningBalance, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
