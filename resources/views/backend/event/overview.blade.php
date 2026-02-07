@extends('layouts/layoutMaster')

@section('title', $event->name)



@section('content')
<div class="container-xl">
  
  @include('backend.event.partials.header', ['event' => $event])

  @if($event->isIndividual())
    @include('backend.event.partials.individual')
  @elseif($event->isTeam())
    @include('backend.event.partials.team')
  @elseif($event->isCamp())
    @include('backend.event.partials.camp')
  @else
    <div class="alert alert-warning">Unknown event type</div>
  @endif

</div>

@endsection
