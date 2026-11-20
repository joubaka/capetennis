<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Cape Tennis Plus morning updates</title></head>
<body style="font-family:Arial,sans-serif;color:#20384b;line-height:1.6">
<h1>Cape Tennis Plus morning updates</h1>
<p>Here is new information you have not marked as read on Cape Tennis.</p>
@foreach($items as $item)
  <section style="margin-bottom:24px">
    <h2>{{ $item['snapshot']['name'] }}</h2>
    <p><strong>{{ match ($item['kind']) { 'new_event' => 'New event added', 'announcement' => 'Announcement', default => 'Event information updated' } }}</strong></p>
    @if($item['kind'] === 'announcement')
      <p>{{ $item['snapshot']['title'] ?: 'Event update' }}</p>
      <p>{{ $item['snapshot']['excerpt'] }}</p>
    @else
      @if($item['snapshot']['start_date'])<p>Starts: {{ $item['snapshot']['start_date'] }}@if($item['snapshot']['end_date']) · Ends: {{ $item['snapshot']['end_date'] }}@endif</p>@endif
      @if($item['snapshot']['registration_closes_at'])<p>Registration closes: {{ $item['snapshot']['registration_closes_at'] }}</p>@endif
      @if($item['snapshot']['venue_notes'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($item['snapshot']['venue_notes']), 300) }}</p>@endif
      @if($item['snapshot']['information'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($item['snapshot']['information']), 500) }}</p>@endif
    @endif
    <p><a href="{{ route('events.show', $item['snapshot']['event_id']).($item['kind'] === 'announcement' ? '#event-announcement-'.substr($item['key'], 13) : '') }}">View event details and latest registration or nomination information</a></p>
  </section>
@endforeach
<p>Check the event page for current dates, entry requirements and any later changes. Essential event information remains free.</p>
<p><a href="{{ route('home') }}">Browse Cape Tennis events</a></p>
<p><a href="{{ route('player-plus.notifications') }}">Snooze or turn off your Plus morning emails</a></p>
</body></html>
