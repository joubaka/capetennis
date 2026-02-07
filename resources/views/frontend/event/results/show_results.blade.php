@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

<!-- Page -->
@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-profile.css')}}" />
@endsection


@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/pickr/pickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/pages-profile.js')}}"></script>
<script src="{{asset('assets/js/forms-editors.js')}}"></script>

@endsection

@section('content')

<div class="row">

    @if($event->results_published == 1)

    <div class="col-12">

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Positions in {{$event->name}} </h3>


            </div>
            <!-- /.box-header -->
            <div class="box-body">


                <a href="{{ route('events.show',$event->id) }}" class=" btn btn-danger m-3">Back to event</a>

                <div class="row">
                    @if($event->series)

                    @if($event->series->rankType->type == 'position')
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->positions as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>




                    @endforeach

                    @elseif($event->series->rankType->type == 'overberg')
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->positions as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    @endforeach
                    @else
                    @foreach($event->eventCategories as $key=> $eventCategory)
                   
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->points as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}} - {{$entry->position}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>




                    @endforeach
                    @endif

                    @else
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->positions as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>




                    @endforeach

                    @endif
                </div>



            </div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->
    @else
    @can('admin')
    <h4>Admin mode - not Published</h4>
    <div class="col-12">

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Positions in {{$event->name}} </h3>


            </div>
            <!-- /.box-header -->
            <div class="box-body">


                <a href="{{ route('events.show',$event->id) }}" class=" btn btn-danger m-3">Back to event</a>

                <div class="row">
                    @if($event->series)

                    @if($event->series->rank_type == 'position')
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->positions as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    @endforeach
                    @elseif($event->series->rank_type == 'overberg')
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->positions as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    @endforeach
                    @else
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->points as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}} - {{$entry->position}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    @endforeach
                    @endif

                    @else
                    @foreach($event->eventCategories as $key=> $eventCategory)
                    <div class="col-xl-3 col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="mb-0"> {{$eventCategory->category->name}} @can('admin') {{$eventCategory->id}}</h5>@endcan

                                </div>

                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach($eventCategory->positions as $rank => $entry)

                                    <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
                                        <div class="badge bg-label-success rounded p-2">{{($rank+1)}}</div>
                                        <div class="d-flex justify-content-between w-100 flex-wrap">
                                            <h6 class="mb-0 ms-3">{{$entry->player->name}} {{$entry->player->surname}}</h6>

                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    @endforeach

                    @endif
                </div>



            </div>
        </div>
        <!-- /.box-body -->
    </div>
    @endcan

    Not published
    @endif

</div>

@endsection