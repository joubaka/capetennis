@extends('layouts.backend')
@section('title', 'Review clothing refund')
@section('content')
<div class="card clothing-refund-workspace"><div class="card-body">
  <h4>Review clothing refund — order #{{ $order->id }}</h4>
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <p>Original payer: <strong>{{ $order->user?->name }}</strong> · {{ $event->name }}</p>
  <div class="table-responsive"><table class="table clothing-refund-lines">
    <thead><tr><th>Item / size</th><th>Purchased / paid</th><th>Prior refunds or pending</th><th>Selected for this refund</th><th>Excluded from this refund</th><th>Refund amount</th></tr></thead>
    <tbody>@foreach($quote['rows'] as $line)<tr>
      <td data-label="Item / size">{{ $line['name'] }} · {{ $line['size'] }}</td><td data-label="Purchased / paid">{{ $line['purchased'] }} units · R{{ number_format($line['paid_cents'] / 100, 2) }}</td>
      <td data-label="Prior refunds / pending">{{ $line['used_quantity'] }} units · R{{ number_format($line['used_cents'] / 100, 2) }}</td>
      <td data-label="Selected for refund">@if($line['selected_cents'] > 0)@if(($validated['mode'] ?? '') === 'amount')Amount only; quantity unchanged @else {{ $line['selected_quantity'] }} units @endif @else Not selected @endif</td>
      <td data-label="Excluded from refund">{{ $line['remaining_quantity'] - $line['selected_quantity'] }} available units · R{{ number_format(($line['paid_cents'] - $line['used_cents'] - $line['selected_cents']) / 100, 2) }}</td>
      <td data-label="Refund amount">R{{ number_format($line['selected_cents'] / 100, 2) }}</td>
    </tr>@endforeach</tbody>
  </table></div>
  <div class="alert alert-info"><strong>{{ $quote['kind'] }}</strong>
    <p class="fs-5 mb-1">Refund amount: <strong>R{{ number_format($quote['total_cents'] / 100, 2) }}</strong></p>
    <p class="mb-1">Fee: R0.00 · Net to original payer: R{{ number_format($quote['total_cents'] / 100, 2) }} via {{ $validated['method'] === 'wallet' ? 'wallet credit' : 'PayFast' }}.</p>
    <p class="mb-0">R{{ number_format(($quote['remaining_cents'] - $quote['total_cents']) / 100, 2) }} remains refundable on this order after this refund.</p>
  </div>
  <p>Reason: {{ $validated['reason'] }}</p>
  @if($validated['late_refund_note'] ?? null)<p>Late refund note: {{ $validated['late_refund_note'] }}</p>@endif
  <form method="POST" action="{{ route('backend.clothing.refunds.store', [$event, $order]) }}">
    @csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}">
    @if($validated['method'] === 'payfast')
      @include('backend.clothing.partials.refund-bank-details', ['bankFieldPrefix' => 'refund'])
    @endif
    <button class="btn btn-danger" type="submit">Confirm refund of R{{ number_format($quote['total_cents'] / 100, 2) }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('backend.clothing.refunds.show', [$event, $order, 'sort' => $validated['return_sort'] ?? 'rank', 'search' => $validated['return_search'] ?? '', 'team' => $validated['return_team'] ?? '', 'all_events' => $validated['return_all_events'] ?? 0]) }}">Change selection</a>
  </form>
</div></div>
@include('backend.clothing.partials.refund-styles')
@endsection
