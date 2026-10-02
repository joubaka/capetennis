<h2 class="h5">All logged emails</h2>
<p class="text-muted">Existing logged campaigns and system emails tracked from the time mail history was enabled. Older unlogged messages cannot be recovered. Mail server acceptance does not confirm inbox delivery or reading.</p>
@php($mailHistoryUrl = request()->routeIs('backend.superadmin.workspace') ? route('backend.superadmin.workspace', ['tab' => 'mails']) : route('backend.superadmin.mail-history'))
<form method="GET" action="{{ $mailHistoryUrl }}" class="row g-2 mb-4">
  @if(request()->routeIs('backend.superadmin.workspace'))<input type="hidden" name="tab" value="mails">@endif
  @foreach(['mail_recipient' => 'Recipient email', 'mail_subject' => 'Subject', 'mail_type' => 'Mail type'] as $key => $label)
    <div class="col-sm-6 col-lg-4">
      <label class="form-label" for="{{ $key }}">{{ $label }}</label>
      <input class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ $mailFilters[$key] ?? '' }}" maxlength="{{ $key === 'mail_type' ? 100 : 255 }}">
    </div>
  @endforeach
  <div class="col-sm-6 col-lg-4">
    <label class="form-label" for="mail_status">Status</label>
    <select class="form-select" id="mail_status" name="mail_status">
      <option value="">All statuses</option>
      @foreach($mailStatuses as $status)
        <option value="{{ $status }}" @selected(($mailFilters['mail_status'] ?? '') === $status)>{{ $status === 'sent' ? 'Sent (see acceptance evidence)' : ($status === 'acceptance_unknown' ? 'Outcome unverified' : ucfirst($status)) }}</option>
      @endforeach
    </select>
  </div>
  @foreach(['mail_from' => 'From date', 'mail_until' => 'Through date'] as $key => $label)
    <div class="col-sm-6 col-lg-4">
      <label class="form-label" for="{{ $key }}">{{ $label }}</label>
      <input class="form-control" type="date" id="{{ $key }}" name="{{ $key }}" value="{{ $mailFilters[$key] ?? '' }}">
    </div>
  @endforeach
  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary" type="submit">Filter emails</button>
    <a class="btn btn-outline-secondary" href="{{ $mailHistoryUrl }}">Clear</a>
  </div>
</form>
@if($errors->any())
  <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif
<p class="small text-muted">{{ number_format($mailLogs->total()) }} logged emails match these filters.</p>
<div class="table-responsive">
  <table class="table table-hover">
    <thead><tr><th>Logged</th><th>Recipient</th><th>Subject / type</th><th>Status</th><th>Last evidence</th></tr></thead>
    <tbody>
      @forelse($mailLogs as $mail)
        <tr>
          <td class="text-nowrap">{{ $mail->created_at?->format('d M Y H:i') }}</td>
          <td style="overflow-wrap:anywhere">{{ $mail->recipient_email }}@if($mail->recipient_name)<small class="d-block text-muted">{{ $mail->recipient_name }}</small>@endif</td>
          <td style="overflow-wrap:anywhere">{{ $mail->history_subject ?: 'Subject not recorded' }}<small class="d-block text-muted">{{ $mail->mail_type }}</small></td>
          <td>{{ $mail->status === 'sending' ? 'Sending / outcome unverified' : ($mail->status === 'acceptance_unknown' ? 'Outcome unverified' : $mail->delivery_status_label) }}</td>
          <td class="text-nowrap">{{ ($mail->accepted_at ?? $mail->sent_at ?? $mail->failed_at ?? $mail->skipped_at ?? $mail->queued_at)?->format('d M Y H:i') ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-muted text-center py-4">No logged emails match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
{{ $mailLogs->links() }}
