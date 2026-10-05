@extends('layouts/layoutMaster')
@section('title', 'Event email log')
@section('content')
@include('backend.event.mail-log.feedback')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h3">Email log</h1><p>{{ $event->name }}</p></div><a class="btn btn-outline-primary align-self-start" href="{{ route('backend.email-issues') }}">My email issues</a></div>
<p class="text-muted">Queued means waiting to send. Mail-server acceptance does not confirm inbox delivery. Older records may have incomplete evidence; emails with no proven event association are excluded.</p>
<h2 class="h6">Recipient outcomes</h2>
<div class="d-flex flex-wrap gap-3 mb-3" aria-label="Email outcomes">
@foreach(['total'=>'Total','queued'=>'Queued','sending'=>'Sending','server_accepted'=>'Server accepted','failed'=>'Failed','skipped'=>'Skipped','unverified'=>'Unverified','sandbox_accepted'=>'Test acceptance'] as $key=>$label)<span>{{ $label }}: <strong>{{ $summary[$key] }}</strong></span>@endforeach
</div>
@if(($copySummary['total'] ?? 0)>0)<p>Sender/admin copies: <strong>{{ $copySummary['total'] }}</strong> · Queued: {{ $copySummary['queued'] }} · Server accepted: {{ $copySummary['server_accepted'] }} · Failed: {{ $copySummary['failed'] }} · Skipped: {{ $copySummary['skipped'] }} · Unverified: {{ $copySummary['unverified'] }}</p>@endif
@if($summary['failed'] || $summary['skipped'] || $summary['unverified'])<div class="alert alert-warning" style="color:#513c06;background:#fff3cd;border-color:#ffe69c" role="status">Some emails need attention. Open a record to see its outcome and available evidence.</div>@endif
<form method="get" class="row g-2 mb-3">@if(!empty($filters['campaign']))<input type="hidden" name="campaign" value="{{ $filters['campaign'] }}">@endif<div class="col-12 col-md-6"><label class="form-label" for="mail-search">Subject or recipient</label><input id="mail-search" class="form-control" name="search" maxlength="200" value="{{ $filters['search'] ?? '' }}"></div><div class="col-12 col-md-4"><label class="form-label" for="mail-status">Status</label><select id="mail-status" name="status" class="form-select"><option value="">All outcomes</option>@foreach(\App\Services\SuperAdminMailHistory::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ \Illuminate\Support\Str::headline($status) }}</option>@endforeach</select></div><div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary">Filter</button></div></form>
<div class="row g-3">
@forelse($logs as $log)
<div class="col-12 col-lg-6"><article class="card h-100"><div class="card-body" style="overflow-wrap:anywhere"><h2 class="h6"><a href="{{ route('backend.event-mail-log.show', [$event,$log]) }}">{{ \App\Services\EventMailLogService::subject($log) }}</a></h2><p class="mb-1">{{ $log->recipient_name }} {{ $log->recipient_email }}</p><p class="mb-1"><strong>{{ $log->delivery_status_label }}</strong> · {{ $log->created_at->format('d M Y H:i') }} SAST</p><small>{{ \App\Services\SuperAdminMailHistory::typeLabel($log->mail_type) }} · {{ data_get($log->payload,'recipient_kind','Recipient') }}</small>@if($reason=\App\Services\EventMailLogService::explanation($log))<p class="text-body mb-0 mt-2">{{ $reason }}</p>@endif</div></article></div>
@empty<div class="col-12"><p>No email records match this event and your permitted audience.</p></div>@endforelse
</div><div class="mt-3">{{ $logs->links() }}</div>
@endsection
