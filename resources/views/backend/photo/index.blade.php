@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')

@endsection

@section('page-script')

@endsection

@section('content')

<div class="container">


  <div class="card">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Event</th>
            <th>Total Uploaded</th>
            <th>View</th>

          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @foreach($events as $key => $event)
          <tr>
            <td>{{$event->id}}</td>
            <td>{{$event->name}}</td>
            <td>
              # fotos here
            </td>
            <td><a href="{{route('eventPhoto.show',$event->id)}}" class="badge bg-label-primary me-1">View</a></td>

          </tr>
          @endforeach
        </tbody>
      </table>
    </div>



  </div>
</div>


@endsection