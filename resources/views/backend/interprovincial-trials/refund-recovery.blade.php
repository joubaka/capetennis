@extends('layouts.backend')
@section('title', $event->name.' — PayFast refund recovery')
@section('content')
<div class="container-xl py-4"><h1>PayFast refund recovery</h1><a href="{{ route('backend.interprovincial-trials.programme.index',$event) }}">Regional programme</a>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<p>These attempts already contacted PayFast. Recovery completes local records and never submits another provider refund. Verify provider history before reconciling an uncertain outcome.</p>
@foreach($attempts as $attempt)
@php($participation=\App\Models\TrialParticipation::where('order_id',$attempt->order_id)->first())
<div class="card mb-3"><div class="card-body"><h2 class="h5">{{ $attempt->order?->player?->full_name }} — {{ $attempt->status }}</h2><p>Order {{ $attempt->order_id }} · Original PayFast payment {{ $attempt->pf_payment_id }} · Exact refund R {{ number_format($attempt->amount,2) }} · Local status {{ $attempt->order?->refund_status }}</p>
@if($participation && $attempt->order?->refund_status==='pending')
<form method="post" action="{{ route('backend.interprovincial-trials.refund-recovery.recover',[$event,$participation]) }}">@csrf
@if($attempt->status==='confirmed')<input type="hidden" name="mode" value="retry"><button class="btn btn-primary">Retry confirmed refund locally</button>
@else<input type="hidden" name="mode" value="reconcile"><label>Original PayFast payment ID<input class="form-control" name="pf_payment_id" required maxlength="120"></label><label>Exact externally refunded amount<input class="form-control" name="amount" type="number" step="0.01" min="0.01" required></label><label>Provider refund reference<input class="form-control" name="reference" required minlength="3" maxlength="120"></label><label>Provider confirmation time<input class="form-control" name="confirmed_at" type="datetime-local" required></label><label>Evidence and reason<textarea class="form-control" name="reason" required minlength="10" maxlength="1000"></textarea></label><label class="d-block"><input type="checkbox" name="externally_confirmed" value="1" required> I verified that PayFast completed this exact refund.</label><button class="btn btn-warning mt-2">Record provider evidence and complete locally</button>@endif
</form>@endif</div></div>@endforeach {{ $attempts->links() }}</div>
@endsection
