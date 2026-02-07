<!DOCTYPE html>
<html>
<head>
    <title>Transactions PDF</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
        th { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h2>Transactions - {{ $event->name }}</h2>

    @php
        $runningBalance = 0;
        $grossTotal = 0;
        $feeTotal = 0;
        $capeTotal = 0;
        $nettTotal = 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>User</th>
                <th>Type</th>
                <th>Gross</th>
                <th>Payfast Fee</th>
                <th>Cape Tennis Fee</th>
                <th>Nett</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
            @php
            $gross = $t->amount_gross ?? 0;
            $isWithdrawal = $t->transaction_type === 'Withdrawal';

            // Payfast Fee: always positive for Withdrawal, negative for normal
            $fee = $t->amount_fee ?? 0;
            $fee = $isWithdrawal ? abs($fee) : -abs($fee);

            // Cape Tennis Fee: positive for withdrawal, negative for others
            $itemCount = $t->order && $t->order->items ? $t->order->items->count() : 1;
$capeFeeValue = $event->cape_tennis_fee ?? 15;
$cape = $isWithdrawal ? $capeFeeValue * $itemCount : -$capeFeeValue * $itemCount;
            $cape = $isWithdrawal ? abs($cape) : -abs($cape);

            // Nett = Gross + Payfast Fee + Cape Fee
            $nett = $gross + $fee + $cape;
            $runningBalance += $nett;

            // Totals
            $grossTotal += $gross;
            $feeTotal += $fee;
            $capeTotal += $cape;
            $nettTotal += $nett;
        @endphp


                <tr>
                    <td>{{ $t->pf_payment_id ?? '-' }}</td>
                    <td>{{ $t->created_at->format('d M Y') }}</td>
                    <td style="text-align: left;">
                      <strong>{{ $t->user->name ?? '-' }}</strong><br>
                      <ul style="margin: 0; padding-left: 15px; font-size: 10px;">
                        @if ($isWithdrawal)
                            @php
                                $amount = $t->item_price ?? abs($gross);
                            @endphp
                            <li>
                                {{ optional($t->player)->name }} {{ optional($t->player)->surname }}
                                ({{ optional(optional($t->category_event)->category)->name }})
                                - R{{ number_format($amount, 2) }}
                            </li>
                        @elseif ($t->order && $t->order->items)
                            @foreach ($t->order->items as $item)
                                @php
                                    $amount = $item->item_price ?? $event->entryFee ?? 0;
                                @endphp
                                <li>
                                    {{ $item->player->name }} {{ $item->player->surname }}
                                    ({{ optional($item->category_event->category)->name }})
                                    - R{{ number_format($amount, 2) }}
                                </li>
                            @endforeach
                        @endif
                    </ul>

                  </td>

                    <td>{{ $t->transaction_type }}</td>
                    <td>R{{ number_format($gross, 2) }}</td>
                    <td>R{{ number_format($fee, 2) }}</td>
                    <td>R{{ number_format($cape, 2) }}</td>
                    <td>R{{ number_format($nett, 2) }}</td>
                    <td>R{{ number_format($runningBalance, 2) }}</td>

                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: right;">Totals:</th>
                <th>R{{ number_format($grossTotal, 2) }}</th>
                <th>R{{ number_format($feeTotal, 2) }}</th>
                <th>R{{ number_format($capeTotal, 2) }}</th>
                <th>R{{ number_format($nettTotal, 2) }}</th>
                <th>R{{ number_format($runningBalance, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
