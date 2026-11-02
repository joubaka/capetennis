@extends('layouts.backend')

@section('title', 'Cape Tennis Assistant')

@section('content')
  @include('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Super-user assistant pilot'])
  <div class="card">
    <div class="card-body">
      <h2 class="h4">Test your tournament plan</h2>
      <p>Open <strong>Ask assistant</strong> to review scheduling requests for this event. A preview does not change fixtures, saved bookings or public schedules.</p>
      <p class="mb-0">Saving, draw generation and publication each require a reviewed confirmation. Access is restricted to the super-user during testing.</p>
    </div>
  </div>
@endsection
