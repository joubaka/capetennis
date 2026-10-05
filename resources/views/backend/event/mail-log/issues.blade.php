@extends('layouts/layoutMaster')
@section('title','My email issues')
@section('content')
@include('backend.event.mail-log.feedback')
<h1 class="h3">My email issues</h1><p>Persistent alerts for your event emails. Acknowledging an alert does not resend or change the email outcome.</p>
@forelse($visible as $item)<div class="card mb-3"><div class="card-body" style="overflow-wrap:anywhere"><h2 class="h6">{{ $item['event'] ? $item['event']->name : 'Your email attempt' }}</h2><p>{{ \Illuminate\Support\Str::headline($item['issue']->kind) }} · Attempt {{ $item['issue']->attempt_number }} - {{ $item['issue']->created_at->format('d M Y H:i') }} @if($item['log']) - {{ $item['log']->recipient_email }} @endif</p>@if($item['log'])<a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.show',[$item['event'],$item['log']]) }}">Review email record</a>@else<p>Private event details require current event-management access.</p>@endif@if(!$item['issue']->read_at)<form class="d-inline" method="post" action="{{ route('backend.event-mail-log.acknowledge',[$item['issue']->event_id,$item['issue']]) }}">@csrf<button class="btn btn-outline-secondary">Acknowledge</button></form>@else<span class="text-muted">Acknowledged</span>@endif</div></div>@empty<p>No email issues.</p>@endforelse
{{ $issues->links('pagination::bootstrap-5') }}
@endsection
