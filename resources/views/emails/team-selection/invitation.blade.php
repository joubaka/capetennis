@php
  $event = $invitation->selectionImport?->event;
  $campaign = $campaign ?? [];
  $responseUrl = route('team-selection.invitations.show', $invitation);
  $logo = $event?->logo ? asset('storage/'.$event->logo) : asset('assets/img/logos/cape-tennis-logo-transparent.png');
  $message = $campaign['message'] ?? $invitation->selectionImport?->email_message;
  $eventInformation = $campaign['event_information'] ?? $invitation->selectionImport?->event_information;
  $responseDeadline = !empty($campaign['response_deadline']) ? \Illuminate\Support\Carbon::parse($campaign['response_deadline']) : $invitation->selectionImport?->response_deadline;
  $paymentDeadline = !empty($campaign['payment_deadline']) ? \Illuminate\Support\Carbon::parse($campaign['payment_deadline']) : $invitation->selectionImport?->payment_deadline;
  $includeClothing = (bool) ($campaign['include_clothing'] ?? $invitation->selectionImport?->include_clothing);
  $eventDetails = $campaign['event'] ?? [];
  $clothingItems = collect($campaign['clothing_items'] ?? []);
  $publicEventUrl = $eventDetails['public_url'] ?? ($event?->published ? route('events.show', $event) : null);
  $venueNames = collect($eventDetails['venues'] ?? [])->filter();
