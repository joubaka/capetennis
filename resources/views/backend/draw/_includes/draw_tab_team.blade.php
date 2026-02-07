{{-- List item --}}
<div class="list-group-item d-flex align-items-center">
  <div class="w-100">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div class="user-info">
        <h6 class="mb-2">
          {{ $draw->drawName }}
          <span class="text-muted">— {{ optional($draw->draw_types)->drawTypeName ?? 'Type' }}</span>
        </h6>

        @php
          $isTeamEvent = optional($draw->event)->eventType == 3;
        @endphp

        {{-- 📍 Venues display here --}}
        <div class="draw-venues mb-2" data-draw-id="{{ $draw->id }}">
          @if($draw->venues && $draw->venues->count() > 0)
            <small class="text-muted">Venues:</small>
            @foreach($draw->venues as $venue)
              <span class="badge bg-label-primary me-1">
                {{ $venue->name }} <span class="text-muted">({{ $venue->pivot->num_courts }})</span>
              </span>
            @endforeach
          @endif
        </div>

        <div class="btn-group btn-group-sm flex-wrap" role="group">
          {{-- Show Fixtures / Team Fixtures --}}
          <a class="btn btn-warning"
             href="{{ $isTeamEvent
                      ? route('backend.team-fixtures.index', ['draw_id' => $draw->id])
                      : route('draw.show', $draw->id) }}">
            {{ $isTeamEvent ? 'Show Team Fixtures' : 'Show Fixtures' }}
          </a>

          {{-- Publish / Unpublish --}}
          <button type="button"
                  class="btn toggle-publish {{ $draw->published ? 'btn-success' : 'btn-danger' }}"
                  data-url="{{ route('draw.toggle.publish', $draw->id) }}"
                  data-status="{{ $draw->published ? 1 : 0 }}">
            {{ $draw->published ? 'Unpublish' : 'Publish' }}
          </button>

          {{-- Schedule Page --}}
          <a class="btn btn-info"
             href="{{ route('backend.team-schedule.page', $draw->id) }}">
            Schedule Matches
          </a>

          {{-- Add Venues --}}
          <button type="button"
                  class="btn btn-secondary btn-add-venues"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}"
                  data-url="{{ route('backend.draw.venues.store', $draw->id) }}">
            Add Venues
          </button>
          {{-- Recreate Fixtures --}}
<button type="button"
        class="btn btn-success btn-recreate-fixtures"
        data-url="{{ route('headoffice.recreateFixturesForDraw', $draw->id) }}"
        data-draw-id="{{ $draw->id }}"
        data-draw-name="{{ $draw->drawName }}">
  <i class="ti ti-refresh"></i> Recreate Fixtures
</button>

          {{-- Delete Draw --}}
          <button type="button"
                  class="btn btn-danger btn-delete-draw"
                  data-url="{{ route('draws.destroy', $draw->id) }}"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Global Venues Modal --}}
<div class="modal fade" id="venuesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="venuesForm" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Venues</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div id="venues-container">
            {{-- Rows will be added dynamically --}}
          </div>
          <button type="button" class="btn btn-sm btn-secondary" id="addVenueRow">+ Add Venue</button>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
  let $venuesModal     = $('#venuesModal');
  let $venuesForm      = $('#venuesForm');
  let $venuesContainer = $('#venues-container');
  let csrfToken        = $('meta[name="csrf-token"]').attr('content'); // safer CSRF token

  // -----------------------------
  // Venue row template
  // -----------------------------
  function venueRowTemplate(selectedId = "", numCourts = 1) {
    let options = `
      <option value="">-- Select Venue --</option>
      @foreach($venues as $venue)
        <option value="{{ $venue->id }}" ${selectedId == "{{ $venue->id }}" ? 'selected' : ''}>
          {{ $venue->name }}
        </option>
      @endforeach
    `;
    return `
      <div class="venue-row d-flex gap-2 mb-2">
        <select name="venue_id[]" class="form-select venue-select" required>${options}</select>
        <input type="number" name="num_courts[]" class="form-control" value="${numCourts}" min="1" required>
        <button type="button" class="btn btn-danger btn-remove-row" aria-label="Remove venue row">&times;</button>
      </div>
    `;
  }

