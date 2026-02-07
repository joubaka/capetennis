@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Admin - Event Page')

{{-- ================================
      VENDOR CSS
================================ --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}">
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">
@endsection

@section('page-style')
  <link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}">
@endsection

{{-- ================================
      VENDOR JS
================================ --}}
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js"></script>
@endsection

{{-- ================================
      PAGE JS
================================ --}}
@section('page-script')
  <script src="{{asset('assets/js/draw-show-ver3.js')}}"></script>
  <script src="{{asset('assets/js/ui-toasts.js')}}"></script>
@endsection

{{-- ================================
      PAGE CONTENT
================================ --}}
@section('content')

<div class="card-header event-header">
  <h3 class="text-center">
    {{$event->name}}:
    <div class="badge bg-info">{{$draw->drawName}}</div>
  </h3>
</div>

<div class="row">

  <div class="col-12 col-md-3">
    @include('backend.adminPage.admin_show.navbar.navbar')
  </div>

  <div class="col-12 col-md-9">

    <div class="col-12 col-md-12">
      <div class="nav-align-top">
        <ul class="nav nav-pills mb-3" role="tablist">
          <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#draws">Draw</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#options">Settings</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#players">Players</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#fixtures">Fixtures</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">Schedule</button></li>
        </ul>

        <div class="tab-content">

          {{-- =======================
                  DRAW TAB
          ======================= --}}
          <div class="tab-pane fade show active" id="draws">

            <div class="col-12 mb-3">
              @if($draw->oop_published == 1)
                <button data-id="{{$draw->id}}" id="unpublishOOP" class="btn btn-danger">Unpublish Order of Play</button>
              @else
                <button data-id="{{$draw->id}}" id="publishOOP" class="btn btn-success">Publish Order of Play</button>
              @endif

              <a href="{{route('event.draw.get.pdf',$draw->id)}}">Print</a>
            </div>

            @switch($draw->settings->draw_format_id)
              @case(1)
                @break

              @case(2)
                @include('backend.draw._includes.individual_draw')
                @break

              @default
                <div class="alert alert-danger">No draw selected!</div>
            @endswitch

          </div>

          {{-- =======================
                 PLAYERS TAB
          ======================= --}}
          <div class="tab-pane fade" id="players">
            <div class="demo-inline-spacing mb-3">
              <button type="button" class="btn btn-label-linkedin" data-bs-toggle="modal" data-bs-target="#add-player-modal">
                <i class="ti ti-users-plus me-2"></i> Add Player
              </button>

              <button type="button" class="btn btn-label-github" data-bs-toggle="modal" data-bs-target="#add-category-modal">
                <i class="ti ti-clipboard-data me-2"></i> Copy from Category
              </button>
            </div>

            <div class="card mb-3">
              <div class="card-body">
                <p>Player Ranking</p>
          <ul class="list-group" id="handle-list-1" data-draw-id="{{ $draw->id }}">
    @foreach($draw->registrations as $registration)
   <li data-id="{{$registration->id}}" data-seed="{{$registration->pivot->seed}}" class="list-group-item d-flex justify-content-between">
  <span>
    <i class="drag-handle cursor-move ti ti-menu-2 me-2"></i>

    <span class="seed-label text-muted small me-2" data-registration="{{$registration->id}}">
      Seed {{$registration->pivot->seed}}
    </span>

    {{$registration->players[0]->getFullNameAttribute()}}
  </span>
</li>

    @endforeach
