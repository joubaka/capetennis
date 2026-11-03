<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f3f6fa;font-family:Arial,sans-serif;color:#102b4e">
<div style="max-width:580px;margin:24px auto;padding:24px;background:#fff;border-top:5px solid #f28b22">
    <p style="color:#2167af;font-weight:bold">Cape Tennis Player Plus</p>
    <h1 style="font-size:24px">{{ $eventName }}</h1>
    <p>Hello {{ $playerName }} and linked contacts,</p>
    @if($drawPublished)<p>The draw for {{ $playerName }} has been published: {{ $drawName }}.</p>@endif
    @if($scheduleChanged)<p>The schedule for {{ $drawName }} {{ $scheduleInitial ? 'has been published' : 'has been updated' }}.</p>@endif
    <p style="margin:28px 0"><a href="{{ $profileUrl }}" style="display:inline-block;padding:14px 18px;background:#2167af;color:#fff;text-decoration:none;border-radius:5px">Open {{ $playerName }} in My Tennis</a></p>
    <p>Sign in with an account already linked to this player to open their My Tennis page.</p>
    <p><a href="{{ $publicUrl }}" style="color:#2167af">View the published draw and schedule</a></p>
    <p style="font-size:13px;color:#52627a">Please check the latest published page for changes.</p>
</div>
</body></html>
