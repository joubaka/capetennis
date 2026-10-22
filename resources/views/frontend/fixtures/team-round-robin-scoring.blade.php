@extends('layouts/layoutMaster')
@section('title', 'Team round-robin scoring')
@section('content')
<div class="container-xxl py-4">
  <h1 class="h4 mb-3">Team round-robin scoring</h1>
  @include('backend.team-draw.partials.round-robin-matrix')
  <a class="btn btn-outline-primary" href="{{ route('frontend.fixtures.enter-scores', ['draw' => $draw->id, 'view' => 'matches']) }}">View all matches</a>
</div>
@endsection
