@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/swiper/swiper.css')}}" />
@endsection

<!-- Page -->
@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/ui-carousel.css')}}" />
@endsection


@section('vendor-script')
<script src="{{asset('assets/vendor/libs/swiper/swiper.js')}}"></script>

@endsection

@section('page-script')
<script src="{{asset('assets/js/photo-details.js')}}"></script>
@endsection

@section('content')

<div class="container">
<div class="row">
   <div class="col-6 ">
        <div class="card d-flex justify-content-center">
            <img src="{{ asset('storage/photoFolder/'.$image->path) }}" class="w-100 shadow-1-strong rounded " alt="Boat on Calm Water" />

        
        </div>


    </div>
    <div class="col-6 ">
    <button id="buy-button" data-image="{{$image}}" class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasEnd" aria-controls="offcanvasEnd">Buy this photo</button>



    </div>

</div>
 
</div>

@include('frontend.photo._includes.buy-photo-modal')
@endsection