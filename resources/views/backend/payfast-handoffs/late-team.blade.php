@extends('layouts/layoutMaster')
@section('title', 'Late team payments')
@section('content')
<div class="container-xxl container-p-y">
  <a href="{{ route('admin.events.finances', $event) }}">Back to event finances</a>
  <h3 class="mt-3">{{ $event->name }}: late team payments</h3>
  <p>These payments were received after checkout closed. Registration stays unconfirmed until an administrator reviews the original receipt and current roster. Do not ask the payer to pay again.</p>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @forelse($orders as $order)
    <div class="card mb-3"><div class="card-body">
      <h5>{{ $order->player?->name }} {{ $order->player?->surname }}</h5>
      <p>{{ $order->team?->name }} · Order #{{ $order->id }} · Original total R{{ number_format($order->total_amount, 2) }}</p>
      <form method="POST" action="{{ route('backend.late-team-payments.preview', [$event, $order]) }}">
        @csrf
        <label class="form-label" for="reason-{{ $order->id }}">Reason for reconciliation</label>
        <textarea class="form-control mb-3" id="reason-{{ $order->id }}" name="reason" required minlength="10" maxlength="1000" rows="2">{{ old('reason') }}</textarea>
        <button class="btn btn-primary" style="min-height:44px" type="submit">Check payment and preview registration</button>
      </form>
    </div></div>
  @empty
    <p>No team payments awaiting reconciliation for this event.</p>
  @endforelse
  {{ $orders->links() }}
</div>
@endsection
