@component('mail::message')
# Category change receipt

{{ $adjustment->event->name }}

{{ $adjustment->sourceCategory->category?->name }} → {{ $adjustment->targetCategory->category?->name }}

@if((float) $adjustment->difference > 0)
Additional payment received through PayFast: **R{{ number_format((float) $adjustment->difference, 2) }}**.
@elseif((float) $adjustment->wallet_credit > 0)
Credited to your wallet: **R{{ number_format((float) $adjustment->wallet_credit, 2) }}**.
@else
No additional payment or wallet credit was required.
@endif

Amount paid toward this entry: **R{{ number_format((float) ($adjustment->payment_after['total_paid'] ?? 0), 2) }}**.

Reference: Category change #{{ $adjustment->id }}.
@endcomponent
