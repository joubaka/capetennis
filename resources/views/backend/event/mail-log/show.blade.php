@extends('layouts/layoutMaster')

@section('title', 'Email record')

@section('content')

@include('backend.event.mail-log.feedback')

<a class="btn btn-outline-secondary mb-3" href="{{ route('backend.event-mail-log.index',$event) }}">Back to email log</a>

<div class="card"><div class="card-body" style="overflow-wrap:anywhere"><h1 class="h4">{{ \App\Services\EventMailLogService::subject($log) }}</h1><p>{{ $event->name }}</p><dl class="row"><dt class="col-sm-3">Recipient</dt><dd class="col-sm-9">{{ $log->recipient_name }} {{ $log->recipient_email }}</dd><dt class="col-sm-3">Sender</dt><dd class="col-sm-9">{{ data_get($log->payload,'from_name','Not recorded') }}</dd><dt class="col-sm-3">Audience</dt><dd class="col-sm-9">{{ data_get($log->payload,'recipient_kind',\App\Services\SuperAdminMailHistory::typeLabel($log->mail_type)) }} @if(data_get($log->payload,'region_id')) · Region #{{ data_get($log->payload,'region_id') }} @endif @if(data_get($log->payload,'team_id')) · Team #{{ data_get($log->payload,'team_id') }} @endif</dd><dt class="col-sm-3">Campaign</dt><dd class="col-sm-9">{{ data_get($log->payload,'campaign_key',data_get($log->payload,'event_communication_batch_id','Not recorded')) }}</dd><dt class="col-sm-3">Outcome</dt><dd class="col-sm-9">{{ $log->delivery_status_label }}</dd>@foreach(['queued_at'=>'Queued','sent_at'=>'Transport completed','accepted_at'=>'Server accepted','failed_at'=>'Failed','skipped_at'=>'Skipped'] as $field=>$label)@if($log->$field)<dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9">{{ $log->$field->format('d M Y H:i:s') }} SAST</dd>@endif @endforeach</dl>

@if($reason=\App\Services\EventMailLogService::explanation($log))<div class="alert alert-warning" style="color:#513c06;background:#fff3cd;border-color:#ffe69c">{{ $reason }}</div>@endif

<h2 class="h5">Saved message text</h2>

<p>Initiated by: {{ isset($sender) && $sender ? $sender->name.' (#'.$sender->id.')' : (data_get($log->payload,'system_initiated') ? 'System initiated' : 'Not recorded') }}</p>

@php($body=(data_get($log->payload,'rendered_html') ?? data_get($log->payload,'rendered_text') ?? data_get($log->payload,'body') ?? data_get($log->payload,'message')))

@if($body)<div class="border rounded p-3" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ trim(strip_tags(preg_replace('/<\/(p|div|li|h[1-6])>|<br\s*\/?\s*>/i',"\n",$body))) }}</div>@elseif(data_get($log->payload,'financial_metadata_only'))<p>This financial email retains metadata only. Payment and bank details are excluded from this log.</p>@else<p>The message body was not saved for this historical record.</p>@endif

<p class="text-muted mt-3">Message text is displayed without active HTML or links. A completed send or server acceptance does not prove delivery or reading.</p>

@if(\App\Services\EventMailLogService::canRetry($log))

@if($retryPreview ?? false)<form method="post" action="{{ route('backend.event-mail-log.retry',[$event,$log]) }}">@csrf<label class="d-block mb-2"><input type="checkbox" name="confirmed" value="1" required> I reviewed this saved message and recipient and want to retry this failed email.</label><button class="btn btn-primary">Queue reviewed retry</button></form>@else<a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.retry-preview',[$event,$log]) }}">Review failed email for retry</a>@endif

@endif

@if($log->status==='failed' && !$log->sent_at && !$log->accepted_at)

<p>Retry only after reviewing the exact message, recipient and current event access. For reviewed team and Trials messages, use the existing Communications retry preview.</p>

@if($event->isTeam() || $event->isInterprovincialTrials())<a class="btn btn-outline-primary" href="{{ route('backend.event-communications.index',$event) }}">Open Communications</a>@endif

@endif

<h2 class="h5 mt-4">Attempt history</h2>

@foreach($history as $entry)

<div class="border rounded p-2 mb-2"><strong>Attempt {{ $entry->attempt_number }}  -  {{ $entry->status }}</strong>  -  {{ $entry->recorded_at->format('d M Y H:i:s') }}<p class="mb-0">Initiated by {{ $entry->actor_id ? 'User #'.$entry->actor_id : 'system / actor not recorded' }}</p>

@if(data_get($entry->snapshot,'error_message'))<p class="mb-0">{{ data_get($entry->snapshot,'error_message') }}</p>@endif

<details><summary>Saved attempt snapshot</summary><p>{{ data_get($entry->snapshot,'recipient_email') }}</p><div style="white-space:pre-wrap">{{ strip_tags((data_get($entry->snapshot,'payload.rendered_html') ?? data_get($entry->snapshot,'payload.rendered_text') ?? data_get($entry->snapshot,'payload.body') ?? data_get($entry->snapshot,'payload.message') ?? 'Message text unavailable')) }}</div></details></div>

@endforeach

{{ $history->links('pagination::bootstrap-5') }}

</div></div>

@endsection

