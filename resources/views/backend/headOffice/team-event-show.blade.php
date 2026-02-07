@extends('layouts/layoutMaster')

@section('title', 'Admin - Event Page')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection




@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
@endsection

@section('vendor-script')


{{-- jQuery Repeater --}}


<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')


@endsection

@section('content')

<!-- Event Header -->
<div class="card mb-4">
  <div class="card-header text-center">
    <h3 class="mb-0">Team Event: <span class="text-primary">{{$event->name}}</span></h3>
  </div>
</div>

<div class="row g-3">
  <!-- Sidebar -->
  <div class="col-12 col-md-3">
    <div class="card h-100">
      <div class="card-body p-2">
        @include('backend.adminPage.admin_show.navbar.navbar')
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="col-12 col-md-9">
    @if(session('success'))
  <div class="alert alert-success">
    {{ session('success') }}
  </div>
@endif

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Event Draws</h5>
      
     
  
<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createDrawModal">
  + Create New Draw
</button>


<button id="generate-fixtures-btn"
        data-url="{{ route('headoffice.createFixtures', $event->id) }}"
        class="btn btn-sm btn-success">
  + Create Fixtures
</button>





      </div>
    <!-- Loading Overlay -->
<div id="loading-overlay" 
     class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 justify-content-center align-items-center d-none"
     style="z-index: 2000;">
  <div class="spinner-border text-light" role="status" style="width: 4rem; height: 4rem;">
    <span class="visually-hidden">Generating fixtures...</span>
  </div>
  <span class="ms-3 text-white fw-bold fs-5">Generating fixtures...Please wait...</span>
</div>


      <div class="card-body">
        <div class="list-group">
          @forelse($event->draws as $draw)
            @include('backend.draw._includes.draw_tab_team')
          @empty
            <div class="alert alert-warning">No draws available yet.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Create New Draw -->
<div class="modal fade" id="createDrawModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <form id="createDrawForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

         <!-- Step 1: Draw Type -->
<div class="mb-4">
  <h6 class="fw-bold mb-3">Choose Draw Type</h6>
  <div class="d-flex flex-column gap-2">
    @foreach($drawTypes as $type)
      <label class="switch switch-info">
        <input type="radio" name="draw_type_id" class="switch-input"
               value="{{ $type->id }}" data-mixed="{{ $type->is_mixed ? '1' : '0' }}">
        <span class="switch-toggle-slider"></span>
        <span class="switch-label">{{ $type->drawTypeName }}</span>
      </label>
    @endforeach
  </div>
</div>
<!-- Step 2a: Categories (NOT mixed) -->
<div id="categorySection" class="mb-4 d-none">
  <h6 class="fw-bold mb-3">Choose Category</h6>
  <div class="d-flex flex-column gap-2">
    @foreach($categories as $cat)
      <label class="switch switch-primary">
        <input type="radio"
               name="category_choice"   {{-- keeps radio grouping --}}
               class="switch-input"
               data-id="{{ $cat->id }}">
        <span class="switch-toggle-slider"></span>
        <span class="switch-label">{{ $cat->name }}</span>
      </label>
    @endforeach
  </div>
</div>

<!-- Step 2b: Placeholder for Mixed -->
<div id="mixedPlaceholder" class="mb-4 d-none">
  <div class="alert alert-info">
    Mixed draw option will be available soon.
  </div>
</div>
@php
  // Extract unique age groups (strip "Boys"/"Girls")
  $ageGroups = collect($categories)
                ->map(function($cat) {
                  return preg_replace('/\s+(Boys|Girls)$/i', '', $cat->name);
                })
                ->unique()
                ->values();
@endphp

