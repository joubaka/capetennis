<p>Hello {{ $invitation->recipient_name }},</p>
<p>You have been invited to participate in {{ $invitation->event->name }} for {{ $invitation->categoryEvent->category->name }}.</p>
<p><a href="{{ $invitationUrl }}">View your invitation</a></p>
<p>Registration will open later. This invitation does not create a registration or payment.</p>
