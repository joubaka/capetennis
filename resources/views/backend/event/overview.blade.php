@extends('layouts/layoutMaster')

@section('title', $event->name)



@section('content')
<div class="container-xl">
  
  @include('backend.event.partials.header', ['event' => $event])

  @if($event->isIndividual())
    @include('backend.event.individual.index')
  @elseif($event->isTeam())
    @include('backend.event.team.index')
  @elseif($event->isCamp())
    @include('backend.event.camp.index')
  @else
    <div class="alert alert-warning">Unknown event type</div>
  @endif

</div>

@endsection
