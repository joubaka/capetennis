<p>Hello {{ $invitation->player?->full_name ?? 'Player' }},</p>
<p>{{ $invitation->batch?->event?->name ?? 'The Cape Tennis Masters event' }} invitation update.</p>
<p>Age group: {{ $invitation->categoryEvent?->category?->name ?? 'Masters' }}</p>
<p>Ranking position: {{ $invitation->ranking_position }}</p>
@if($invitation->batch?->event)
  <p><a href="{{ route('events.show', $invitation->batch->event) }}">View the event on Cape Tennis</a></p>
@endif
@if($kind === 'invitation' || $kind === 'replacement')
  <p>Please log in to Cape Tennis to accept the invitation and complete PayFast payment, or decline if you are unavailable.</p>
@elseif($kind === 'confirmed')
  <p>Your PayFast payment has been verified and your place is confirmed.</p>
@elseif($kind === 'declined')
  <p>Your unavailability has been recorded, but the next reserve player will only be invited after you confirm the cancellation.</p>
  <p><a href="{{ URL::temporarySignedRoute('masters.invitations.confirm-decline', now()->addDays(2), ['invitation' => $invitation]) }}">Confirm that you are unavailable</a></p>
  <p>This confirmation link expires in 48 hours.</p>
@elseif($kind === 'withdrawn')
  <p>Your withdrawal has been recorded. If automatic replacement is enabled, the next reserve player will be contacted.</p>
@endif
