@php
    $reportPrefix = $report['prefix'];
    $reportFilters = $report['filters'];
    $reportContext = $reportContext ?? [];
    $reportUrl = $reportUrl ?? url()->current();
    $reportEvent = $reportEvent ?? null;
    $quickOutcomes = ['all' => 'All', 'sent_complete' => 'Sent', 'failed' => 'Failed', 'pending' => 'Pending', 'skipped' => 'Excluded'];
    $reportLink = function ($outcome) use ($reportPrefix, $reportContext, $reportUrl, $report) {
        $query = array_merge(request()->except([$report['page'], $reportPrefix.'outcome']), $reportContext);
        if ($outcome !== 'all') $query[$reportPrefix.'outcome'] = $outcome;
        return $reportUrl.'?'.http_build_query($query).'#email-history';
    };
@endphp
<style>
.mail-report{font-size:1rem}.mail-report .mail-quick{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.6rem}.mail-report .mail-quick a{color:#263d5b;background:#fff;border:1px solid #d6dde7;border-radius:.5rem;padding:.85rem;text-decoration:none;min-width:0}.mail-report .mail-quick a[aria-current=page]{border-color:#235b96;box-shadow:0 0 0 1px #235b96}.mail-report .mail-quick strong{display:block;font-size:1.45rem}.mail-report .mail-report-row{display:grid;grid-template-columns:minmax(150px,1fr) minmax(180px,1.5fr) minmax(160px,1fr) 110px 140px;gap:1rem;align-items:center;padding:1rem;border-top:1px solid #d6dde7;background:#fff}.mail-report .mail-report-row>div{min-width:0;overflow-wrap:anywhere}.mail-report .mail-report-list{border:1px solid #d6dde7;border-radius:.5rem;overflow:hidden}.mail-report .mail-report-head{background:#f2f5f8;font-weight:600;font-size:.875rem}.mail-report .mail-report-secondary{font-size:.875rem;color:#526174}.ct-backend .mail-report .mail-report-action .btn:not(.p-0):not(.btn-icon){min-height:44px;min-width:68px;white-space:nowrap}.mail-report a{color:#174d83}.mail-report .btn-outline-secondary{color:#42536a;border-color:#8392a5}.mail-report .mail-report-neutral{background:#edf1f5;color:#42536a}.mail-report .badge{white-space:normal;font-size:.875rem;line-height:1.4}.mail-report .mail-quick a:hover{border-color:#235b96}.mail-report .pagination{flex-wrap:wrap}@media(max-width:991.98px){.mail-report .mail-report-row{grid-template-columns:minmax(0,1fr) minmax(0,1.3fr)}.mail-report .mail-report-head{display:none}}@media(max-width:575.98px){.mail-report .mail-quick{grid-template-columns:repeat(2,minmax(0,1fr))}.mail-report .mail-report-row{grid-template-columns:minmax(0,1fr);gap:.65rem}.mail-report .mail-report-action{display:flex;gap:.5rem}.mail-report .mail-report-row .mail-report-date{font-size:.875rem}.mail-report .mail-report-row>div{width:100%}}
</style>
<section class="mail-report mb-4" id="email-history" aria-label="Email history">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><h2 class="h4 mb-0">{{ $reportTitle ?? 'Email history' }}</h2><span class="mail-report-secondary">{{ number_format($report['logs']->total()) }} matching records</span></div>
<div class="mail-quick mb-3">
@foreach($quickOutcomes as $value=>$label)
<a href="{{ $reportLink($value) }}" @if(($reportFilters['outcome'] ?? 'all')===$value) aria-current="page" @endif><span>{{ $label }}</span><strong>{{ number_format($report['counts'][$value]) }}</strong></a>
@endforeach
</div>
<p class="mail-report-secondary">Counts reflect current filters for search, dates, type and report scope, before outcome filtering. {{ number_format($report['recipientCount']) }} recipient records; {{ number_format($report['copyCount']) }} copies.</p>
<p class="mail-report-secondary">Sent includes completed historical sends without acceptance evidence. It excludes sandbox and uncertain outcomes. Inbox delivery is not confirmed. <a href="{{ $reportLink('accepted') }}">{{ number_format($report['counts']['accepted']) }} server accepted</a> &middot; <a href="{{ $reportLink('uncertain') }}">{{ number_format($report['counts']['uncertain']) }} acceptance uncertain</a>.</p>
<form method="get" action="{{ $reportUrl }}" class="card card-body mb-3">
@foreach($reportContext as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="{{ $reportPrefix }}search">Search recipient or subject</label><input class="form-control" id="{{ $reportPrefix }}search" name="{{ $reportPrefix }}search" value="{{ $reportFilters['search'] ?? '' }}" maxlength="200"></div><div class="col-md-6"><label class="form-label" for="{{ $reportPrefix }}outcome">Outcome</label><select class="form-select" id="{{ $reportPrefix }}outcome" name="{{ $reportPrefix }}outcome"><option value="">All outcomes</option>@foreach(\App\Services\MailReportFilters::OUTCOMES as $value=>$label)<option value="{{ $value }}" @selected(($reportFilters['outcome'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></div></div>
<details class="mt-3" @if(!empty($reportFilters['mail_type']) || !empty($reportFilters['from']) || !empty($reportFilters['until'])) open @endif><summary>Type and date filters</summary><div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label" for="{{ $reportPrefix }}mail_type">Email type</label><select class="form-select" id="{{ $reportPrefix }}mail_type" name="{{ $reportPrefix === 'mail_' ? 'mail_type' : $reportPrefix.'mail_type' }}"><option value="">All recorded types</option>@foreach($report['types'] as $value=>$label)<option value="{{ $value }}" @selected(($reportFilters['mail_type'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label" for="{{ $reportPrefix }}from">From</label><input class="form-control" type="date" id="{{ $reportPrefix }}from" name="{{ $reportPrefix }}from" value="{{ old($reportPrefix.'from',$reportFilters['from'] ?? '') }}"></div><div class="col-md-3"><label class="form-label" for="{{ $reportPrefix }}until">Until</label><input class="form-control" type="date" id="{{ $reportPrefix }}until" name="{{ $reportPrefix }}until" value="{{ old($reportPrefix.'until',$reportFilters['until'] ?? '') }}"></div></div></details>
<div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary">Apply filters</button><a class="btn btn-outline-secondary" href="{{ $reportUrl.'?'.http_build_query($reportContext).'#email-history' }}">Reset filters</a></div>
@if($errors->any())<div class="alert alert-danger mt-3 mb-0" role="alert">{{ $errors->first() }}</div>@endif
</form>
@if(isset($report['groups']))
<p class="mail-report-secondary">{{ number_format($report['groups']->total()) }} mail sends. Open a send to view its matching recipients and individual outcomes. Historical groups without a batch reference combine the same subject, type, sender and recorded day.</p>
@forelse($report['groups'] as $send)
<details class="card mb-2 mail-send-group" data-mail-send>
<summary class="p-3" style="cursor:pointer;min-height:44px"><strong>{{ $send->send_subject }}</strong><div class="mail-report-secondary mt-1">{{ \App\Services\SuperAdminMailHistory::typeLabel($send->send_type) }} &middot; {{ \Carbon\Carbon::parse($send->first_recorded)->format('d M Y H:i') }} SAST &middot; {{ number_format($send->recipient_count) }} matching records @if(str_starts_with($send->send_key,'legacy:')) &middot; Historical day group @else &middot; {{ str_starts_with($send->send_key,'batch:') ? 'Batch' : 'Reviewed preview' }} #{{ substr($send->send_key,strpos($send->send_key,':')+1) }} @endif</div><div class="d-flex flex-wrap gap-2 mt-2"><span>Completed: {{ $send->sent_count }}</span><span>Failed: {{ $send->failed_count }}</span><span>Pending: {{ $send->pending_count }}</span><span>Excluded: {{ $send->skipped_count }}</span><span>Acceptance uncertain: {{ $send->uncertain_count }}</span></div></summary>
<div class="p-3 pt-0" data-send-content data-send-url="{{ $reportUrl.'?'.http_build_query(array_merge(request()->except(['history_sends_page','send_recipients_page']),$reportContext,['history_send'=>$send->representative_id])) }}"><a class="btn btn-outline-primary" href="{{ $reportUrl.'?'.http_build_query(array_merge(request()->except(['history_sends_page','send_recipients_page']),$reportContext,['history_send'=>$send->representative_id])) }}">Open recipient list</a></div>
</details>
@empty<div class="card p-4 text-center">No logged emails match these filters.</div>@endforelse
@if($report['groups']->hasPages())<div class="mt-3">{{ $report['groups']->links('pagination::bootstrap-5') }}</div>@endif
<script>
(function () {
    const section = document.getElementById('email-history');
    if (!section) return;
    async function load(content, url) {
        if (content.dataset.loading) return;
        content.dataset.loading = '1';
        content.setAttribute('aria-busy', 'true');
        const status = document.createElement('p');
        status.setAttribute('role', 'status'); status.textContent = 'Loading recipients...'; content.prepend(status);
        try {
            const response = await fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok || response.redirected) throw new Error('Unable to load recipients.');
            content.innerHTML = await response.text(); content.dataset.loaded = '1';
        } catch (error) {
            status.textContent = 'Could not load recipients. Use the link below to try again.';
            if (!content.querySelector('a')) { const retry = document.createElement('a'); retry.href = url; retry.textContent = 'Try again'; content.append(retry); }
        } finally { delete content.dataset.loading; content.removeAttribute('aria-busy'); }
    }
    section.querySelectorAll('[data-mail-send]').forEach(group => {
        group.addEventListener('toggle', () => { const content = group.querySelector('[data-send-content]'); if (group.open && !content.dataset.loaded) load(content, content.dataset.sendUrl); });
    });
    section.addEventListener('click', event => {
        const link = event.target.closest('[data-send-content] a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link.closest('[data-send-pagination]') || !link.closest('[data-send-content]').dataset.loaded) {event.preventDefault(); load(link.closest('[data-send-content]'), link.href);}
    });
})();
</script>
@else
<div class="mail-report-list"><div class="mail-report-row mail-report-head"><div>Recipient</div><div>Subject / type</div><div>Outcome</div><div>Recorded</div><div>Action</div></div>
@forelse($report['logs'] as $mail)
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
<article class="mail-report-row"><div><strong>{{ $mail->recipient_name ?: $mail->recipient_email }}</strong>@if($mail->recipient_name)<div class="mail-report-secondary">{{ $mail->recipient_email }}</div>@endif</div><div><strong>{{ $reportSubject }}</strong><div class="mail-report-secondary">{{ \App\Services\SuperAdminMailHistory::typeLabel($mail->mail_type) }}@if(in_array(data_get($mail->payload,'recipient_kind'),['admin_copy','sender_copy'])) &middot; Copy @endif</div></div><div><span class="badge bg-label-{{ $tone }} @if($tone==='secondary') mail-report-neutral @endif">{{ $label }}</span>@if($mail->status==='failed')<div class="mail-report-secondary mt-1">{{ \App\Services\EventMailLogService::explanation($mail) ?: 'Review this failed attempt before retrying.' }}</div>@endif</div><div class="mail-report-date">{{ $mail->created_at?->format('d M Y') }}<div class="mail-report-secondary">{{ $mail->created_at?->format('H:i') }} SAST</div></div><div class="mail-report-action">@if($reportEvent)<a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.show',[$reportEvent,$mail]) }}">Open</a>
@if($mail->status==='failed' && !$mail->sent_at && !$mail->accepted_at)
@if(in_array($mail->mail_type,['team_selection_invitation','interprovincial_trial_invitation']) || (data_get($mail->payload,'event_communication_batch_id') && \App\Models\EventCommunicationBatch::whereKey(data_get($mail->payload,'event_communication_batch_id'))->where('event_id',$reportEvent->id)->where('created_by',auth()->id())->exists()))
<form method="post" action="{{ route('backend.event-communications.retry-preview',[$reportEvent,$mail]) }}" data-mail-launch>@csrf<button class="btn btn-outline-primary mt-2">Review retry</button></form>
@elseif($reportEvent->isInterprovincialTrials() && data_get($mail->payload,'preview_id'))
<details class="mt-2"><summary>Review retry</summary><strong>{{ data_get($mail->payload,'subject') }}</strong><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ html_entity_decode(strip_tags(data_get($mail->payload,'body','')), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</pre><form method="post" action="{{ route('backend.interprovincial-trials.communications.retry',[$reportEvent,$mail]) }}">@csrf<label><input type="checkbox" name="approved_retry" value="1" required> I reviewed and approve this exact recipient and message.</label><button class="btn btn-outline-primary mt-2">Approve retry</button></form></details>
@endif
@endif
@else<span class="mail-report-secondary">Metadata only</span>@endif</div></article>
@empty<div class="p-4 text-center"><strong>No logged emails match these filters.</strong><p class="mail-report-secondary mb-0 mt-2">Try a different outcome or reset the filters.</p></div>@endforelse
</div>
@if($report['logs']->hasPages())<div class="mt-3">{{ $report['logs']->links('pagination::bootstrap-5') }}</div>@endif
@endif
</section>
