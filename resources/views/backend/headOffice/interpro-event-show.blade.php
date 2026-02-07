
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
            @include('backend.draw._includes.draw_tab_interpro')
          @empty
            <div class="alert alert-warning">No draws available yet.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Create New Draw -->
<!-- Modal: Create New Draw (Individual Event) -->
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

          {{-- Only draw name required --}}
          <div class="mb-3">
            <label for="drawName" class="form-label fw-bold">Draw Name</label>
            <input type="text" id="drawName" name="drawName" class="form-control"
                   placeholder="e.g. Boys U14 Main Draw" required>
          </div>

          {{-- Optional hidden event ID --}}
          <input type="hidden" name="event_id" value="{{ $event->id ?? '' }}">

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
    /* ---------------------------------------------
     * GLOBAL VENUES ARRAY (used for venue modal)
     * --------------------------------------------- */
    window.ALL_VENUES = @json($venues->map(fn($v) => [
        'id'   => $v->id,
        'name' => $v->name
    ]));
</script>

<script>
$(document).ready(function () {

    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    /* ============================================================
     * SECTION 1 — DRAW NAME AUTO-UPDATE LOGIC
     * ============================================================ */
    function updateDrawName() {
        let selectedType = $('input[name="draw_type_id"]:checked');
        let typeText = selectedType.closest('.switch').find('.switch-label').text().trim();

        let selectedCat = $('input[name="category_choice"]:checked');
        let catText = selectedCat.data('age')
            || selectedCat.closest('.switch').find('.switch-label').text().trim();

        let name = '';

        if (selectedCat.length) name += catText;

        if (selectedType.val() == "3" && selectedCat.length) {
            name += ' – ' + typeText + ' (Boys & Girls)';
        } else if (typeText) {
            name += ' – ' + typeText;
        }

        $('#drawName').val(name);
    }

    $(document).on('change', 'input[name="draw_type_id"]', function () {
        let selectedVal = $(this).val();
        let isMixed = $(this).data('mixed') == 1;

        if (selectedVal == "3") {
            $('#categorySection').addClass('d-none');
            $('#mixedPlaceholder').addClass('d-none');
            $('#type3Categories').removeClass('d-none');
            $('input[name="category_choice"]').prop('checked', false);

        } else if (isMixed) {
            $('#type3Categories').addClass('d-none');
            $('#categorySection').addClass('d-none');
            $('#mixedPlaceholder').removeClass('d-none');
            $('input[name="category_choice"]').prop('checked', false);

        } else {
            $('#type3Categories').addClass('d-none');
            $('#mixedPlaceholder').addClass('d-none');
            $('#categorySection').removeClass('d-none');
        }

        updateDrawName();
    });

    $(document).on('change', 'input[name="category_choice"]', updateDrawName);


    /* ============================================================
     * SECTION 2 — CREATE DRAW FORM SUBMIT
     * ============================================================ */
    $('#createDrawForm').on('submit', function (e) {
        e.preventDefault();

        const drawName = $('#drawName').val().trim();
        if (!drawName) { toastr.error('Please enter a draw name'); return; }

        $.post("{{ route('headoffice.createSingleDraw', $event->id) }}", $(this).serialize())
        .done(function (data) {
            toastr.success(data.message);
            $('#createDrawModal').modal('hide');
            location.reload();
        })
        .fail(function () {
            toastr.error('Error creating draw');
        });
    });


    /* ============================================================
     * SECTION 3 — RECREATE FIXTURES
     * ============================================================ */
    $(document).on('click', '.btn-recreate-fixtures', function () {
        const $btn = $(this);

        Swal.fire({
            title: 'Recreate Fixtures?',
            html: `This will <strong>delete and rebuild</strong> all fixtures for <b>${$btn.data('draw-name')}</b>.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, recreate',
            confirmButtonColor: '#28a745'
        }).then((result) => {
            if (!result.isConfirmed) return;

            showLoading();

            $.post($btn.data('url'), {_token: csrfToken})
            .done(function (response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    toastr.error(response.message);
                }
            })
            .fail(function () {
                toastr.error('Failed to recreate fixtures.');
            })
            .always(hideLoading);
        });
    });


    /* ============================================================
     * SECTION 4 — GENERATE FIXTURES (NEW DRAW)
     * ============================================================ */
    $('#generate-fixtures-btn').on('click', function (e) {
        e.preventDefault();

        let url = $(this).data('url');
        if (!url) { toastr.error('No fixture generation URL'); return; }

        showLoading();

        $.post(url, {_token: csrfToken})
        .done(function (data) {
            toastr.success(data.message || 'Fixtures generated');
            location.reload();
        })
        .fail(function () {
            toastr.error('Error generating fixtures');
        })
        .always(hideLoading);
    });


    /* ============================================================
     * SECTION 5 — VENUE MODAL LOGIC
     * ============================================================ */

    const $venuesModal     = $('#venuesModal');
    const $venuesForm      = $('#venuesForm');
    const $venuesContainer = $('#venues-container');

    function venueRowTemplate(selectedId = "", numCourts = 1) {
        let options = `<option value="">-- Select Venue --</option>`;
        window.ALL_VENUES.forEach(v => {
            const sel = (String(selectedId) === String(v.id)) ? 'selected' : '';
            options += `<option value="${v.id}" ${sel}>${v.name}</option>`;
        });

        return `
          <div class="venue-row d-flex gap-2 mb-2">
            <select name="venue_id[]" class="form-select venue-select">${options}</select>
            <input type="number" name="num_courts[]" class="form-control" min="1" value="${numCourts}">
            <button type="button" class="btn btn-danger btn-remove-row">&times;</button>
          </div>`;
    }

    function initSelect2($row) {
        let $select = $row.find('.venue-select');
        if ($select.hasClass("select2-hidden-accessible")) $select.select2('destroy');

        $select.select2({
            dropdownParent: $venuesModal,
            width: '100%'
        });
    }

    $(document).on('click', '.btn-add-venues', function () {
        let drawId   = $(this).data('draw-id');
        let drawName = $(this).data('draw-name');

        let url = @json(route('backend.draw.venues.store', ['draw' => '__ID__'])).replace('__ID__', drawId);
        $venuesForm.attr('action', url).data('draw-id', drawId);

        $venuesModal.find('.modal-title').text('Assign Venues to ' + drawName);
        $venuesContainer.empty();

        let jsonUrl = @json(route('backend.draw.venues.json', ['draw' => '__ID__'])).replace('__ID__', drawId);
        
        $.get(jsonUrl).done(function (venues) {
            if (venues.length > 0) {
                venues.forEach(v => {
                    let $row = $(venueRowTemplate(v.id, v.num_courts));
                    $venuesContainer.append($row);
                    initSelect2($row);
                });
            } else {
                let $row = $(venueRowTemplate());
                $venuesContainer.append($row);
                initSelect2($row);
            }

            $venuesModal.modal('show');
        });
    });

    $('#addVenueRow').on('click', function () {
        let $row = $(venueRowTemplate());
        $venuesContainer.append($row);
        initSelect2($row);
    });

    $(document).on('click', '.btn-remove-row', function () {
        $(this).closest('.venue-row').remove();
    });

    $venuesForm.on('submit', function (e) {
        e.preventDefault();

        let url    = $(this).attr('action');
        let data   = $(this).serialize();
        let drawId = $(this).data('draw-id');

        $.post(url, data + '&_token=' + csrfToken)
        .done(function (response) {
            if (!response.success) {
                toastr.error("Could not save venues.");
                return;
            }

            toastr.success("Venues updated successfully.");
            $venuesModal.modal('hide');

            let $vc = $('.draw-venues[data-draw-id="' + drawId + '"]');

            if (response.venues.length > 0) {
                let html = '<small class="text-muted">Venues:</small> ';
                response.venues.forEach(v => {
                    html += `
                        <span class="badge bg-label-primary me-1">
                          ${v.name} <span class="text-muted">(${v.pivot.num_courts})</span>
                        </span>`;
                });
                $vc.html(html);
            } else {
                $vc.empty();
            }
        })
        .fail(() => toastr.error("Error while saving venues."));
    });


    /* ============================================================
     * SECTION 6 — PUBLISH / DELETE DRAW
     * ============================================================ */
    $(document).on('click', '.toggle-publish', function () {
        let $btn = $(this);

        $.post($btn.data('url'), {_token: csrfToken, status: $btn.data('status')})
        .done(function (resp) {
            if (!resp.success) {
                toastr.error("Could not update publish status.");
                return;
            }

            let newStatus = resp.published ? 1 : 0;
            $btn.data('status', newStatus);

            if (newStatus) {
                $btn.removeClass('btn-danger').addClass('btn-success').text('Unpublish');
                toastr.success("Draw published.");
            } else {
                $btn.removeClass('btn-success').addClass('btn-danger').text('Publish');
                toastr.info("Draw unpublished.");
            }
        });
    });

    $(document).on('click', '.btn-delete-draw', function () {
        let $btn = $(this);

        Swal.fire({
            title: 'Delete Draw?',
            html: `Are you sure you want to delete <strong>${$btn.data('draw-name')}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it',
        }).then((res) => {
            if (!res.isConfirmed) return;

            $.ajax({
                url: $btn.data('url'),
                type: 'DELETE',
                data: { _token: csrfToken },
                success: function (resp) {
                    if (resp.success) {
                        toastr.success(resp.message);
                        $btn.closest('.list-group-item').fadeOut(300, function () { $(this).remove(); });
                    } else {
                        toastr.error(resp.message);
                    }
                }
            });
        });
    });


    /* ============================================================
     * SECTION 7 — LOADING OVERLAY HELPERS
     * ============================================================ */
    function showLoading() {
        $('#loading-overlay').removeClass('d-none').addClass('d-flex');
    }
    function hideLoading() {
        $('#loading-overlay').removeClass('d-flex').addClass('d-none');
    }

});
</script>



@endsection
