<h1>{{ $event->name }}</h1>
<p>{{ $notification ? 'Camp invitation notification for' : 'You are invited to enter the camp:' }} {{ $name }} · {{ $division }}</p>
<p>{!! nl2br(e($message)) !!}</p>
<p>{{ $event->start_date?->format('j F Y') }} – {{ $event->end_date?->format('j F Y') }}<br>{{ $event->venue_notes }}</p>
<p><a href="{{ $url }}">{{ $notification ? 'View camp information' : 'View invitation and enter the camp' }}</a></p>
<p>{{ $notification ? 'This is a notification copy. Entry and invitation decisions use the primary invitation.' : 'Your place is confirmed through the camp’s normal registration and payment process.' }}</p>
