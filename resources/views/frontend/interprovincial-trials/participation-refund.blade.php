@extends('layouts/layoutMaster')
@section('title','Participation refund')
@section('content')
<div class="container py-4"><div class="card card-body mx-auto" style="max-width:760px"><h4>Participation refund — {{ $participation->player->full_name }}</h4><p>{{ $event->name }}</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<p>Original amount paid: R{{ number_format($participation->order->total_amount,2) }}. Existing event withdrawal fee of 10% applies. Withdraw before the regional participation payment deadline to qualify.</p>
@if($participation->order->refund_status==='completed')<div class="alert alert-success">Refund completed: R{{ number_format($participation->order->refund_net,2) }}.</div>
@elseif(!$participation->order->withdrawn_at)<p>Withdraw the participation before requesting a refund.</p>
@else
@if($participation->order->refund_status==='pending')<div class="alert alert-info">Refund pending: R{{ number_format($participation->order->refund_net,2) }}.</div>@endif
<form method="post" action="{{ route('interprovincial-trials.participation.request-refund',[$event,$participation]) }}">@csrf<label class="form-label">Refund method</label><select name="method" class="form-select mb-3"><option value="bank">Manual bank refund</option>@if($participation->order->payfast_paid && $participation->order->payfast_pf_payment_id)<option value="payfast">PayFast refund to original payment</option>@endif</select>
@foreach(['refund_account_name'=>'Account holder','refund_bank_name'=>'Bank','refund_account_number'=>'Account number','refund_branch_code'=>'Branch code'] as $field=>$label)<label class="form-label">{{ $label }}</label><input name="{{ $field }}" class="form-control mb-2" required value="{{ old($field,$participation->order->$field) }}">@endforeach
<label class="form-label">Account type</label><select name="refund_account_type" class="form-select mb-3"><option value="current">Current</option><option value="savings">Savings</option></select><button class="btn btn-primary">Request refund</button></form>
@endif
<a href="{{ route('events.show',$event) }}" class="mt-3">Back to event</a></div></div>
@endsection
