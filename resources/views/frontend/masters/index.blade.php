@extends('layouts/contentNavbarLayout')

@section('title', 'Masters invitations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-4">Masters invitations</h4>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @forelse($invitations as $invitation)
    <div class="card mb-3">
      <div class="card-body">
        <h5>{{ $invitation->batch->event->name ?? 'Masters event' }}</h5>
        <p class="mb-1">{{ $invitation->categoryEvent->category->name ?? 'Age group' }}</p>
        <p class="text-muted">Ranking position {{ $invitation->ranking_position }} · {{ $invitation->total_points }} points</p>
        @if($invitation->status === 'invited')
          <form method="POST" action="{{ route('masters.invitations.accept', $invitation) }}" class="d-inline">@csrf<button class="btn btn-primary">Accept and pay</button></form>
          <form method="POST" action="{{ route('masters.invitations.decline', $invitation) }}" class="d-inline">@csrf<button class="btn btn-outline-secondary">I am unavailable</button></form>
        @elseif($invitation->status === 'accepted_pending_payment')
          <a class="btn btn-warning" href="{{ route('registration.checkout', $invitation->order_id) }}">Complete payment</a>
        @else
          <span class="badge bg-label-success">Confirmed</span>
        @endif
      </div>
    </div>
  @empty
    <div class="alert alert-info">You have no active Masters invitations.</div>
  @endforelse
</div>
@endsection