@endphp
<div style="margin:0;background:#f4f3f8;padding:28px 12px;font-family:Arial,Helvetica,sans-serif;color:#4f4b5f;line-height:1.55">
  <div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e6e3ed;border-radius:12px;overflow:hidden">
    <div style="background:#15324b;padding:24px;text-align:center"><img src="{{ $logo }}" alt="Cape Tennis" width="150" style="background:#fff;border-radius:8px;padding:7px"><div style="color:#fff;text-transform:uppercase;letter-spacing:1.5px;margin-top:12px">{{ ($kind ?? 'invitation') === 'replacement' ? 'Replacement team invitation' : 'Platteland team invitation' }}</div></div>
    <div style="padding:30px 32px">
      <h2 style="color:#263b50">Hello {{ $invitation->player?->full_name ?? 'Player' }},</h2>
      @if($message)<p>{!! nl2br(e($message)) !!}</p>@endif
      <p>You have been selected to represent <strong>{{ $invitation->region?->region_name }}</strong> at <strong>{{ $event?->name }}</strong>.</p>
      <div style="background:#f2f7fa;border-left:4px solid #16876f;padding:16px 18px;margin:22px 0"><strong>{{ $invitation->team?->name }}</strong><br>Ranking position: {{ $invitation->ranking_position }}<br>Playing order: {{ $invitation->roster_rank }}</div>
      <table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 20px">
        @if($event?->start_date)<tr><td style="padding:7px 0;color:#777180">Event dates</td><td style="padding:7px 0;text-align:right;font-weight:bold">{{ $event->start_date->format('d M Y') }}{{ $event->end_date && !$event->end_date->equalTo($event->start_date) ? ' – '.$event->end_date->format('d M Y') : '' }}</td></tr>@endif
        <tr><td style="padding:7px 0;color:#777180">Entry fee</td><td style="padding:7px 0;text-align:right;font-weight:bold">R{{ number_format((float) $event?->entryFee, 2) }}</td></tr>
        @if($responseDeadline)<tr><td style="padding:7px 0;color:#777180">Respond by</td><td style="padding:7px 0;text-align:right;font-weight:bold">{{ $responseDeadline->format('d M Y H:i') }}</td></tr>@endif
        @if($paymentDeadline)<tr><td style="padding:7px 0;color:#777180">Payment deadline</td><td style="padding:7px 0;text-align:right;font-weight:bold">{{ $paymentDeadline->format('d M Y H:i') }}</td></tr>@endif
        @if(filled($eventDetails['organizer'] ?? $event?->organizer))<tr><td style="padding:7px 0;color:#777180">Organiser</td><td style="padding:7px 0;text-align:right;font-weight:bold">{{ $eventDetails['organizer'] ?? $event?->organizer }}</td></tr>@endif
        @if(filled($eventDetails['contact_email'] ?? $event?->email))<tr><td style="padding:7px 0;color:#777180">Event contact</td><td style="padding:7px 0;text-align:right;font-weight:bold">{{ $eventDetails['contact_email'] ?? $event?->email }}</td></tr>@endif
      </table>
      @if($venueNames->isNotEmpty() || filled($eventDetails['venue_notes'] ?? $event?->venue_notes))<div style="background:#f2f7fa;border-radius:8px;padding:15px 17px;margin:0 0 18px"><strong>Venue{{ $venueNames->count() === 1 ? '' : 's' }}</strong>@if($venueNames->isNotEmpty())<br>{{ $venueNames->implode(', ') }}@endif @if(filled($eventDetails['venue_notes'] ?? $event?->venue_notes))<br>{!! nl2br(e($eventDetails['venue_notes'] ?? $event?->venue_notes)) !!}@endif</div>@endif
      @if($eventInformation)<div style="background:#fff8e8;border-radius:8px;padding:15px 17px;margin:0 0 22px"><strong>Event information</strong><br>{!! nl2br(e($eventInformation)) !!}</div>@endif
      @if($publicEventUrl)<p style="text-align:center"><a href="{{ $publicEventUrl }}" style="color:#126b99;font-weight:bold">View the published event page and latest event information</a></p>@endif
      <div style="border:1px solid #d9e5eb;border-radius:8px;padding:16px 18px;margin:22px 0"><strong>How to accept your invitation</strong><ol style="margin:10px 0 0;padding-left:20px"><li>Sign in with the Cape Tennis account linked to this player.</li><li>Open the secure invitation using the green button below.</li><li>Choose <strong>Accept and pay</strong> and complete the PayFast payment before the payment deadline.</li><li>Your team place is secured only after Cape Tennis verifies the payment.</li></ol><p style="margin:10px 0 0">If you are unavailable, decline promptly so the next reserve can be invited.</p></div>
      <div style="text-align:center;margin:26px 0 10px"><a href="{{ $responseUrl }}?action=pay" style="display:inline-block;background:#16876f;color:#fff;text-decoration:none;font-weight:bold;border-radius:6px;padding:12px 20px;margin:0 4px 8px">Accept and pay</a><a href="{{ $responseUrl }}?action=decline" style="display:inline-block;background:#fff;color:#b54747;text-decoration:none;font-weight:bold;border:1px solid #d9a6a6;border-radius:6px;padding:11px 20px;margin:0 4px 8px">Decline invitation</a></div>
      <p style="font-size:13px;color:#777180;text-align:center">Both buttons open your secure invitation page, where you confirm your choice.</p>
      @if($includeClothing)<div style="background:#eef7ff;border-radius:8px;padding:16px 18px;margin-top:20px"><strong>Optional regional clothing</strong>@if($clothingItems->isNotEmpty())<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:9px">@foreach($clothingItems as $item)<tr><td style="padding:6px 8px 6px 0;border-top:1px solid #d8e8f2"><strong>{{ $item['name'] }}</strong>@if(!empty($item['sizes']))<br><span style="font-size:12px;color:#65717a">Sizes: {{ implode(', ', $item['sizes']) }}</span>@endif</td><td style="padding:6px 0;border-top:1px solid #d8e8f2;text-align:right;white-space:nowrap"><strong>R{{ number_format((float) $item['price'], 2) }}</strong></td></tr>@endforeach</table>@endif<p style="margin:12px 0 0"><strong>How to order:</strong> first accept this invitation and complete the event payment. Once payment is verified, return to your invitation page, choose <strong>Order optional clothing</strong>, select the items, sizes and quantities, and complete the separate clothing payment.</p></div>@endif
    </div>
  </div>
</div>
