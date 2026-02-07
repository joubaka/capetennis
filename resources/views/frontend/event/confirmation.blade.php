@extends('layouts/layoutMaster')

@section('title', 'Confirmation')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bs-stepper/bs-stepper.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/rateyo/rateyo.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />

<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}} " />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/bs-stepper/bs-stepper.js')}}"></script>
<script src="{{asset('assets/vendor/libs/rateyo/rateyo.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>


@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/wizard-ex-checkout.css')}}" />
@endsection

@section('page-script')

<script src="{{asset('assets/js/wizard-ex-checkout.js')}}"></script>
<script src="{{asset('assets/js/forms-selects.js')}}"></script>

@endsection

@section('content')
<h4 class="fw-bold py-3 mb-4">
  <span class="text-muted fw-light">Confirmation</span> 
</h4>
<!-- Confirmation -->
<div id="checkout-confirmation" class="content">
  <div class="row mb-3">
    <div class="col-12 col-lg-8 offset-lg-2 text-center mb-3">
      <h4 class="mt-2">Thank You! </h4>
      <p>Your have registered for  <a href="{{route('events.show',$event->id)}}">{{$event->name}}</a>!</p>
     
     
    </div>
   
  </div>
<div class="text-center mt-4">
 <img src="{{asset('assets/img/pages/paid.png')}}" alt="paid">
</div>
<div class="text-center mt-4">
<h1><a href="{{route('home')}}">Return to Home Page</a></h1>
</div>
</div>
@include('_partials/_modals/modal-add-new-address')
@endsection