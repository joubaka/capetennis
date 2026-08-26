<x-mail::message>
@include('emails._capez-header')
# Category Changed

Hi {{ $data['player_name'] }},

Your category for **{{ $data['event_name'] }}** has been changed.

- **Previous Category:** {{ $data['old_category'] }}
- **New Category:** {{ $data['new_category'] }}

This change was made by {{ $data['changed_by'] }}.

If you did not request this change, please contact support at support@capetennis.co.za.

Thanks,<br>
Cape Tennis
</x-mail::message>
