<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email preview · {{ $subject }}</title>
  <style>
    * { box-sizing:border-box; }
    body { margin:0;background:#e9edf2;font-family:Arial,Helvetica,sans-serif;color:#263b50;line-height:1.5; }
    .mail-review { width:100%;max-width:760px;margin:0 auto;padding:28px 20px 40px;overflow-wrap:anywhere; }
    .mail-review h1 { margin:0 0 18px;font-size:26px;line-height:1.25; }
    .mail-review h2 { margin:0 0 12px;font-size:18px; }
    .mail-review-card { background:#fff;border:1px solid #cbd5df;border-radius:10px;padding:20px; }
    .mail-review-summary { border:2px solid #16876f; }
    .mail-review-status { color:#11745f; }
    .mail-review-detail { margin-top:6px; }
    .mail-review-muted { color:#596575; }
    .mail-review-note { margin:12px 0 0;font-size:14px; }
    .mail-review-recipients { margin-top:16px;padding-top:12px;border-top:1px solid #dce3e9; }
    .mail-review-example, .mail-review-send { margin-top:24px; }
    .mail-review-frame { display:block;width:100%;height:clamp(560px,75vh,1000px);border:1px solid #cbd5df;border-radius:10px;background:#fff; }
    .mail-review-confirm { display:flex;align-items:flex-start;gap:10px;min-height:44px;padding:10px 0;cursor:pointer; }
    .mail-review-confirm input { flex:none;width:20px;height:20px;margin:2px 0 0; }
    .mail-review-submit { max-width:100%;min-height:44px;margin-top:12px;border:0;border-radius:6px;background:#11745f;color:#fff;padding:12px 18px;font:inherit;font-weight:bold;white-space:normal;cursor:pointer; }
    @media(max-width:575px) {
      .mail-review { padding:18px 12px 28px; }
      .mail-review h1 { font-size:23px; }
      .mail-review-card { padding:16px; }
      .mail-review-frame { height:clamp(520px,80vh,900px); }
      .mail-review-submit { width:100%; }
    }
  </style>
</head>
<body>
<main data-mail-review class="operational-page mail-review">
@include('backend.partials.operational-controls')
  <h1>Email preview</h1>
    <section class="mail-review-card mail-review-summary" aria-label="Email review details">
      <strong class="mail-review-status">{{ ($previewOnly ?? true) ? 'Preview only — no email has been sent' : 'Saved invitation email — read-only campaign snapshot' }}</strong>
      <div class="mail-review-detail"><span class="mail-review-muted">Sample player:</span> {{ $invitation->player?->full_name ?? 'Player' }}</div>
      <div class="mail-review-detail"><span class="mail-review-muted">Subject:</span> <strong>{{ $subject }}</strong></div>
      <div class="mail-review-detail"><strong>From:</strong> {{ $campaign['from_name'] ?? config('mail.from.name') }} · <strong>Reply-to:</strong> {{ $campaign['reply_to'] ?? '' }}</div>
      <div class="mail-review-detail"><span class="mail-review-muted">Version:</span> {{ substr($campaign['hash'], 0, 12) }}</div>
      <p class="mail-review-note mail-review-muted">{{ ($previewOnly ?? true) ? 'Sending is unlocked only for this exact preview. Any change requires a new preview.' : 'This is the exact saved campaign content used for this invitation. Rank and recipient status may have changed since the campaign was first sent.' }}</p>
      @isset($recipients)
        <div class="mail-review-recipients"><details><summary>Review all {{ count($recipients) }} recipients</summary>@foreach($recipients as $recipient)<div class="mail-review-detail">{{ $recipient['name'] }} · {{ $recipient['email'] }}</div>@endforeach</details></div>
      @endisset
    </section>
  @php($sample = collect($previewVariants ?? [])->first())
  <section class="mail-review-example" aria-labelledby="example-email-heading">
  <h2 id="example-email-heading">Example email</h2>
  <iframe title="Example invitation email" sandbox="" srcdoc="{{ view('emails.team-selection.invitation', ['invitation' => $sample['invitation'] ?? $invitation, 'campaign' => $campaign, 'kind' => $sample['kind'] ?? $kind])->render() }}" class="mail-review-frame"></iframe>
  </section>
  @if(isset($customSend) || isset($campaignSend))
    <p class="mail-review-note mail-review-muted">Sending submits the reviewed emails for background delivery. Delivery may continue after you leave this page.</p>
  @endif
  @isset($customSend)
      <form method="POST" action="{{ $customSend['route'] }}" class="mail-review-card mail-review-send">
        @csrf
        @foreach($customSend['sender'] ?? [] as $field => $value)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endforeach
        @foreach($customSend['invitation_ids'] as $invitationId)<input type="hidden" name="invitation_ids[]" value="{{ $invitationId }}">@endforeach
        <input type="hidden" name="email_subject" value="{{ $customSend['email_subject'] }}">
        <textarea name="email_message" hidden>{{ $customSend['email_message'] }}</textarea>
        <input type="hidden" name="preview_hash" value="{{ $customSend['preview_hash'] }}">
        <input type="hidden" name="preview_token" value="{{ $customSend['preview_token'] }}">
        <label class="mail-review-confirm"><input type="checkbox" name="confirm_recipients" value="1" required> <span>I confirm these exact recipients and this custom email.</span></label>
        <button type="submit" class="mail-review-submit">Approve and send {{ count($customSend['invitation_ids']) }} emails</button>
      </form>
  @endisset
  @isset($campaignSend)
    <form method="POST" action="{{ $campaignSend['route'] }}" class="mail-review-card mail-review-send">
      @csrf
      @foreach($campaignSend['data'] as $field => $value)
        @if(is_scalar($value))<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endif
      @endforeach
      <label class="mail-review-confirm"><input type="checkbox" required> <span>I confirm the recipients and example invitation are correct.</span></label>
      <button class="mail-review-submit" type="submit">Approve and send invitations</button>
    </form>
  @endisset
</main>
</body>
</html>
