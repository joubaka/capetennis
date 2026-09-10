@php
  $event = $invitation->selectionImport?->event;
  $campaign = $campaign ?? [];
  $responseUrl = route('team-selection.invitations.show', $invitation);
  $logo = $event?->logo ? asset('storage/'.$event->logo) : asset('assets/img/logos/cape-tennis-logo-transparent.png');
  $message = $campaign['message'] ?? $invitation->selectionImport?->email_message;
  $includeClothing = (bool) ($campaign['include_clothing'] ?? $invitation->selectionImport?->include_clothing);
  $eventDetails = $campaign['event'] ?? [];
  $clothingItems = collect($campaign['clothing_items'] ?? []);
  $publicEventUrl = $eventDetails['public_url'] ?? ($event?->published ? route('events.show', $event) : null);
  $eventName = $eventDetails['name'] ?? $event?->name;
@endphp
<div style="width:100%;margin:0;background:#f4f3f8;padding:28px 12px;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;color:#4f4b5f;line-height:1.55;word-wrap:break-word">
  <div style="width:100%;max-width:640px;margin:0 auto;background:#fff;border:1px solid #e6e3ed;border-radius:12px;overflow:hidden;box-sizing:border-box">
    <div style="background:#15324b;padding:24px;text-align:center"><img src="{{ $logo }}" alt="Cape Tennis" width="150" style="background:#fff;border-radius:8px;padding:7px"><div style="color:#fff;text-transform:uppercase;letter-spacing:1.5px;margin-top:12px">{{ ($kind ?? 'invitation') === 'replacement' ? 'Replacement team invitation' : 'Platteland team invitation' }}</div></div>
    <div style="padding:30px 32px;box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word">
      <h2 style="color:#263b50">Hello {{ $invitation->player?->full_name ?? 'Player' }},</h2>
      @if($message)<p>{!! nl2br(e($message)) !!}</p>@endif
      <p>You have been selected to represent <strong>{{ $invitation->region?->region_name }}</strong> at <strong>{{ $eventName }}</strong>.</p>
      <div style="border:1px solid #d9e5eb;border-radius:8px;padding:16px 18px;margin:22px 0"><strong>How to register</strong><ol style="margin:10px 0 0;padding-left:20px"><li>Sign in to a Cape Tennis account.</li><li>Open the registration page using the green button below.</li><li>Choose <strong>Register and pay</strong> and complete the PayFast payment before the payment deadline.</li><li>Your team place is secured only after Cape Tennis verifies the payment.</li></ol><p style="margin:10px 0 0">If you are unavailable, let us know promptly so the next reserve can be invited.</p></div>
      <div style="text-align:center;margin:26px 0 10px">@if($publicEventUrl)<a href="{{ $publicEventUrl }}" style="display:inline-block;background:#fff;color:#425466;text-decoration:none;font-weight:bold;border:1px solid #b8c5cf;border-radius:6px;padding:11px 18px;margin:0 4px 8px">View event</a>@endif<a href="{{ $responseUrl }}?action=pay" style="display:inline-block;background:#16876f;color:#fff;text-decoration:none;font-weight:bold;border:1px solid #16876f;border-radius:6px;padding:11px 18px;margin:0 4px 8px">Register and pay</a><a href="{{ $responseUrl }}?action=decline" style="display:inline-block;background:#fff;color:#b54747;text-decoration:none;font-weight:bold;border:1px solid #d9a6a6;border-radius:6px;padding:11px 18px;margin:0 4px 8px">Decline invitation</a></div>
      <p style="font-size:13px;color:#777180;text-align:center">Register and Decline open your secure invitation page, where you confirm your choice.</p>
      @if($includeClothing)<div style="background:#eef7ff;border-radius:8px;padding:16px 18px;margin-top:20px;overflow-wrap:anywhere;word-break:break-word"><strong>Optional regional clothing</strong>@if($clothingItems->isNotEmpty())<table role="presentation" width="100%" style="width:100%;table-layout:fixed;border-collapse:collapse;margin-top:9px">@foreach($clothingItems as $item)<tr><td width="72%" style="width:72%;padding:6px 8px 6px 0;border-top:1px solid #d8e8f2;vertical-align:top;overflow-wrap:anywhere;word-break:break-word"><strong>{{ $item['name'] }}</strong>@if(!empty($item['sizes']))<br><span style="font-size:12px;color:#65717a">Sizes: {{ implode(', ', $item['sizes']) }}</span>@endif</td><td width="28%" style="width:28%;padding:6px 0;border-top:1px solid #d8e8f2;text-align:right;vertical-align:top;white-space:nowrap"><strong>R{{ number_format((float) $item['price'], 2) }}</strong></td></tr>@endforeach</table>@endif<p style="margin:12px 0 0"><strong>How to order:</strong> first complete the event registration and payment. Once payment is verified, return to your invitation page, choose <strong>Order optional clothing</strong>, select the items, sizes and quantities, and complete the separate clothing payment.</p></div>@endif
    </div>
  </div>
</div>
