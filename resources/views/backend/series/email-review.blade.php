@extends('layouts.backend')
@section('title','Review series email')
@section('content')
<div data-mail-review><h1 class="h3">{{ $series->name }} - review series email</h1>
@foreach(['success'=>'success','warning'=>'warning','error'=>'danger','info'=>'info'] as $flash=>$tone)@if(session($flash))<div class="alert alert-{{ $tone }}" role="status">{{ session($flash) }}</div>@endif @endforeach
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<p><strong>{{ $emailCount }} emails</strong> to <strong>{{ $uniqueCount }} unique email addresses</strong>.</p>
<div class="alert alert-info">Each email address receives one message with the same subject and content, even when shared by players or registered in multiple events. Additional CC contacts receive one separate copy. Recipient addresses stay private. No emails are queued until you approve this review.</div>
<section class="card card-body mb-4" data-series-combined-review>
<h2 class="h4">All series recipients</h2>
@if($issues->isNotEmpty())<div class="alert alert-warning"><strong>Excluded missing contacts</strong><ul>@foreach($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>@endif
@if($cc = $batches->first()->options['cc'] ?? [])<p class="text-break"><strong>CC / additional copies:</strong> {{ implode(', ', $cc) }}<br><small>Addresses already in the player list receive only one copy.</small></p>@endif
@include('backend.partials.email-sample', ['mailRecipients' => $recipients, 'mailSender' => $batches->first()->options, 'mailSampleHeading' => 'Message for everyone'])
</section>
@if(!$batches->every(fn($batch)=>$batch->approved_at))<form method="post" action="{{ route('series.email.approve',$series) }}">@csrf<input type="hidden" name="intent" value="{{ $intent }}">
@if($issues->isNotEmpty())<label class="d-block mb-3"><input type="checkbox" name="acknowledge_missing" value="1" required> I acknowledge the excluded missing contacts.</label>@endif
<label class="d-block mb-3"><input type="checkbox" name="confirmed" value="1" required> I reviewed this recipient list, message and sender details and approve sending.</label><button class="btn btn-primary">Approve {{ $emailCount }} emails</button>
</form>@else<p>This announcement was already approved. No additional emails will be queued by approving it again.</p>
<details class="mb-3"><summary>Delivery reports</summary><ul>@foreach($batches as $batch)@if(count($batch->recipients))<li><a href="{{ route('backend.event-communications.index',['event'=>$batch->event_id,'batch'=>$batch->id,'report_scope'=>'batch']) }}">{{ $batch->event->name }} send report</a></li>@endif @endforeach</ul></details>
@endif
<a class="btn btn-outline-secondary mt-3" href="{{ route('series.show',$series) }}">Return to series and start a new announcement</a>
</div>
@endsection