function initSelect2($row) {
  let $select = $row.find('.venue-select');

  // Only destroy if already initialized
  if ($select.hasClass("select2-hidden-accessible")) {
    $select.select2('destroy');
  }

  $select.select2({
    dropdownParent: $venuesModal,
    width: '100%'
  });
}


  // -----------------------------
  // Add Venues button
  // -----------------------------
  $(document).off('click.venues', '.btn-add-venues').on('click.venues', '.btn-add-venues', function () {
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

  // -----------------------------
  // Add new row manually
  // -----------------------------
  $('#addVenueRow').off('click.venues').on('click.venues', function () {
    let $row = $(venueRowTemplate());
    $venuesContainer.append($row);
    initSelect2($row);
  });

  // -----------------------------
  // Remove row
  // -----------------------------
  $(document).off('click.venues', '.btn-remove-row').on('click.venues', '.btn-remove-row', function () {
    $(this).closest('.venue-row').remove();
  });

  // -----------------------------
  // Save Venues form
  // -----------------------------
  $venuesForm.off('submit.venues').on('submit.venues', function (e) {
    e.preventDefault();
    let url     = $(this).attr('action');
    let data    = $(this).serialize();
    let drawId  = $(this).data('draw-id');

    $.post(url, data + '&_token=' + csrfToken)
      .done(function (response) {
        if (response.success) {
          toastr.success("✅ Venues updated successfully.");
          $venuesModal.modal('hide');

          let $venueContainer = $('.draw-venues[data-draw-id="' + drawId + '"]');
          if (response.venues && response.venues.length > 0) {
            let html = '<small class="text-muted">Venues:</small> ';
            response.venues.forEach(v => {
              html += `<span class="badge bg-label-primary me-1">
                         ${v.name} <span class="text-muted">(${v.pivot.num_courts})</span>
                       </span>`;
            });
            $venueContainer.html(html);
          } else {
            $venueContainer.empty();
          }
        } else {
          toastr.error("❌ Could not save venues.");
        }
      })
      .fail(function (error) {
        console.error(error);
        toastr.error("❌ Error while saving venues.");
      });
  });

  // -----------------------------
  // Toggle Publish button
  // -----------------------------
  $(document).off('click.venues', '.toggle-publish').on('click.venues', '.toggle-publish', function () {
    let $btn          = $(this);
    let url           = $btn.data('url');
    let currentStatus = $btn.data('status');

    $btn.prop('disabled', true);

    $.post(url, {
        _token: csrfToken,
        status: currentStatus
    })
    .done(function (response) {
        if (response.success) {
          let newStatus = response.published ? 1 : 0;
          $btn.data('status', newStatus);

          if (newStatus === 1) {
            $btn.removeClass('btn-danger').addClass('btn-success').text('Unpublish');
            toastr.success("✅ Draw published.");
          } else {
            $btn.removeClass('btn-success').addClass('btn-danger').text('Publish');
            toastr.info("ℹ️ Draw unpublished.");
          }
        } else {
          toastr.error("❌ Could not update publish status.");
        }
    })
    .fail(function () {
        toastr.error("❌ Error while toggling publish.");
    })
    .always(function () {
        $btn.prop('disabled', false);
    });
  });

  // -----------------------------
  // Delete Draw button
  // -----------------------------
  $(document).off('click.venues', '.btn-delete-draw').on('click.venues', '.btn-delete-draw', function () {
    let $btn     = $(this);
    let url      = $btn.data('url');
    let drawName = $btn.data('draw-name');

    Swal.fire({
      title: 'Delete Draw?',
      html: `Are you sure you want to delete <strong>${drawName}</strong>?<br>This action cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#d33'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: url,
          type: 'DELETE',
          data: { _token: csrfToken },
          success: function (response) {
            if (response.success) {
              toastr.success(response.message);
              $btn.closest('.list-group-item').fadeOut(300, function () { $(this).remove(); });
            } else {
              toastr.error(response.message);
            }
          },
          error: function () {
            toastr.error("❌ Error while deleting draw.");
          }
        });
      }
    });
  });
});
</script>








