@extends('layouts.backend')
@section('title', 'Review event emails')
@section('content')
<h4>{{ $event->name }} — Review emails</h4>
<p>{{ count($batch->recipients) }} emails. Nothing is sent until you approve below.</p>
@if($batch->issues)<div class="alert alert-warning"><strong>Missing contacts</strong><ul class="mb-0">@foreach($batch->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>@endif
@foreach($batch->recipients as $recipient)<div class="card card-body mb-3"><p class="text-break"><strong>To:</strong> {{ $recipient['email'] }} — {{ $recipient['kind']==='manager' ? 'Team manager summary' : 'Player/parent' }}</p><h5>{{ $recipient['subject'] }}</h5><iframe title="Email preview for {{ $recipient['email'] }}" sandbox="" srcdoc="{{ $recipient['html'] }}" style="width:100%;height:420px;border:1px solid #ddd" loading="lazy"></iframe></div>@endforeach
<form method="post" action="{{ route('backend.event-communications.send',$event) }}">@csrf<input type="hidden" name="token" value="{{ $batch->token }}">
@if($batch->issues)<label class="d-block mb-3"><input class="form-check-input me-2" type="checkbox" name="acknowledge_missing" value="1" required>I reviewed the missing contacts. These recipients will not receive an email.</label>@endif
<label class="d-block mb-3"><input class="form-check-input me-2" type="checkbox" name="confirm_send" value="1" required>I approve these exact recipients and messages.</label><button class="btn btn-primary">Approve and queue {{ count($batch->recipients) }} emails</button> <a class="btn btn-outline-secondary" href="{{ route('backend.event-communications.index',$event) }}">Back to composer</a>
</form>
@endsection
