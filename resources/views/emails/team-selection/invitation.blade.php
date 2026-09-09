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
      </table>
      @if($eventInformation)<div style="background:#fff8e8;border-radius:8px;padding:15px 17px;margin:0 0 22px"><strong>Event information</strong><br>{!! nl2br(e($eventInformation)) !!}</div>@endif
      <p>Your place is confirmed only after payment has been verified. If you are unavailable, please decline promptly so the next reserve can be invited.</p>
      <div style="text-align:center;margin:26px 0 10px"><a href="{{ $responseUrl }}?action=pay" style="display:inline-block;background:#16876f;color:#fff;text-decoration:none;font-weight:bold;border-radius:6px;padding:12px 20px;margin:0 4px 8px">Accept and pay</a><a href="{{ $responseUrl }}?action=decline" style="display:inline-block;background:#fff;color:#b54747;text-decoration:none;font-weight:bold;border:1px solid #d9a6a6;border-radius:6px;padding:11px 20px;margin:0 4px 8px">Decline invitation</a></div>
      <p style="font-size:13px;color:#777180;text-align:center">Both buttons open your secure invitation page, where you confirm your choice.</p>
      @if($includeClothing)<p style="background:#eef7ff;border-radius:8px;padding:14px 16px;margin-top:20px"><strong>Optional regional clothing is available.</strong> After event payment is confirmed, select items, sizes and quantities from your invitation page.</p>@endif
    </div>
  </div>
</div>
