<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email preview · {{ $subject }}</title>
</head>
<body style="margin:0;background:#e9edf2;font-family:Arial,Helvetica,sans-serif;color:#263b50">
  <div style="max-width:680px;margin:22px auto 0;padding:0 12px">
    <div style="background:#fff;border:2px solid #16876f;border-radius:10px;padding:16px 18px;box-sizing:border-box">
      <strong style="color:#16876f">{{ ($previewOnly ?? true) ? 'Preview only — no email has been sent' : 'Saved invitation email — read-only campaign snapshot' }}</strong>
      <div style="margin-top:10px"><span style="color:#777180">Sample player:</span> {{ $invitation->player?->full_name ?? 'Player' }}</div>
      <div style="margin-top:5px"><span style="color:#777180">Subject:</span> <strong>{{ $subject }}</strong></div>
      <div style="margin-top:5px"><span style="color:#777180">Version:</span> {{ substr($campaign['hash'], 0, 12) }}</div>
      <p style="margin:10px 0 0;font-size:13px;color:#777180">{{ ($previewOnly ?? true) ? 'Sending is unlocked only for this exact preview. Any change requires a new preview.' : 'This is the exact saved campaign content used for this invitation. Rank and recipient status may have changed since the campaign was first sent.' }}</p>
      @isset($recipients)
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid #dce3e9"><strong>Exact recipients ({{ count($recipients) }})</strong>@foreach($recipients as $recipient)<div style="margin-top:5px">{{ $recipient['name'] }} · {{ $recipient['email'] }}</div>@endforeach</div>
      @endisset
    </div>
  </div>
  @include('emails.team-selection.invitation', ['invitation' => $invitation, 'campaign' => $campaign, 'kind' => $kind])
  @isset($customSend)
    <div style="max-width:680px;margin:0 auto 28px;padding:0 12px">
      <form method="POST" action="{{ $customSend['route'] }}" style="background:#fff;border-radius:10px;padding:16px 18px;box-sizing:border-box">
        @csrf
        @foreach($customSend['invitation_ids'] as $invitationId)<input type="hidden" name="invitation_ids[]" value="{{ $invitationId }}">@endforeach
        <input type="hidden" name="email_subject" value="{{ $customSend['email_subject'] }}">
        <textarea name="email_message" hidden>{{ $customSend['email_message'] }}</textarea>
        <input type="hidden" name="preview_hash" value="{{ $customSend['preview_hash'] }}">
        <label style="display:block"><input type="checkbox" name="confirm_recipients" value="1" required> I confirm these exact recipients and this custom email.</label>
        <button type="submit" style="margin-top:12px;border:0;border-radius:6px;background:#16876f;color:#fff;padding:10px 16px;cursor:pointer">Send {{ count($customSend['invitation_ids']) }} custom invitation(s)</button>
      </form>
    </div>
  @endisset
</body>
</html>
