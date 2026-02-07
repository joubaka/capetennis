@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Admin - Event Page')

{{-- =======================================
      VENDOR CSS
======================================= --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">

 {{-- FIX: SVG.js restored --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js"></script>
@endsection

@section('page-style')
  <link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}">
@endsection

{{-- =======================================
      VENDOR JS
======================================= --}}
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

{{-- =======================================
      PAGE JS
======================================= --}}
@section('page-script')
  <script src="{{asset('assets/js/draw-show.js')}}"></script>

  <script src="{{asset('assets/js/my-functions.js')}}"></script>
@endsection



{{-- =======================================
      PAGE CONTENT
======================================= --}}
@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="card-header event-header">
  <h3 class="text-center">Event: {{$event->name}}</h3>
</div>

<div class="row">

  {{-- LEFT NAV --}}
  <div class="col-12 col-sm-3">
    @include('backend.adminPage.admin_show.navbar.navbar')
  </div>

  {{-- RIGHT CONTENT --}}
  <div class="col-12 col-sm-9">

    <ul class="nav nav-pills mb-2">
      <li class="nav-item me-2">
        <button id="create-draw-button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#drawModal">
          Create Draw
        </button>
      </li>
    </ul>

    <div class="card">
      <div class="row">
        <div class="col-12">
          <div class="list-group m-2">

            @foreach($event->draws as $draw)
              @include('backend.draw._includes.draw_detail_index')
            @endforeach

          </div>
        </div>
      </div>
    </div>

  </div>

</div>



{{-- =======================================
      CREATE DRAW MODAL
======================================= --}}
<div class="modal fade" id="drawModal" tabindex="-1" aria-labelledby="drawModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Create Draw</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="create-draw-form">

        <div class="modal-body">

          <div class="mb-3">
            <ul class="list-group" id="pending-tasks">
              @foreach($event->region_in_events as $region)
                <li data-id="{{$region->pivot->id}}" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                  <span>{{$region->region_name}}</span>
                </li>
              @endforeach
            </ul>
          </div>

          <input type="hidden" name="event_id" value="{{$event->id}}">

        <div class="mt-4">
    <h5>Draw Format Type</h5>

    <select name="drawType" id="drawTypeSelect" class="form-select form-select-sm">

        <optgroup label="Team Formats">
            @foreach($teamDrawTypes as $drawType)
                <option value="{{ $drawType->id }}">
                    {{ $drawType->drawTypeName }}
                </option>
            @endforeach
        </optgroup>

        <optgroup label="Individual Formats">
            @foreach($individualDrawTypes as $drawType)
                <option value="{{ $drawType->id }}">
                    {{ $drawType->drawTypeName }}
                </option>
            @endforeach
        </optgroup>

    </select>
</div>


          <div class="row mt-3">
            <div class="col-md">
              @foreach($event->eventCategories as $eventCategory)
                <div class="form-check form-check-primary mt-2">
                  <input class="form-check-input" name="category[]" type="checkbox" value="{{$eventCategory->id}}">
                  <label class="form-check-label">{{$eventCategory->category->name}}</label>
                </div>
              @endforeach
            </div>
          </div>

          <div class="pt-4">
            <button type="button" id="create-fixtures-button" class="btn btn-primary me-sm-3">Create Fixtures</button>
            <button type="reset" class="btn btn-label-secondary">Cancel</button>
          </div>

        </div>

      </form>

    </div>
  </div>
</div>



<script>
  var venues = {!! $venues->toJson() !!};
</script>
<script>
    window.APP_URL = "{{ url('/') }}";

 
    window.storeVenueRoute = "{{ route('backend.draw.venues.store', ['draw' => $draw->id]) }}";

</script>



@endsection
