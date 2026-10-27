@extends('layouts/layoutMaster')
@section('title', 'Pay category difference')
@section('content')
<div class="container py-4" style="max-width:720px">
  <h1 class="h4">Pay category difference</h1>
  <p>Continue to PayFast to pay R{{ number_format((float) $adjustment->difference, 2) }}.</p>
  {!! $form !!}
  <button class="btn btn-primary" type="submit" form="payfastForm">Continue to PayFast</button>
  <a class="btn btn-link" href="{{ route('registrations.categoryAdjustment.show', $adjustment) }}">Back</a>
</div>
@endsection
