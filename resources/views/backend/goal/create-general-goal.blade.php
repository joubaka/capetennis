@extends('layouts/layoutMaster')

@section('title', 'Player Profile')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bs-stepper/bs-stepper.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/bs-stepper/bs-stepper.js')}}"></script>

@endsection

@section('page-script')
<script src="{{asset('assets/js/goal.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
@endsection

@section('content')

<div class="bs-stepper wizard-numbered mt-2">
    <div class="bs-stepper-header">
    <div class="step" data-target="#personal-info">
            <button type="button" class="step-trigger">
                <span class="bs-stepper-circle">1</span>
                <span class="bs-stepper-label">
                    <span class="bs-stepper-title">Goal details</span>
                    <span class="bs-stepper-subtitle">Add your Goal</span>
                </span>
            </button>
        </div>
     
        <div class="line">
            <i class="ti ti-chevron-right"></i>
        </div>
        <div class="step" data-target="#account-details">
            <button type="button" class="step-trigger">
                <span class="bs-stepper-circle">2</span>
                <span class="bs-stepper-label">
                    <span class="bs-stepper-title">Period</span>
                    <span class="bs-stepper-subtitle">Setup your goal dates</span>
                </span>
            </button>
        </div>
        <div class="line">
            <i class="ti ti-chevron-right"></i>
        </div>
        <div class="step" data-target="#social-links">
            <button type="button" class="step-trigger">
                <span class="bs-stepper-circle">3</span>
                <span class="bs-stepper-label">
                    <span class="bs-stepper-title">Summary</span>
                    <span class="bs-stepper-subtitle">Confirm Goal</span>
                </span>
            </button>
        </div>
    </div>

    <div class="bs-stepper-content">
        <form id="goalForm" onSubmit="return false">
        @csrf
            <!-- Account Details -->
            <div id="account-details" class="content">
                <div class="content-header mb-3">
                    <h6 class="mb-0">Goal Period</h6>
                    <small>Set your start and finish dates</small>
                </div>
                <div class="g-3 mt-2">
                    <div class="col-sm-3 col-12">

                        <label for="html5-date-input" class="form-label">Improve  {{$type->name}} goal by </label>
                        <div class="switches-stacked mt-4 mb-4">
                            <label class="switch switch-square">
                                <input name="goalDate" type="radio" value="{{$twoWeeks}}" class="switch-input dates" name="switches-square-stacked-radio" >
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                                <span class="switch-label">2 weeks from today - <span class="badge bg-label-primary">{{$twoWeeks}}</span></span> </span>
                            </label>

                            <label class="switch switch-square">
                                <input name="goalDate" type="radio" value="{{$endOfMonth}}" class="switch-input dates" name="switches-square-stacked-radio">
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                                <span class="switch-label">End of Month - <span class="badge bg-label-primary"> {{$endOfMonth}}</span></span>
                            </label>

                            <label class="switch switch-square">
                                <input name="goalDate" type="radio" value=" {{$endOfNextMonth}}" class="switch-input dates" name="switches-square-stacked-radio">
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                                <span class="switch-label">End of next month - <span class="badge bg-label-primary"> {{$endOfNextMonth}}</span></span>
                            </label>
                            <label class="switch switch-square">
                                <input name="goalDate" id="custom-date" value="customDate" type="radio" class="switch-input" name="switches-square-stacked-radio">
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                                <span class="switch-label">Select specific date</span>
                            </label>
                            <div class="mb-3 d-none" id="custom-date-select">

                                <label for="goalByDate">Please select custom date</label>
                                <input name="goalCustomDate" class="form-control" id="goalByDate" type="date" id="html5-date-input">

                            </div>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-between">
                        <button class="btn btn-label-secondary btn-prev" disabled> <i class="ti ti-arrow-left me-sm-1 me-0"></i>
                            <span class="align-middle d-sm-inline-block d-none">Previous</span>
                        </button>
                        <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1 me-0">Next</span> <i class="ti ti-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- Personal Info -->
            <div id="personal-info" class="content">
                <div class="row">
                    <div class="col-md-6 col-12 mb-md-0 mb-4">
                        <h5>Aspect to improve</h5>
                        <div class="card shadow-none bg-transparent border border-primary">

                            <ul style="min-height: 100px;" class="list-group list-group-flush" id="pending-tasks">

                            </ul>
                        </div>

                    </div>

                    <div class="col-md-6 col-12 mb-md-0 mb-4">
                        <h5>Aspects</h5>

                        <ul class="list-group list-group-flush" id="completed-tasks">
                            @foreach($technicals as $technical)
                            <li data-id="{{$technical->id}}" data-name="{{$technical->name}}" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                                <span class="btn btn-primary">{{$technical->name}}</span>

                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-12 d-flex justify-content-between">
                        <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1 me-0"></i>
                            <span class="align-middle d-sm-inline-block d-none">Previous</span>
                        </button>
                        <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1 me-0">Next</span> <i class="ti ti-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- Social Links -->
            <div id="social-links" class="content">
                <div class="content-header mb-3">
                    <h6 class="mb-0">Summary</h6>
                    <small>Confirm your Goal</small>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>{{$type->name}} Goal</h3>
                    </div>
                    <div class="card-body">
                    <h6>I want to reach my {{$type->name}} by:</h6>
                        <div id="stroke"></div>
                        <h6 class="m-2">by</h6>
                        <div class="badge bg-success" id="date">tt</div>
                    </div>
                </div>
                <div class="row g-3">

                    <div class="col-12 d-flex justify-content-between">
                        <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1 me-0"></i>
                            <span class="align-middle d-sm-inline-block d-none">Previous</span>
                        </button>
                        <button class="btn btn-success btn-submit" id="submitGoal">Submit</button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="player" value="{{$player->id}}">
      
        </form>
    </div>
</div>


@endsection