<!-- Step 2c: Special Categories for Type 3 -->
<div id="type3Categories" class="mb-4 d-none">
  <h6 class="fw-bold mb-3">Choose Age Group</h6>
  <div class="d-flex flex-column gap-2">
    @foreach($ageGroups as $age)
      @php
        $boys = $categories->firstWhere('name', $age . ' Boys');
        $girls = $categories->firstWhere('name', $age . ' Girls');
      @endphp
      @if($boys && $girls)
        <label class="switch switch-primary">
          <input type="radio"
                 name="category_choice"   {{-- keeps radio grouping --}}
                 class="switch-input"
                 data-age="{{ $age }}"
                 data-ids="[{{ $boys->id }},{{ $girls->id }}]">
          <span class="switch-toggle-slider"></span>
          <span class="switch-label">{{ $age }}</span>
        </label>
      @endif
    @endforeach
  </div>
</div>




<!-- Step 3: Auto-generated Name -->
<div class="mb-3">
  <label for="drawName" class="form-label fw-bold">Draw Name (Auto)</label>
  <input type="text" id="drawName" name="drawName" class="form-control" readonly>
</div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  var venues = @json($venues);
  console.debug("[Init] Venues loaded:", venues);

  $(function () {

    // -------------------------
    // Update Draw Name Function
    // -------------------------
    function updateDrawName() {
      console.debug("[updateDrawName] Triggered");

      let selectedType = $('input[name="draw_type_id"]:checked');
      let typeText = selectedType.closest('.switch').find('.switch-label').text().trim();
      console.debug("[updateDrawName] Selected type:", selectedType.val(), typeText);

      let selectedCat = $('input[name="category_choice"]:checked');
      let catText = selectedCat.data('age')
          || selectedCat.closest('.switch').find('.switch-label').text().trim();
      console.debug("[updateDrawName] Selected category:", selectedCat.data(), catText);

      let name = '';

      if (selectedCat.length) {
        name += catText;
      }

      if (selectedType.val() == "3" && selectedCat.length) {
        name += ' – ' + typeText + ' (Boys & Girls)';
      } else if (typeText) {
        name += ' – ' + typeText;
      }

      console.debug("[updateDrawName] Final name:", name);
      $('#drawName').val(name);
    }

    // -------------------------
    // Handle Draw Type Change



    // -------------------------
    // -----------------------------
// Recreate Fixtures per Draw
// -----------------------------
$(document).off('click.draw', '.btn-recreate-fixtures').on('click.draw', '.btn-recreate-fixtures', function () {
  const $btn      = $(this);
  const url       = $btn.data('url');
  const drawName  = $btn.data('draw-name');
  const drawId    = $btn.data('draw-id');
  const csrfToken = $('meta[name="csrf-token"]').attr('content');

  Swal.fire({
    title: 'Recreate Fixtures?',
    html: `This will <strong>delete and rebuild</strong> all fixtures for <b>${drawName}</b>.<br><small class="text-muted">Continue?</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, recreate',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#28a745'
  }).then((result) => {
    if (result.isConfirmed) {
      showLoading();

      $.post(url, {_token: csrfToken})
        .done(function (response) {
          if (response.success) {
            toastr.success(response.message);
            setTimeout(() => location.reload(), 1200);
          } else {
            toastr.error(response.message || 'Error recreating fixtures');
          }
        })
        .fail(function (xhr) {
          console.error('[RecreateFixtures] Error:', xhr);
          toastr.error('❌ Failed to recreate fixtures.');
        })
        .always(() => {
          hideLoading();
        });
    }
  });
});





    $(document).on('change', 'input[name="draw_type_id"]', function () {
      let selectedVal = $(this).val();
      let isMixed = $(this).data('mixed') == 1;
      console.debug("[DrawTypeChange] Selected:", selectedVal, "Mixed:", isMixed);

      if (selectedVal == "3") {
        console.debug("[DrawTypeChange] Showing type 3 categories");
        $('#categorySection').addClass('d-none');
        $('#mixedPlaceholder').addClass('d-none');
        $('#type3Categories').removeClass('d-none');
        $('input[name="category_choice"]').prop('checked', false);

      } else if (isMixed) {
        console.debug("[DrawTypeChange] Showing mixed placeholder");
        $('#type3Categories').addClass('d-none');
        $('#categorySection').addClass('d-none');
        $('#mixedPlaceholder').removeClass('d-none');
        $('input[name="category_choice"]').prop('checked', false);

      } else {
        console.debug("[DrawTypeChange] Showing normal categories");
        $('#type3Categories').addClass('d-none');
        $('#mixedPlaceholder').addClass('d-none');
        $('#categorySection').removeClass('d-none');
      }

      updateDrawName();
    });

    // -------------------------
    // Handle Category Change
    // -------------------------
    $(document).on('change', 'input[name="category_choice"]', function () {
      console.debug("[CategoryChange] Changed:", $(this).data());
      updateDrawName();
    });

    // -------------------------
    // Submit Form
    // -------------------------
    $('#createDrawForm').on('submit', function (e) {
      e.preventDefault();
      console.debug("[FormSubmit] Triggered");

      let selectedVal = $('input[name="draw_type_id"]:checked').val();
      let isMixed = $('input[name="draw_type_id"]:checked').data('mixed') == 1;
      console.debug("[FormSubmit] Selected type:", selectedVal, "Mixed:", isMixed);

      // Clear old hidden inputs
      $('#createDrawForm').find('input[name="category_ids[]"]').remove();

      if (selectedVal == "3") {
        let selectedCat = $('#type3Categories input:checked');
        let ids = selectedCat.data('ids'); // e.g. [39,40]
        console.debug("[FormSubmit] Type 3 categories:", ids);

        if (!ids) {
          toastr.error('Please select an age group');
          return;
        }

        ids.forEach(id => {
          console.debug("[FormSubmit] Adding hidden ID:", id);
          $('<input>').attr({
            type: 'hidden',
            name: 'category_ids[]',
            value: id
          }).appendTo('#createDrawForm');
        });
      } else {
        let selectedCat = $('#categorySection input:checked');
        let id = selectedCat.data('id');
        console.debug("[FormSubmit] Normal category:", id);

        if (!id) {
          toastr.error('Please select a category');
          return;
        }

        $('<input>').attr({
          type: 'hidden',
          name: 'category_ids[]',
          value: id
        }).appendTo('#createDrawForm');
      }

      console.debug("[FormSubmit] Submitting form...");
      $.post("{{ route('headoffice.createSingleDraw', $event->id) }}", $(this).serialize())
        .done(function (data) {
          console.log("[FormSubmit] Success:", data);
          toastr.success(data.message);
          $('#createDrawModal').modal('hide');
        })
        .fail(function (xhr) {
          console.error("[FormSubmit] Error:", xhr.responseText);
          toastr.error('Error creating draw');
        });
    });

  });

  // -------------------------
  // Generate Fixtures
  // -------------------------
  $('#generate-fixtures-btn').on('click', function (e) {
    e.preventDefault();
    console.debug("[Fixtures] Generate button clicked");

    let url = $(this).data('url');
    console.debug("[Fixtures] URL:", url);

    if (!url) {
      toastr.error('No fixture generation URL found');
      return;
    }

    showLoading();

    $.post(url, {_token: '{{ csrf_token() }}'})
      .done(function (data) {
        console.log("[Fixtures] Success:", data);
        toastr.success(data.message || 'Fixtures generated successfully');
        location.reload();
      })
      .fail(function (xhr) {
        console.error("[Fixtures] Error:", xhr.responseText);
        toastr.error('Error generating fixtures');
      })
      .always(() => {
        console.debug("[Fixtures] Hiding loading overlay");
        hideLoading();
      });
  });

  // -------------------------
  // Loading Overlay Helpers
  // -------------------------
  function showLoading() {
    console.debug("[Loading] Show overlay");
    $('#loading-overlay').removeClass('d-none').addClass('d-flex');
  }

  function hideLoading() {
    console.debug("[Loading] Hide overlay");
    $('#loading-overlay').removeClass('d-flex').addClass('d-none');
  }
</script>


@endsection
