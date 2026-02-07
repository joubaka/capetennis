@extends('layouts/layoutMaster')

@section('title', 'Ranking Details')

@section('vendor-style')

@endsection

@section('vendor-script')

@endsection

@section('page-script')


@endsection

@section('content')

<div class="card mb-4">
    <!-- Notifications -->
    <h5 class="card-header pb-1">{{$player->name}} {{$player->surname}}</h5>
    <div class="card-body">
        <span>Results for series</span>
    </div>
    <div class="table-responsive text-nowrap">
        <table class="table table-striped border-top">
            <thead>
                <tr>
                    <th class="text-nowrap">ID</th>
                    <th class="text-nowrap text-center">Event</th>
                    <th class="text-nowrap text-center">Score</th>

                </tr>
            </thead>
            <tbody>
                @foreach($results as $key => $position)
                <tr>
                    <td class="text-nowrap">{{$position->id}}</td>
                    <td>
                        <div class="form-check d-flex justify-content-center">
                            {{$position->category_event->event->name}}
                        </div>
                    </td>
                    <td>
                        <div class="form-check d-flex justify-content-center">
                            @if($series->rank_type == 'participation')
                            {{$position->round_robin_score}} points
                            @else
                           <span class="badge bg-label-success"> {{$position->position}}</span>  -   {{$position->point->score}} points 
                            @endif

                        </div>
                    </td>
                    <td>
                        <div class="form-check d-flex justify-content-center">

                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- /Notifications -->
</div>

@endsection