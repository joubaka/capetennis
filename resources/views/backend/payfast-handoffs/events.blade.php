@extends('layouts/layoutMaster')
@section('title', 'PayFast checkout recovery')
@section('content')
<div class="container-xxl container-p-y">
  <h3>PayFast checkout recovery</h3>
  <p>Select an event to review registration checkouts awaiting a payment result.</p>
  <div class="list-group mb-3">
    @forelse($events as $event)
      <a class="list-group-item list-group-item-action py-3" href="{{ route('backend.payfast-handoffs.index', $event) }}">{{ $event->name }} <span class="text-muted">#{{ $event->id }}</span></a>
    @empty
      <p>No unresolved registration checkouts found.</p>
    @endforelse
  </div>
  {{ $events->links() }}
</div>
@endsection
