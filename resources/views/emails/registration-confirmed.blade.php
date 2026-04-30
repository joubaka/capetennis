<x-mail::message>
# Registration Confirmed

Hi {{ $data['player_name'] }},

Your registration for **{{ $data['event_name'] }}** has been confirmed.

- **Category:** {{ $data['category_name'] ?? 'N/A' }}
- **Payment Method:** {{ $data['payment_method'] ?? 'N/A' }}
@if(!empty($data['amount']))
- **Amount Paid:** R{{ number_format($data['amount'], 2) }}
@endif

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
