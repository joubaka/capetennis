@php
  $mailHistoryUrl = request()->routeIs('backend.superadmin.workspace') ? route('backend.superadmin.workspace', ['tab' => 'mails']) : route('backend.superadmin.mail-history');
  $statusLabels = ['queued' => 'Queued', 'sending' => 'Sending', 'sent' => 'Sent (all acceptance evidence)', 'failed' => 'Failed', 'skipped' => 'Skipped', 'acceptance_unknown' => 'Outcome unverified'];
  $progress = $mailSummary['queued'] + $mailSummary['sending'];
  $unverified = $mailSummary['unverified'] + $mailSummary['sandbox_accepted'];
@endphp
<style>
  @media (max-width: 575.98px) {
    .mail-history-table, .mail-history-table tbody { display: block; width: 100%; }
    .mail-history-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
    .mail-history-table tbody tr { display: block; margin: 12px; border: 1px solid var(--bs-border-color, #ddd); border-radius: 8px; }
    .mail-history-table tbody td { display: block; width: 100%; min-width: 0 !important; max-width: none !important; border: 0; padding: 8px 12px; white-space: normal !important; }
    .mail-history-table tbody td[data-label]::before { content: attr(data-label); display: block; font-size: 11px; color: var(--bs-secondary-color, #777); margin-bottom: 3px; }
    .mail-history-table tbody td[data-label="Logged"], .mail-history-table tbody td[data-label="Last evidence"] { display: inline-block; width: 49%; vertical-align: top; }
  }
</style>
<div class="row g-2 mb-3" aria-label="Mail summary for current filters">
  @foreach([
    ['Mail server accepted', $mailSummary['server_accepted'], 'success', 'Accepted by the mail server'],
    ['Failed', $mailSummary['failed'], 'danger', 'Sending attempt failed'],
    ['In progress', $progress, 'primary', number_format($mailSummary['queued']).' queued · '.number_format($mailSummary['sending']).' sending'],
    ['Outcome unverified', $unverified, 'warning', 'Includes sandbox acceptance'],
  ] as [$label, $count, $tone, $detail])
    <div class="col-6 col-lg-3"><div class="card h-100 border-start border-{{ $tone }}"><div class="card-body p-3">
      <div class="small text-muted mb-1">{{ $label }}</div><div class="h3 mb-1 text-{{ $tone }}">{{ number_format($count) }}</div><div class="small text-muted">{{ $detail }}</div>
    </div></div></div>
  @endforeach
</div>
<div class="card mb-3"><div class="card-body p-3">
  <form method="GET" action="{{ $mailHistoryUrl }}" class="row g-2 align-items-end">
    @if(request()->routeIs('backend.superadmin.workspace'))<input type="hidden" name="tab" value="mails">@endif
    @foreach(['mail_recipient' => ['Recipient', 'Email address'], 'mail_subject' => ['Subject', 'Search recorded subjects']] as $key => [$label, $placeholder])
      <div class="col-sm-6 col-lg-3"><label class="form-label small mb-1" for="{{ $key }}">{{ $label }}</label><input class="form-control form-control-sm" id="{{ $key }}" name="{{ $key }}" value="{{ $mailFilters[$key] ?? '' }}" maxlength="255" placeholder="{{ $placeholder }}"></div>
    @endforeach
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small mb-1" for="mail_type">Mail type</label><select class="form-select form-select-sm" id="mail_type" name="mail_type">
        <option value="">All mail types</option>
        @foreach($mailTypes as $type => $label)<option value="{{ $type }}" @selected(($mailFilters['mail_type'] ?? '') === $type)>{{ $label }}</option>@endforeach
      </select>
    </div>
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small mb-1" for="mail_status">Status</label><select class="form-select form-select-sm" id="mail_status" name="mail_status">
        <option value="">All statuses</option>
        @foreach($mailStatuses as $status)<option value="{{ $status }}" @selected(($mailFilters['mail_status'] ?? '') === $status)>{{ $statusLabels[$status] }}</option>@endforeach
      </select>
    </div>
    @foreach(['mail_from' => 'From date', 'mail_until' => 'Through date'] as $key => $label)
      <div class="col-6 col-lg-3"><label class="form-label small mb-1" for="{{ $key }}">{{ $label }}</label><input class="form-control form-control-sm" type="date" id="{{ $key }}" name="{{ $key }}" value="{{ $mailFilters[$key] ?? '' }}"></div>
    @endforeach
    <div class="col-12 col-lg-6 d-flex flex-wrap gap-2"><button class="btn btn-sm btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Apply filters</button><a class="btn btn-sm btn-outline-secondary" href="{{ $mailHistoryUrl }}">Clear filters</a></div>
    @if($mailTypesLimited)<div class="col-12 small text-muted">Showing the first 100 recorded mail types, plus your selected type.</div>@endif
  </form>
  @if($errors->any())<div class="alert alert-danger mt-2 mb-0" role="alert">{{ $errors->first() }}</div>@endif
</div></div>
<div class="card">
  <div class="card-header p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 mb-0">Logged emails <span class="badge bg-label-secondary ms-1">{{ number_format($mailLogs->total()) }}</span></h2><span class="small text-muted">{{ number_format($mailSummary['skipped']) }} skipped · Counts reflect current filters</span></div>
    <p class="small text-muted mb-0 mt-2">Tracked campaign and system email recipient records. Older unlogged messages cannot be recovered. Mail server acceptance does not confirm inbox delivery or reading.</p>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0 mail-history-table">
    <thead><tr><th>Recipient</th><th>Subject / type</th><th>Status</th><th>Logged</th><th>Last evidence</th></tr></thead>
    <tbody>
      @forelse($mailLogs as $mail)
        @php
          $typeLabel = \App\Services\SuperAdminMailHistory::typeLabel($mail->mail_type);
          [$tone, $statusText] = match (true) {
            $mail->status === 'sent' && $mail->evidence_status === 'server_accepted' && $mail->accepted_at !== null => ['success', 'Mail server accepted'],
            $mail->status === 'sent' && $mail->evidence_status === 'sandbox_accepted' && $mail->accepted_at !== null => ['warning', 'Sandbox accepted'],
            $mail->status === 'sent' => ['warning', 'Sent — acceptance unverified'],
            $mail->status === 'sending' => ['primary', 'Sending / outcome unverified'],
            $mail->status === 'acceptance_unknown' => ['warning', 'Outcome unverified'],
            $mail->status === 'failed' => ['danger', 'Failed'],
            $mail->status === 'skipped' => ['secondary', 'Skipped'],
            default => ['primary', 'Queued'],
          };
        @endphp
        <tr>
          <td data-label="Recipient" style="min-width:180px;max-width:260px;overflow-wrap:anywhere"><span class="fw-semibold">{{ $mail->recipient_email }}</span>@if($mail->recipient_name)<small class="d-block text-muted mt-1">{{ $mail->recipient_name }}</small>@endif</td>
          <td data-label="Subject / type" style="min-width:200px;max-width:360px;overflow-wrap:anywhere"><span class="fw-semibold">{{ $mail->history_subject ?: $typeLabel }}</span><small class="d-block text-muted mt-1">{{ $mail->history_subject ? $typeLabel : 'Subject not recorded' }}</small></td>
          <td data-label="Status" style="min-width:165px"><span class="badge bg-label-{{ $tone }} text-wrap text-start lh-base">{{ $statusText }}</span></td>
          <td data-label="Logged" class="small text-nowrap">{{ $mail->created_at?->format('d M Y') }}<small class="d-block text-muted">{{ $mail->created_at?->format('H:i') }}</small></td>
          <td data-label="Last evidence" class="small text-nowrap">{{ ($mail->accepted_at ?? $mail->sent_at ?? $mail->failed_at ?? $mail->skipped_at ?? $mail->queued_at)?->format('d M Y') ?? '—' }}<small class="d-block text-muted">{{ ($mail->accepted_at ?? $mail->sent_at ?? $mail->failed_at ?? $mail->skipped_at ?? $mail->queued_at)?->format('H:i') }}</small></td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center py-5"><i class="ti ti-mail-search fs-2 text-muted" aria-hidden="true"></i><p class="fw-semibold mb-1 mt-2">No logged emails match these filters.</p><p class="text-muted small mb-0">Try a wider date range or clear the filters.</p></td></tr>
      @endforelse
    </tbody>
  </table></div>
  @if($mailLogs->hasPages())<div class="card-footer p-3">{{ $mailLogs->links() }}</div>@endif
</div>
