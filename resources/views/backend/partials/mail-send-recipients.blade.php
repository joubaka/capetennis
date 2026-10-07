<div class="mail-report-list"><div class="mail-report-row mail-report-head"><div>Recipient</div><div>Subject / type</div><div>Outcome</div><div>Recorded</div><div>Action</div></div>
@forelse($sendRecipients as $mail)

@php
    $reportSubject = $mail->history_subject ?? ($reportEvent ? \App\Services\EventMailLogService::subject($mail) : 'Subject not recorded');
    [$tone,$label] = match(true) {
        $mail->status==='sent' && $mail->evidence_status==='server_accepted' && $mail->accepted_at => ['success','Server accepted'],
        $mail->status==='sent' && $mail->evidence_status==='sandbox_accepted' && $mail->accepted_at => ['secondary','Sandbox accepted'],
        $mail->status==='sent' => ['secondary','Sent - evidence unverified'],
        $mail->status==='acceptance_unknown' => ['warning','Acceptance uncertain'],
        $mail->status==='failed' => ['danger','Failed'],
        $mail->status==='skipped' => ['secondary','Excluded'],
        default => ['primary',$mail->delivery_status_label],
    };
@endphp
<article class="mail-report-row"><div><strong>{{ $mail->recipient_name ?: $mail->recipient_email }}</strong>@if($mail->recipient_name)<div class="mail-report-secondary">{{ $mail->recipient_email }}</div>@endif</div><div><strong>{{ $reportSubject }}</strong><div class="mail-report-secondary">{{ \App\Services\SuperAdminMailHistory::typeLabel($mail->mail_type) }}@if(in_array(data_get($mail->payload,'recipient_kind'),['admin_copy','sender_copy'])) · Copy @endif</div></div><div><span class="badge bg-label-{{ $tone }} @if($tone==='secondary') mail-report-neutral @endif">{{ $label }}</span>@if($mail->status==='failed')<div class="mail-report-secondary mt-1">{{ \App\Services\EventMailLogService::explanation($mail) ?: 'Review this failed attempt before retrying.' }}</div>@endif</div><div class="mail-report-date">{{ $mail->created_at?->format('d M Y') }}<div class="mail-report-secondary">{{ $mail->created_at?->format('H:i') }} SAST</div></div><div class="mail-report-action">@if($reportEvent)<a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.show',[$reportEvent,$mail]) }}">Open</a>
@if($mail->status==='failed' && !$mail->sent_at && !$mail->accepted_at)
@if(in_array($mail->mail_type,['team_selection_invitation','interprovincial_trial_invitation']) || (data_get($mail->payload,'event_communication_batch_id') && \App\Models\EventCommunicationBatch::whereKey(data_get($mail->payload,'event_communication_batch_id'))->where('event_id',$reportEvent->id)->where('created_by',auth()->id())->exists()))
<form method="post" action="{{ route('backend.event-communications.retry-preview',[$reportEvent,$mail]) }}">@csrf<button class="btn btn-outline-primary mt-2">Review retry</button></form>
@elseif($reportEvent->isInterprovincialTrials() && data_get($mail->payload,'preview_id'))
<details class="mt-2"><summary>Review retry</summary><strong>{{ data_get($mail->payload,'subject') }}</strong><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ html_entity_decode(strip_tags(data_get($mail->payload,'body','')), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</pre><form method="post" action="{{ route('backend.interprovincial-trials.communications.retry',[$reportEvent,$mail]) }}">@csrf<label><input type="checkbox" name="approved_retry" value="1" required> I reviewed and approve this exact recipient and message.</label><button class="btn btn-outline-primary mt-2">Approve retry</button></form></details>
@endif
@endif
@else<span class="mail-report-secondary">Metadata only</span>@endif</div></article>
@empty<div class="p-3">No recipients match these filters.</div>@endforelse
</div>
@if($sendRecipients->hasPages())<div class="mt-3" data-send-pagination>{{ $sendRecipients->links('pagination::bootstrap-5') }}</div>@endif
