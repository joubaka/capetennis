@extends('layouts.backend')
@section('title', 'Practice camp planning')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <h1 class="h3">Practice camp planning</h1>
  <p>Prepare a private invitation list from existing regions, teams and rosters.</p>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @role('super-user')
  <div class="card mb-4"><div class="card-body">
    <h2 class="h5">Cavaliers Practice Camp 2027</h2>
    <p>Provisional dates: <strong>7–9 January 2027</strong>. Venue, fees, capacity and contacts still to be confirmed.</p>
    <form method="POST" action="{{ route('backend.practice-camps.store') }}">@csrf
      <button class="btn btn-primary">Create or open the private 2027 draft</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Registration stays closed. This does not publish the event or send invitations.</p>
  </div></div>
  @endrole
  @forelse($plans as $plan)
    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
      <div><h2 class="h5 mb-1">{{ $plan->event->name }}</h2><span class="badge bg-label-secondary">Private draft</span></div>
      <a class="btn btn-outline-primary" href="{{ route('backend.practice-camps.show', $plan) }}">Open camp planner</a>
    </div></div>
  @empty<p>No camp drafts yet.</p>@endforelse
  {{ $plans->links() }}
</div>
@endsection
