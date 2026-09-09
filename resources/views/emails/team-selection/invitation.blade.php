@php
  $event = $invitation->selectionImport?->event;
  $responseUrl = route('team-selection.invitations.show', $invitation);
  $logo = $event?->logo ? asset('storage/'.$event->logo) : asset('assets/img/logos/cape-tennis-logo-transparent.png');
@endphp
<div style="margin:0;background:#f4f3f8;padding:28px 12px;font-family:Arial,Helvetica,sans-serif;color:#4f4b5f;line-height:1.55">
  <div style="max-width:620px;margin:auto;background:#fff;border:1px solid #e6e3ed;border-radius:12px;overflow:hidden">
    <div style="background:#7651e8;padding:24px;text-align:center"><img src="{{ $logo }}" alt="Cape Tennis" width="150" style="background:#fff;border-radius:8px;padding:7px"><div style="color:#fff;text-transform:uppercase;letter-spacing:1.5px;margin-top:12px">Platteland team invitation</div></div>
    <div style="padding:30px 32px">
      <h2 style="color:#403b52">Hello {{ $invitation->player?->full_name ?? 'Player' }},</h2>
      <p>You have been selected to represent <strong>{{ $invitation->region?->region_name }}</strong> at <strong>{{ $event?->name }}</strong>.</p>
      <div style="background:#f7f5ff;border-left:4px solid #7651e8;padding:16px 18px;margin:22px 0"><strong>{{ $invitation->team?->name }}</strong><br>Ranking position: {{ $invitation->ranking_position }}<br>Playing order: {{ $invitation->roster_rank }}</div>
      <p>Please register and complete payment by {{ $invitation->selectionImport?->payment_deadline?->format('d M Y H:i') }}. If unavailable, decline promptly so the next reserve can be invited.</p>
      <p style="text-align:center;margin-top:26px"><a href="{{ $responseUrl }}" style="display:inline-block;background:#7651e8;color:#fff;text-decoration:none;font-weight:bold;border-radius:6px;padding:12px 22px">Respond to invitation</a></p>
    </div>
  </div>
</div>
