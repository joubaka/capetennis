@extends('layouts/contentNavbarLayout')
@section('title', 'Platteland team invitation')
@section('content')
@php($event = $invitation->selectionImport?->event)
@php($responseDeadline = $invitation->effectiveResponseDeadline())
@php($paymentDeadline = $invitation->effectivePaymentDeadline())
<div class="container-xxl flex-grow-1 container-p-y"><div class="row justify-content-center"><div class="col-xl-8 col-lg-9"><div class="card border-primary shadow-sm">
  <div class="card-header text-center py-4"><span class="badge bg-label-primary mb-2">Regional team selection</span><h4 class="mb-1">Platteland team invitation</h4><p class="text-muted mb-0">{{ $event?->name }}</p></div>
  <div class="card-body p-4 p-lg-5"><h5>Hello {{ $invitation->player?->full_name }},</h5>
    @if($invitation->selectionImport?->email_message)<p>{!! nl2br(e($invitation->selectionImport->email_message)) !!}</p>@endif
    <p>You have been selected to represent <strong>{{ $invitation->region?->region_name }}</strong>.</p>
    <div class="alert alert-primary d-flex flex-column gap-1"><strong>{{ $invitation->team?->name }}</strong><span>Ranking position: {{ $invitation->ranking_position }}</span><span>Playing order: {{ $invitation->roster_rank }}</span></div>
    <div class="row g-2 mb-4">
      @if($event?->start_date)<div class="col-sm-6"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Event dates</small><strong>{{ $event->start_date->format('d M Y') }}{{ $event->end_date && !$event->end_date->equalTo($event->start_date) ? ' – '.$event->end_date->format('d M Y') : '' }}</strong></div></div>@endif
      <div class="col-sm-6"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Entry fee</small><strong>R{{ number_format((float) $event?->entryFee, 2) }}</strong></div></div>
      @if($responseDeadline)<div class="col-sm-6"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Respond by</small><strong>{{ $responseDeadline->format('d M Y H:i') }}</strong></div></div>@endif
      @if($paymentDeadline)<div class="col-sm-6"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Payment deadline</small><strong>{{ $paymentDeadline->format('d M Y H:i') }}</strong></div></div>@endif
    </div>
    @if($invitation->selectionImport?->event_information)<div class="alert alert-warning"><strong>Event information</strong><div class="mt-1">{!! nl2br(e($invitation->selectionImport->event_information)) !!}</div></div>@endif
    @if($regionAnnouncements->isNotEmpty())
      <div class="mb-4"><h5>{{ $invitation->region?->region_name }} announcements</h5>@foreach($regionAnnouncements as $announcement)<div class="border rounded p-3 mb-2"><strong>{{ $announcement->title }}</strong><div class="mt-1">{!! $announcement->message !!}</div><small class="text-muted">{{ $announcement->created_at->format('d M Y H:i') }}</small></div>@endforeach</div>
    @endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($invitation->status === \App\Models\TeamSelectionInvitation::INVITED)
      <p>Register and complete payment to play. Your team place is confirmed after payment has been verified.</p><div class="d-flex flex-wrap gap-2"><form method="POST" action="{{ route('team-selection.invitations.accept', $invitation) }}">@csrf<button class="btn btn-success btn-lg"><i class="ti ti-credit-card me-1"></i>Register and pay</button></form><button class="btn btn-outline-danger btn-lg" data-bs-toggle="modal" data-bs-target="#decline-team-invitation">I am unavailable</button></div>
    @elseif($invitation->status === \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)
      <div class="alert alert-warning"><strong>Your place is not confirmed yet.</strong> Complete payment before the deadline, or decline so the reserve can be invited.</div><div class="d-flex flex-wrap gap-2"><a class="btn btn-success btn-lg" href="{{ route('team.payment.payfast', [$invitation->team_id,$invitation->player_id,$invitation->event_id]) }}"><i class="ti ti-credit-card me-1"></i>Continue payment</a><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#decline-team-invitation">Decline invitation</button></div>
    @elseif($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)
      <div class="alert alert-success"><i class="ti ti-circle-check me-1"></i><strong>Registration and payment confirmed.</strong> Your team place is secured.</div>
      @if($canOrderClothing)<a class="btn btn-outline-primary" href="{{ route('team-selection.invitations.clothing', $invitation) }}"><i class="ti ti-shirt me-1"></i>Order optional clothing</a>@endif
      @if($clothingOrders->isNotEmpty())<div class="mt-3"><strong>Clothing orders</strong>@foreach($clothingOrders as $order)<div class="small text-muted">Order #{{ $order->id }} · R{{ number_format((float)$order->total, 2) }} · {{ $order->pay_status ? 'Paid' : 'Payment pending' }}</div>@endforeach</div>@endif
    @else<div class="alert alert-secondary mb-0">This invitation is {{ str_replace('_',' ',$invitation->status) }}.</div>@endif
  </div>
</div></div></div></div>
@if(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
<div class="modal fade" id="decline-team-invitation" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('team-selection.invitations.decline', $invitation) }}" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Decline invitation?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>The place will be released and the next reserve may be invited.</p><label class="form-label">Reason (optional)</label><textarea name="reason" maxlength="1000" class="form-control" rows="3"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep invitation</button><button class="btn btn-danger">Confirm decline</button></div></form></div></div>
@endif
@endsection
@section('page-script')
@if(request('action') === 'decline')<script>document.addEventListener('DOMContentLoaded',()=>{const el=document.getElementById('decline-team-invitation');if(el&&window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();});</script>@endif
@endsection
