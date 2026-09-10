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
      <strong style="color:#16876f">Preview only — no email has been sent</strong>
      <div style="margin-top:10px"><span style="color:#777180">Sample player:</span> {{ $invitation->player?->full_name ?? 'Player' }}</div>
      <div style="margin-top:5px"><span style="color:#777180">Subject:</span> <strong>{{ $subject }}</strong></div>
      <div style="margin-top:5px"><span style="color:#777180">Version:</span> {{ substr($campaign['hash'], 0, 12) }}</div>
      <p style="margin:10px 0 0;font-size:13px;color:#777180">Sending is unlocked only for this exact preview. Any change requires a new preview.</p>
    </div>
  </div>
  @include('emails.team-selection.invitation', ['invitation' => $invitation, 'campaign' => $campaign, 'kind' => $kind])
</body>
</html>
