@extends('layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
  <div class="card">
    <div class="card-body">
      <h3 class="mb-2">{{ $draw->drawName }}</h3>
      <p class="text-muted">{{ $event->name }}</p>
      <div class="alert alert-info" role="status">
        No matches are available to view yet. Please check back for updates.
      </div>
      <a href="{{ route('events.show', $event) }}" class="btn btn-outline-secondary">Back to tournament</a>
    </div>
  </div>
@endsection
