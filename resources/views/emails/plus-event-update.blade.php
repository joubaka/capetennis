<h1>{{ $event->name }}: event update</h1>
<p>Public information for an event your Plus player has entered has changed.</p>
@if(!empty($delivery->changes['draws']))<p>The published draw or match schedule has changed. Check the latest opponents, times and courts on the event page.</p>@endif
@if(!empty($delivery->changes['information']))
<p>Event information has been updated.</p>
<p>Dates: {{ $delivery->snapshot['information']['start_date'] ?? 'Check event page' }} – {{ $delivery->snapshot['information']['end_date'] ?? '' }}</p>
@if($delivery->snapshot['information']['registration_closes_at'] ?? null)<p>Registration closes: {{ $delivery->snapshot['information']['registration_closes_at'] }}</p>@endif
@if($delivery->snapshot['information']['venue_notes'] ?? null)<p>Venue: {{ $delivery->snapshot['information']['venue_notes'] }}</p>@endif
<p>{{ \Illuminate\Support\Str::limit(strip_tags($delivery->snapshot['information']['information'] ?? ''), 500) }}</p>
@endif
@if(!empty($delivery->changes['announcements']))
<h2>New or updated event announcements</h2>
@foreach($delivery->changes['announcements'] as $id)
@if(isset($delivery->snapshot['announcements'][$id]))
@php($post = $delivery->snapshot['announcements'][$id])<h3>{{ $post['title'] }}</h3><p>{{ $post['excerpt'] }}</p>
@endif
@endforeach
@endif
@if(!empty($delivery->changes['announcement_removed']))<p>An event announcement was removed. Check the event page for the current information.</p>@endif
<p><a href="{{ route('events.show', $event->id) }}">View the latest event details, draws and announcements</a></p>
<p><a href="{{ route('player-plus.notifications') }}">Snooze or turn off Plus update emails</a></p>
