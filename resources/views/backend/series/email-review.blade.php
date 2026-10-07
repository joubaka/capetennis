@extends('layouts.backend')
@section('title','Review series emails')
@section('content')
<div data-mail-review><h1 class="h3">{{ $series->name }} - review series emails</h1>
@foreach(['success'=>'success','warning'=>'warning','error'=>'danger','info'=>'info'] as $flash=>$tone)@if(session($flash))<div class="alert alert-{{ $tone }}" role="status">{{ session($flash) }}</div>@endif @endforeach
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<p><strong>{{ $emailCount }} event messages</strong> to <strong>{{ $uniqueCount }} unique email addresses</strong> across {{ $batches->count() }} events.</p>
<div class="alert alert-info">One message is prepared per event. A recipient registered in multiple events receives a separate message for each event. Each preview below identifies its event. No emails are queued until you approve this entire intent.</div>
@foreach($batches as $batch)<section class="card card-body mb-4"><h2 class="h4">{{ $batch->event->name }}</h2><p>{{ count($batch->recipients) }} messages · Sender {{ $batch->options['from_name'] }} · Reply to {{ $batch->options['reply_to'] }}</p>
@if($batch->issues)<div class="alert alert-warning"><strong>Excluded missing contacts</strong><ul>@foreach($batch->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>@endif
@include('backend.partials.email-sample', ['mailRecipients' => $batch->recipients, 'mailSender' => $batch->options])
@if($batch->approved_at)<a href="{{ route('backend.event-communications.index',['event'=>$batch->event_id,'batch'=>$batch->id,'report_scope'=>'batch']) }}">Open this event's send report</a>@endif
</section>@endforeach
@if(!$batches->every(fn($batch)=>$batch->approved_at))<form method="post" action="{{ route('series.email.approve',$series) }}">@csrf<input type="hidden" name="intent" value="{{ $intent }}">
@if($batches->contains(fn($batch)=>!empty($batch->issues)))<label class="d-block mb-3"><input type="checkbox" name="acknowledge_missing" value="1" required> I acknowledge the excluded missing contacts for every event.</label>@endif
<label class="d-block mb-3"><input type="checkbox" name="confirmed" value="1" required> I reviewed these exact recipients, event messages and sender details and approve this intent.</label><button class="btn btn-primary">Approve {{ $emailCount }} event messages</button>
</form>@else<p>This intent was already approved. Use the event reports above to review actual queue and delivery outcomes.</p>@endif
<a class="btn btn-outline-secondary mt-3" href="{{ route('series.show',$series) }}">Return to series and start a new send intent</a>
</div>
@endsection
