<p>Hello {{ $playerName }},</p>
<p>{{ $messageBody }}</p>
<p>{{ $eventName }}</p>
@if($eventUrl)
    <p><a href="{{ $eventUrl }}">View event</a></p>
@endif
