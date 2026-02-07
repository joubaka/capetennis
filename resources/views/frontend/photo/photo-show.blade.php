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
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">{{$event->name}}</div>
                <div class="card-body">
                    <!-- Gallery -->
                    <div class="row">
                        @foreach($photos as $image)

                        <div class="col-lg-3 col-md-12 mb-4 mb-lg-0">
                            <a href="{{route('frontPhoto.show',$image->id)}}">
                                <img src="{{ asset('storage/photoFolder/'.$image->path) }}" class="w-100 shadow-1-strong rounded mb-4" alt="Boat on Calm Water" />
                            </a>

                        </div>


                        @endforeach

                    </div>
                    <!-- Gallery -->


                </div>
            </div>
        </div>


    </div>

</div>


@endsection