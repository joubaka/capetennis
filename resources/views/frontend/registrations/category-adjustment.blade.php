@extends('layouts/layoutMaster')
@section('title', 'Change category')
@section('content')
<div class="container py-4" style="max-width:720px">
  <div class="card"><div class="card-body">
    <h1 class="h4">Change category</h1>
    <p>{{ $adjustment->event->name }}</p>
    <p>{{ $adjustment->sourceCategory->category?->name }} → {{ $adjustment->targetCategory->category?->name }}</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($adjustment->applied_at)
      <div class="alert alert-success">Your category change is complete.</div>
      @if((float) $adjustment->wallet_credit > 0)<p>R{{ number_format((float) $adjustment->wallet_credit, 2) }} credited to your wallet.</p>@endif
    @elseif($adjustment->status === 'pending')
      <p>Pay <strong>R{{ number_format((float) $adjustment->difference, 2) }}</strong> through PayFast to complete this change.</p>
      <p>Your entry stays in its current category until payment is verified. This checkout expires at {{ $adjustment->expires_at->format('d M Y H:i:s') }}.</p>
      <div class="d-flex flex-wrap gap-2">
        @unless($adjustment->payfast_handed_off_at)
        <form method="POST" action="{{ route('registrations.categoryAdjustment.pay', $adjustment) }}">@csrf<button class="btn btn-primary">Pay difference with PayFast</button></form>
        @endunless
        <form method="POST" action="{{ route('registrations.categoryAdjustment.cancel', $adjustment) }}">@csrf<button class="btn btn-outline-secondary">Cancel change</button></form>
      </div>
      <p class="small text-muted mt-3">Already paid? Confirmation may take a moment. Refresh this page to check.</p>
      @if($adjustment->payfast_handed_off_at)<p class="small">This attempt has already been sent to PayFast. Wait for confirmation, or cancel it before starting another attempt.</p>@endif
    @elseif($adjustment->status === 'reconciliation_required')
      <div class="alert alert-warning">Your payment was received, but the category change could not be completed. Your entry has not moved. Please contact the organiser and quote category payment #{{ $adjustment->id }}.</div>
    @else
      <div class="alert alert-info">This checkout is {{ $adjustment->status }}. It did not change your category.</div>
    @endif
    <a class="btn btn-link mt-3" href="{{ route('events.show', $adjustment->event_id) }}">Return to event</a>
  </div></div>
</div>
@endsection
