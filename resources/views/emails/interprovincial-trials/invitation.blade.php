<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.6;color:#243746;max-width:640px;overflow-wrap:anywhere">
<p style="margin:0 0 20px"><strong>For {{ $recipientName ?: 'the nominated player' }}</strong></p>
<div>{!! nl2br(e($messageBody)) !!}</div>
<p style="margin:24px 0"><a href="{{ $invitationUrl }}" style="display:inline-block;padding:12px 20px;background:#183249;color:#ffffff;text-decoration:none;border-radius:6px">View event</a></p>
<p style="font-size:14px;color:#627587">This link opens your nominated player on the event page. When registration is open, choose Register and complete any required payment to confirm the entry.</p>
</div>