</ul>

              </div>
            </div>

            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Seed</th><th>Player</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($draw->registrations as $registration)
                  <tr>
                    <td>
                      <select class="form-select seed-select" data-registration="{{$registration->id}}" data-drawid="{{$draw->id}}">
                        <option value="0">--</option>
                        @for ($i = 1; $i <= $draw->registrations->count(); $i++)
                          <option value="{{$i}}" {{$registration->pivot->seed == $i ? 'selected':''}}>{{$i}}</option>
                        @endfor
                      </select>
                    </td>
                    <td class="fw-medium">{{$registration->players[0]->getFullNameAttribute()}}</td>
                    <td>
                      <div class="dropdown">
                        <button class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                        <div class="dropdown-menu">
                          <button type="button" class="dropdown-item remove-from-draw-button"
                                  data-id="{{$registration->id}}" data-drawid="{{$draw->id}}">
                            <i class="ti ti-trash me-1"></i> Remove
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>

          </div>

          {{-- =======================
                OPTIONS TAB
          ======================= --}}
          <div class="tab-pane fade" id="options">
            <div class="col-12">
              <form action="{{route('draw.update',$draw->id)}}" method="post">
                @method('PUT')
                @csrf

                <div class="mb-3">
                  <label class="form-label">Name</label>
                  <input name="name" type="text" class="form-control" value="{{$draw->drawName}}">
                </div>

                <div class="mb-3">
                  <label class="form-label">Draw Type</label>
                  <select name="draw_type" class="form-select">
                    <option value="1">Please select draw type</option>
                    @foreach($drawTypes as $drawType)
                      <option value="{{$drawType->id}}" {{$drawType->id == $draw->settings->draw_type_id ? 'selected':''}}>
                        {{$drawType->drawTypeName}}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Draw Format</label>
                  <select name="draw_format" class="form-select">
                    <option value="1">Please select draw format</option>
                    @foreach($drawFormats as $format)
                      <option value="{{$format->id}}" {{$format->id == $draw->settings->draw_format_id ? 'selected':''}}>
                        {{$format->name}}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Number of sets</label>
                  <select name="num_sets" class="form-select">
                    <option value="0">Please select</option>
                    <option value="1" {{$draw->settings->num_sets == 1 ? 'selected':''}}>1</option>
                    <option value="3" {{$draw->settings->num_sets == 3 ? 'selected':''}}>3</option>
                    <option value="5" {{$draw->settings->num_sets == 5 ? 'selected':''}}>5</option>
                  </select>
                </div>

                <button type="submit" class="btn btn-primary">Update Settings</button>
              </form>
            </div>
          </div>

          {{-- =======================
                FIXTURES TAB
          ======================= --}}
          <div class="tab-pane fade" id="fixtures">
            <div class="card">
              <h5 class="card-header">{{$draw->drawName}}</h5>
              <div class="card-body">
                @include('bracket.partials.fixtures')
              </div>
            </div>
          </div>

          {{-- =======================
                SCHEDULE TAB
          ======================= --}}
          <div class="tab-pane fade" id="schedule">
            <div class="card">
              <h5 class="card-header">Schedule</h5>

              <div class="row">
                <div class="col-6">
                  <div class="card-body">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      Schedule Settings
                    </button>
                  </div>
                </div>

                <div class="col-6">
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item">Venue: <span id="venue"></span></li>
                    <li class="list-group-item">Number of courts: <span id="numcourts"></span></li>
                    <li class="list-group-item">Match duration: <span id="duration"></span></li>
                    <li class="list-group-item">Start Time: <span id="startTime"></span></li>
                    <li class="list-group-item">Last Match Time: <span id="endTime"></span></li>
                  </ul>
                </div>
              </div>

              <div class="card">
                <div class="card-body">
                  <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#basicModal">
                    Schedule Matches
                  </button>
                </div>
              </div>

            </div>
          </div>


        </div>
      </div>
    </div>

  </div>
</div>

@include('backend.draw._modals.schedule-settings-modal')

{{-- ===========================
      ADD PLAYER MODAL
=========================== --}}
<div class="modal fade" id="add-player-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{route('add.draw.registration',$draw->id)}}" method="post">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title">Select Players to Add</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <label class="form-label">Players</label>
          <select id="select2Multiple" name="players[]" class="select2 form-select" multiple>
            @foreach($event->registrations as $registration)
              <option value="{{$registration->registration->id}}">
                {{$registration->registration->players[0]->getFullNameAttribute()}}
              </option>
            @endforeach
          </select>

          <input type="hidden" name="event_id" value="{{$event->id}}">
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save</button>
        </div>

      </form>
    </div>
  </div>
</div>

{{-- ===========================
      ADD CATEGORY MODAL
=========================== --}}
<div class="modal fade" id="add-category-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{route('add.draw.registration.category',$draw->id)}}" method="post">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title">Select Category to Add</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <select name="category" class="select2 form-select">
            @foreach($event->eventCategories as $event_category)
              <option value="{{$event_category->id}}">{{$event_category->category->name}}</option>
            @endforeach
          </select>

          <input type="hidden" name="event_id" value="{{$event->id}}">
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save</button>
        </div>

      </form>
    </div>
  </div>
</div>

{{-- ===========================
      RESULT MODAL (MAIN)
=========================== --}}
<div class="modal fade" id="result-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="submit-score-form">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title">Edit Score</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <table class="table table-bordered table-sm">
            <thead>
              <tr class="info"><th>Set:</th>
                @for ($i = 0; $i < $draw->settings->num_sets; $i++)
                  <th class="text-center">{{ $i+1 }}</th>
                @endfor
              </tr>
            </thead>

            <tbody id="scoreBody">
              <tr>
                <td><div id="reg1name"></div></td>
                @for ($i = 0; $i < $draw->settings->num_sets; $i++)
                  <td><input type="text" name="reg1Set[]" class="score form-control"></td>
                @endfor
              </tr>

              <tr>
                <td><div id="reg2name"></div></td>
                @for ($i = 0; $i < $draw->settings->num_sets; $i++)
                  <td><input type="text" name="reg2Set[]" class="score form-control"></td>
                @endfor
              </tr>
            </tbody>
          </table>
        </div>

        <input type="hidden" name="fixture_id" id="fixture_id_main">

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary" id="submit-result-button">Submit</button>
        </div>

      </form>
    </div>
  </div>
</div>

{{-- ===========================
     VENUE COURT LIST MODAL
=========================== --}}
<div class="modal fade" id="basicModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Courts</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        @foreach($draw->venues as $venue)
          <p>{{$venue->name}} - {{$venue->num_courts}} courts</p>
        @endforeach
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

@endsection
