@extends('layouts/layoutMaster')

@section('title', 'User Management - Crud App')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
@endsection




@section('page-script')
<script src="{{asset('js/laravel-user-management.js')}}"></script>
<script src="{{asset('assets/js/rank-show.js')}}"></script>
@endsection

@section('content')

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Players</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-success">to do</small>
                        </div>
                        <small>Total Registrations: {{$registrations}} </small>
                    </div>
                    <span class="badge bg-label-success rounded p-2">
                        <i class="ti ti-user-check ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">


            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Total Events</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-info">{{$events->count()}}</small>
                        </div>
                        <small>Total Events in series</small>
                    </div>
                    <span class="badge bg-label-info rounded p-2">
                        <i class="ti ti-user ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Upcoming Events</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-secondary">{{$upcoming_events > 0 ? $upcoming_events:'0'}}</small>
                        </div>
                        <small>Upcoming events</small>
                    </div>
                    <span class="badge bg-label-secondary rounded p-2">
                        <i class="ti ti-users ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Completed events</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-warning">{{$completed_events}}</small>
                        </div>
                        <small>Completed events</small>
                    </div>
                    <span class="badge bg-label-warning rounded p-2">
                        <i class="ti ti-user-circle ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Users List Table -->



<div class="col-12">
    <div class="card mb-4">
        <div class="card-header"> Rankings</div>
        <div class="card-body">
            <div class="row">
                <div class="col-6">
                    <div class="row">




                        <div class="col-12">



                            <div class="list-group" id="cats">
                                @foreach($series->categories as $category)

                                <a href="javascript:void(0);" data-eventcategory="{{$category->id}}" class="list-group-item list-group-item-action">{{$category->event->name}} - {{$category->category->name}}</a>
                                @endforeach

                            </div>
                        </div>


                    </div>
                </div>
                <div class="col-6">

                    @if($series->ranking_lists->count() > 0)
                    @foreach($series->ranking_lists as $key => $rl)


                    <div class="card">
                        <div class="card-header bg-label-primary">
                            <small class="">{{$rl->category->name}} - {{$rl->category->id}}</small>

                        </div>
                        <div class="demo-inline-spacing mt-3">
                            <div class="list-group sortable" id="test-{{$key}}" data-ranklist="{{$rl->id}}">
                                @foreach($rl->rank_cats as $rankCategory)
                                <a href="javascript:void(0);" data-eventcategory="{{$rankCategory->category_event_id}}" class="list-group-item list-group-item-action">{{$rankCategory->eventCategory->event->name}} - {{$rankCategory->eventCategory->category->name}} </a>
                                @endforeach


                            </div>
                        </div>
                    </div>

                    @endforeach
                    @else
                    <div class="col-12 col-md-3">
                        <div class="badg bg-label-danger">No Ranking list created</div>
                    </div>
                    <div class="btn btn-primary btn-sm mt-2" data-id="{{$series->id}}" id="addRankList">Create lists</div>
                    @endif

                </div>

            </div>


        </div>
    </div>
</div>
<div class="col-5">
    <div class="card mb-4">
        <div class="card-header"> Setup</div>
        <div class="card-body">
            <h5>Calculate</h5>


            <p> <a href="{{route('ranking.calculate',$series->id)}}" class="calculate btn btn-info" data-id="{{$series->id}}">calculate - {{$series->id}}</a></p>

        </div>
    </div>
</div>
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




                        <h5>{{$ranking_list->category->name}} {{$ranking_list->category->id}} </h5>
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
                                    <td> <a class="badge bg-label-primary" href="{{route('result.details', ['id' => $scores->player->id, 'series' => $series->id])}}"> {{$scores->player->name}} {{$scores->player->surname}} @if($scores->primarySchool == 1)<span class=" badge bg-label-warning">{{$scores->primarySchool == 1 ? ' (u/13) ':''}}</span> @endif</a> {{$scores->player->id}}</td>
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
