@extends('layouts/layoutMaster')

@section('title', 'Checkout')

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
<script src="{{asset('assets/js/select2-search-addon.js')}}"></script>

@endsection

@section('content')


<!-- Address right -->

<div class="container-xxl flex-grow-1 container-p-y">
    <div><h3>Please select payment option</h3></div>
    <div class="row">
        <div class="col-xl-6">
            @if(Auth::user()->balance > 0)
            <div class="border rounded p-4">

                <div class="card">
                    <div class="card-header">
                        <form action="{{route('registration.wallet.pay')}}" method="POST">
                            @csrf


                            <input type="hidden" id="amount" name="amount_gross" value="{{$request->amount}}">
                            @php
                            $payfastFee = (($request->amount *3.2)/100) + 2;
                            $nett = $request->amount -$payfastFee;

                            @endphp
                            <input type="hidden" id="amount_net" name="amount_net" value="{{$nett}}">
                            <input type="hidden" id="amount_fee" name="amount_fee" value="{{$payfastFee}}">
                            <input type="hidden" id="amount" name="amount" value="{{$request->amount}}">
                            <input type="hidden" id="item_name" name="item_name" value="{{$request->item_name}}">

                            <!--  Category  -->
                            <input type="hidden" name="custom_int1" value="{{$request->custom_int1}}">
                            <input type="hidden" name="custom_str1" value="{{$request->custom_str1}}">

                            <!-- Player -->
                            <input type="hidden" name="custom_int2" value="{{$request->custom_int2}}">
                            <input type="hidden" name="custom_str2" value="{{$request->custom_str2}}">

                            <!-- Event -->
                            <input type="hidden" name="custom_int3" value="{{$request->custom_int3}}">
                            <input type="hidden" name="custom_str3" value="{{$request->custom_str3}}">

                            <!--  User -->
                            <input type="hidden" name="custom_int4" value="{{$request->custom_int4}}">
                            <input type="hidden" name="custom_str4" value="{{$request->custom_str4}}">

                            <!--  order -->
                            <input type="hidden" name="custom_int5" value="{{$request->custom_int5}}">
                            <input type="hidden" name="custom_str5" value="Order">

                            <button type="submit" class="btn btn-primary text-white btn-lg">Pay with Cape Tennis Wallet </button>
                        </form>
                    </div>
                    <div class="card-body">Wallet Balance: R {{Auth::user()->wallet->balance}}</div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-xl-6">
            <div class="border rounded p-4">
                <form action="{{$payfast->url}}" method="post">
                    <input type="hidden" name="merchant_id" value="{{$payfast->id}}">
                    <input type="hidden" name="merchant_key" value="{{$payfast->key}}">

                    <input type="hidden" name="return_url" value="{{$request->return_url}}">
                    <input type="hidden" name="cancel_url" value="{{$request->cancel_url}}">
                    <input type="hidden" name="notify_url" value="{{$request->notify_url}}">



                    <input type="hidden" id="amount" name="amount" value="{{$request->amount}}">
                    <input type="hidden" id="item_name" name="item_name" value="{{$request->item_name}}">

                    <!--  Category  -->
                    <input type="hidden" name="custom_int1" value="{{$request->custom_int1}}">
                    <input type="hidden" name="custom_str1" value="{{$request->custom_str1}}">

                    <!-- Player -->
                    <input type="hidden" name="custom_int2" value="{{$request->custom_int2}}">
                    <input type="hidden" name="custom_str2" value="{{$request->custom_str2}}">

                    <!-- Event -->
                    <input type="hidden" name="custom_int3" value="{{$request->custom_int3}}">
                    <input type="hidden" name="custom_str3" value="{{$request->custom_str3}}">

                    <!--  User -->
                    <input type="hidden" name="custom_int4" value="{{$request->custom_int4}}">
                    <input type="hidden" name="custom_str4" value="{{$request->custom_str4}}">

                    <!--  order -->
                    <input type="hidden" name="custom_int5" value="{{$request->custom_int5}}">
                    <input type="hidden" name="custom_str5" value="Order">

                    <button class="btn btn-danger btn-lg">Pay now with Payfast</button>

                </form>




            </div>
        </div>



    </div>

    <a href="{{ url()->previous() }}" class="btn btn-warning mt-5">Back</a>
</div>





@endsection