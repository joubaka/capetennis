<!doctype html><html lang="en"><body>
<h1>{{ $notification->revision > 1 ? 'Corrected match result' : 'A new match result has been entered' }}</h1>
<p>Event: {{ $notification->snapshot['event'] }}<br>Draw: {{ $notification->snapshot['draw'] }}</p>
<p>{{ implode(' / ', $notification->snapshot['home_names']) }} versus {{ implode(' / ', $notification->snapshot['away_names']) }}</p>
<p>Result: {{ collect($notification->snapshot['sets'])->map(fn ($set) => implode('–', $set))->implode(', ') }}</p>
<p><a href="{{ route('frontend.fixtures.index', $notification->snapshot['draw_id']) }}">View the match result</a></p>
<p>Please check that this result is correct. If anything is wrong, contact the convener at the court.</p>
@if($replyTo)
<p>You can also reply to this email to contact the event administrators. The reply addresses are {{ implode(', ', $replyTo) }}; please check that your email app includes all of them before sending.</p>
@endif
</body></html>
