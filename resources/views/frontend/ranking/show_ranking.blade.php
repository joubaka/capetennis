@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>

@endsection

@section('page-script')


@endsection

@section('content')

<div class="col-12">
    <div class="card mb-4">
        <div class="card-header">
            <h5>Rankings</h5>
        </div>
        <div class="row">
            @foreach($series->ranking_lists as $ranking_list)
            <div class=" col-sm-12 col-lg-6">
                <div class="card">
                    <div class="card-body">




                        <h5>{{$ranking_list->category->name}} </h5>
                        <table class="table table-responsive ">
                            <thead>
                                <th>Rank</th>
                                <th>Name</th>
                                <th># of events</th>
                                <th>Points</th>
                            </thead>
                            <tbody>
                                @foreach($ranking_list->ranking_scores as $key => $scores)
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td> <a class="badge bg-label-primary" href="{{route('result.details', ['id' => $scores->player->id, 'series' => $series->id])}}"> {{$scores->player->name}} {{$scores->player->surname}} @if($scores->primarySchool == 1)<span class=" badge bg-label-warning">{{$scores->primarySchool == 1 ? ' (u/13) ':''}}</span> @endif</a></td>
                                    <td> {{$scores->num_events}}</td>
                                    <td> {{$scores->total_points}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>



                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>


@endsection
