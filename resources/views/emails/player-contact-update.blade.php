<p>Hello {{ $playerName }},</p>
<p>{{ $messageBody }}</p>
@if(!empty($withdrawalSummary))
    {!! $withdrawalSummary !!}
@endif
<p>{{ $eventName }}</p>
@if($eventUrl)
    <p><a href="{{ $eventUrl }}">View event</a></p>
@endif
