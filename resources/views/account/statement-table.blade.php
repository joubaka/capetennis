<table class="table statement-table">
    <thead><tr><th>Recorded date</th><th>Payment / reference</th><th>Entry details</th><th class="text-end">Paid (R)</th><th class="text-end">Refunded (R)</th></tr></thead>
    <tbody>
    @forelse($statementRows as $row)
        <tr>
            <td>{{ $row['date'] }}</td>
            <td>{{ $row['method'] }}<br>{{ $row['reference'] }}<br><small>{{ $row['checkout'] }}</small></td>
            <td>@foreach($row['details'] as $detail)
                <div class="entry-detail"><strong>{{ $detail['player'] }}</strong><br>{{ $detail['event'] }}
                    @if($detail['event_date'])<br><small>Event date: {{ \Illuminate\Support\Carbon::parse($detail['event_date'])->format('Y-m-d') }}</small>@endif
                    @if($detail['fee'] !== null)<br><small>Item fee: R {{ number_format($detail['fee'] / 100, 2) }}</small>@endif
                </div>
            @endforeach</td>
            <td class="text-end">{{ number_format($row['paid'] / 100, 2) }}</td>
            <td class="text-end">{{ number_format($row['refunded'] / 100, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="5">No recorded entry payments or completed refunds match this period and player filter.</td></tr>
    @endforelse
    </tbody>
</table>
