@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/createSchedule.js')}}"></script>
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Create Schedule</h5>
  </div>
  <div class="row">
  <div class="col-md-6 col-12 mb-md-0 mb-4">
    <h5>Pending Tasks</h5>

    <ul class="list-group list-group-flush" id="pending-tasks">
        @foreach($fixtures as $fixture)
      <li data-fixtureid="{{$fixture->id}}" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center ">
        <span>{{$fixture->bracket->name}}-{{$fixture->match_nr}} {{$fixture->registrations1 ? $fixture->registrations1->players[0]->full_name:''}} vs {{$fixture->registrations2 ? $fixture->registrations2->players[0]->full_name:'Bye/not scheduled'}}</span>
       
      </li>
@endforeach
    </ul>
  </div>

  <div class="col-md-6 col-12 mb-md-0 mb-4">
    <h5>{{$venue->name}} - {{$numcourts}} courts</h5>
    @foreach($slots as $key => $slot)
    <div class="card m-2 ">
        <div class="card-header"><h5>{{$slot}} </h5></div>
        <div class="card-body shadow-none bg-transparent border border-primary"> <ul data-venue='{{$venue->id}}' data-date='{{$date}}' data-slot='{{$slot}}' class="  list-group list-group-flush slots " id="slots-{{$key}}">

     
 
    </ul></div>

   

    </div>

    @endforeach
  </div>
</div>
</div>

@endsection
