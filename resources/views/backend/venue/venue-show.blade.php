@php
$configData = Helper::appClasses();
@endphp

@extends('layouts.backend')

@section('title', 'Admin - Event Page')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')


<script src="{{asset('assets/js/venues.js')}}"></script>

@endsection



@section('content')

<div class="card-header event-header">
    <h3>{{$event->name}}</h3>
    <p class="text-muted">Venue allocation · {{ $draw->drawName }}</p>
</div>
<div class="row">

    <div class="col-12 col-lg-3">
        @include('backend.adminPage.admin_show.navbar.navbar')
    </div>

    <div class="col-12 col-lg-9 venue-allocation-admin">

        <div class="card mb-3"><div class="card-body">
            <h5>Current venues for {{ $draw->drawName }}</h5>
            <div class="d-flex flex-wrap gap-2">@forelse($draw->venues as $assignedVenue)<span class="badge bg-label-primary">{{ $assignedVenue->name }}</span>@empty<p class="text-muted mb-0">No venues assigned to this draw.</p>@endforelse</div>
            <p class="small text-muted mt-2 mb-0">These are the draw's general venue assignments. Round-specific venues and match times are managed in scheduling.</p>
        </div></div>
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="card">
            <div class="row">


                <div class="col-12">
                <form id="venueForm" action="{{ route('save.draw.venues') }}" method="POST">
                    @csrf
                    <!-- Multiple Select Dropdown -->
                    <div class="mb-3 m-3">
                        <label id="venues-label" for="venues" class="form-label">Venues for this draw</label>
                        <p id="venue-scope" class="small text-muted">Save replaces the general venue list for {{ $draw->drawName }} only. Clear all selections to remove its general assignments.</p>
                        <select id="venues" name="venues[]" class="form-control" multiple="multiple" aria-describedby="venue-scope" style="width: 100%;">

                            @foreach($venues as $venue)
                            <option value="{{$venue->id}}" @selected(in_array($venue->id, old('venues', $selectedVenues)))>{{$venue->name}}</option>
                            @endforeach
                            <!-- Add more options as needed -->
                        </select>
                        <input type="hidden" name="draw" value="{{$draw->id}}">
                    </div>

                    <button type="submit" id="apply-venue-button" class="m-3 btn btn-primary waves-effect waves-light" @disabled(!auth()->user()->can('fixture.update', $draw))>Save venues for this draw</button>
                    @if(!auth()->user()->can('fixture.update', $draw))<p class="mx-3 text-warning">Venue changes are unavailable here. The draw may be locked, published, or outside your management permissions.</p>@endif
                </form>








                </div>
            </div>


        </div>
    </div>

</div>
<style>
.venue-allocation-admin :is(.btn,select) { min-height:44px !important; }
.venue-allocation-admin .select2-selection--multiple { min-height:44px !important; }
.venue-allocation-admin .select2-container { max-width:100%; }
.venue-allocation-admin .badge { white-space:normal; overflow-wrap:anywhere; }
</style>


<script>
    var venues = {!! $venues->toJson() !!};

</script>

@endsection
