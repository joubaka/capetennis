@extends('layouts.backend')
@section('title','Review camp invitations')
@section('content')
<div data-camp-workflow class="container-xxl container-p-y" style="max-width:1100px;overflow-wrap:anywhere"><h1 class="h3">Review camp invitations</h1><p>{{ count($review->snapshot['rows']) }} messages. This review expires {{ $review->expires_at->format('j M Y H:i') }}.</p>
<p>From: {{ $review->snapshot['envelope']['from_name'] }} &lt;{{ $review->snapshot['envelope']['from_address'] }}&gt;<br>Reply to: {{ $review->snapshot['envelope']['reply_to'] }}</p>
@foreach($review->snapshot['rows'] as $row)<div class="card mb-3"><div class="card-body"><h2 class="h5">{{ $row['name'] }} · {{ $row['email'] }}</h2><p>{{ $row['notification_only']?'Notification only — no private decision or entry link':'Primary actionable invitation' }}</p><p>Subject: {{ $row['subject'] }}</p><iframe title="Email preview for {{ $row['name'] }}" sandbox srcdoc="{{ $row['html'] }}" style="width:100%;min-height:350px;border:1px solid #ddd"></iframe></div></div>@endforeach
<form method="POST" action="{{ route('backend.camps.mail-queue',[$plan,$review]) }}">@csrf<label><input type="checkbox" name="confirmed" value="1" required> I reviewed these exact recipients and messages.</label><br><button class="btn btn-primary mt-3">Queue reviewed invitations</button> <a class="btn btn-outline-secondary mt-3" href="{{ route('backend.camps.show',$plan) }}">Back without sending</a></form></div>
@endsection

@section('page-script')
@include('backend.practice-camps.feedback')
@endsection
