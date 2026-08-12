@component('mail::message')
# Registration confirmed

An administrator has registered you for the following event:

**Event:** {{ $entry->categoryEvent?->event?->name ?? 'Event' }}

**Category:** {{ $entry->categoryEvent?->category?->name ?? 'Category' }}

**Player:** {{ trim(($entry->players->first()?->name ?? '').' '.($entry->players->first()?->surname ?? '')) }}

No payment is required for this administrator-created entry.

Thanks,
{{ config('app.name') }}
@endcomponent
