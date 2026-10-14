@extends('layouts/layoutMaster')
@section('title','My email issues')
@section('content')
@include('backend.event.mail-log.feedback')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div><h1 class="h3">My email issues</h1><p class="mb-0">Clear alerts you have reviewed. Email records and outcomes are retained; clearing does not resend emails.</p></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('backend.email-issues', $showAcknowledged ? [] : ['show' => 'all']) }}">{{ $showAcknowledged ? 'Show outstanding only' : 'Show acknowledged' }}</a>
    @if($clearToken)
    <form method="POST" action="{{ route('backend.email-issues.clear') }}">
      @csrf<input type="hidden" name="snapshot" value="{{ $clearToken }}">
      <button class="btn btn-outline-primary">Clear list</button>
    </form>
    @endif
  </div>
</div>
@if($clearToken)<p class="small text-muted">Clear list acknowledges your outstanding alerts across all pages. New alerts arriving afterward remain visible.</p>@endif
@forelse($visible as $item)<div class="card mb-3"><div class="card-body" style="overflow-wrap:anywhere"><h2 class="h6">{{ $item['event'] ? $item['event']->name : 'Your email attempt' }}</h2><p>{{ \Illuminate\Support\Str::headline($item['issue']->kind) }} · Attempt {{ $item['issue']->attempt_number }} - {{ $item['issue']->created_at->format('d M Y H:i') }} @if($item['log']) - {{ $item['log']->recipient_email }} @endif</p>@if($item['log'])<a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.show',[$item['event'],$item['log']]) }}">Review email record</a>@else<p>Private event details require current event-management access.</p>@endif@if(!$item['issue']->read_at)<form class="d-inline" method="post" action="{{ route('backend.event-mail-log.acknowledge',[$item['issue']->event_id,$item['issue']]) }}">@csrf<button class="btn btn-outline-secondary">Acknowledge</button></form>@else<span class="text-muted">Acknowledged</span>@endif</div></div>@empty<p>{{ $showAcknowledged ? 'No email issues.' : 'No email issues need attention.' }}</p>@endforelse
{{ $issues->links('pagination::bootstrap-5') }}
@endsection
