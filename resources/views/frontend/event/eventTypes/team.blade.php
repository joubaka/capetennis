<script>
$(document).ready(function () {
  console.log('loading')
  /* ===============================
     OPEN CLOTHING ORDER MODAL
  =============================== */
  $('.clothing-order').on('click', function () {

    const playerId  = $(this).data('playerid');
    const playerName = $(this).data('name');
    const teamId    = $(this).data('team');
    const regionId  = $(this).data('region');
    const eventId   = $(this).data('eventid');

    // Title
    $('#clothingPlayerName').text(playerName);

    // Loader
    const $content = $('#clothing-order-content');
    $content.html('<div class="spinner-border text-primary"></div>');

    // Load order form
    $.ajax({
      url: "{{ route('get.region.clothing.items') }}",
      type: "POST",
      data: JSON.stringify({
        region_id: regionId,
        player_id: playerId,
        team_id: teamId,
        event_id: eventId
      }),
      contentType: "application/json",
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function (html) {
        $content.html(html);
      },
      error: function () {
        $content.html(
          '<div class="alert alert-danger">Failed to load clothing options.</div>'
        );
      }
    });

  });

  /* ===============================
     SAVE CLOTHING ORDER
  =============================== */
  $('#saveClothingOrder').on('click', function () {

    const form = $('#clothing-order-content').find('form');
    if (!form.length) return;

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      data: new FormData(form[0]),
      processData: false,
      contentType: false,
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function (data) {
        if (data.success) {
          Swal.fire('Saved', 'Clothing order saved successfully', 'success');

          const modalEl = document.getElementById('clothing-order-modal');
          bootstrap.Modal.getInstance(modalEl).hide();
        } else {
          Swal.fire('Error', data.message || 'Save failed', 'error');
        }
      },
      error: function () {
        Swal.fire('Error', 'Something went wrong', 'error');
      }
    });

  });

  /* ===============================
     TOGGLE CLOTHING OPTIONS
  =============================== */
  $(document).on('change', '.clothing-toggle', function () {

    const itemId = $(this).data('item');
    const $box = $('#options-' + itemId);

    if (this.checked) {
      $box.removeClass('d-none');
    } else {
      $box.addClass('d-none');

      // Reset inputs when unchecked
      $box.find('select').val('');
      $box.find('input[type="number"]').val(1);
    }

  });

});
</script>



<style>
.file-item:hover {
  background-color: #f8f9fa;
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.card .btn-outline-primary {
  border-radius: 50px;
  font-size: 0.875rem;
  padding: 0.25rem 0.75rem;
}

.card .btn-outline-primary:hover {
  background-color: #0d6efd;
  color: #fff;
}
</style>

{{-- resources/views/frontend/event/eventTypes/team.blade.php --}}
@php
  $regions = $event->regions ?? collect();
@endphp

<div class="col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      {{-- 🔹 Announcements --}}
      <div class="card p-4 mb-4">
        <h5 class="pb-4 mb-4 border-bottom">Announcements</h5>

        @forelse($event->announcements as $a)
          <div class="card shadow-none bg-transparent border border-primary mb-4">
            <div class="card-body">
              <p class="card-text">{!! $a->message !!}</p>
              <small class="text-muted">
                <mark>
                  Announcement @ {{ optional($a->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                </mark>
              </small>
            </div>
          </div>
        @empty
          <div class="alert alert-info mb-0">No announcements yet.</div>
        @endforelse
      </div>

      {{-- 🔹 Information --}}
      <div class="card p-4 mb-4">
        <h5 class="pb-1 mb-4 border-bottom">Information</h5>
        {!! $event->information ?: '<div class="text-muted">No additional information provided.</div>' !!}
      </div>

      {{-- 🔹 Draws and Order of Play (Mobile / Tablet only) --}}
      <div class="card d-block d-md-none mb-4">
        <div class="card-body">
          @include('frontend.event.partials._draws_and_order_of_play')
        </div>
      </div>

      {{-- 🔹 Regions & Teams --}}
      <div class="card p-4">
        <div class="card-body pb-0">
          <div class="badge bg-label-primary mb-3">
            Click on a Region below to register
          </div>
        </div>

        @if($regions->isNotEmpty())
          <div class="nav-align-top nav-tabs-shadow">

            <ul class="nav nav-tabs flex-nowrap overflow-auto" role="tablist">
              @foreach($regions as $idx => $region)
                @php $tabId = 'team' . $region->id; @endphp
                <li class="nav-item">
                  <button class="nav-link {{ $idx === 0 ? 'active' : '' }}"
                          data-bs-toggle="tab"
                          data-bs-target="#{{ $tabId }}"
                          aria-selected="{{ $idx === 0 ? 'true' : 'false' }}">
                    {{ $region->region_name }}
                  </button>
                </li>
              @endforeach
            </ul>

            <div class="tab-content pt-3">
              @foreach($regions as $idx => $region)
                @php $tabId = 'team' . $region->id; @endphp

                <div class="tab-pane fade {{ $idx === 0 ? 'active show' : '' }}"
                     id="{{ $tabId }}">
                  <div class="row">

                    @forelse($region->teams as $team)
                      @if((int)($team->noProfile ?? 0) === 0)
                        @include('frontend.event.partials.profile-team', ['team' => $team])
                      @else
                        @include('frontend.event.partials.no-profile-team', ['team' => $team])
                      @endif
                    @empty
                      <div class="col-12">
                        <div class="alert alert-secondary mb-0">
                          No teams listed for this region yet.
                        </div>
                      </div>
                    @endforelse

                  </div>
                </div>
              @endforeach
            </div>

          </div>
        @else
          <div class="alert alert-secondary mt-3">
            Regions are not configured for this event.
          </div>
        @endif
      </div>
    </div>

    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      {{-- 🔹 About --}}
      <div class="card mb-4">
        <div class="card-body">
          <small class="text-uppercase">About</small>

          <ul class="list-unstyled mb-4 mt-3">
            <li class="d-flex align-items-center mb-3">
              <span class="fw-bold me-2">Start Date:</span>
              <span class="badge bg-label-success">{{ $sDate }}</span>
            </li>

            <li class="d-flex align-items-center mb-3">
              <span class="fw-bold me-2">End Date:</span>
              <span class="badge bg-label-success">{{ $eDate }}</span>
            </li>

            @forelse($event->eventCategories as $ce)
              <li class="d-flex align-items-center mb-3">
                <span class="fw-bold me-2">{{ $ce->category->name }}</span>
                <span>R{{ number_format((float)$ce->entry_fee, 2) }}</span>
              </li>
            @empty
              <li class="d-flex align-items-center mb-3">
                <span class="fw-bold me-2">Entry Fee:</span>
                <span>R{{ number_format((float)$event->entryFee, 2) }}</span>
              </li>
            @endforelse
          </ul>

          <small class="text-uppercase">Contact</small>
          <ul class="list-unstyled mt-3 mb-0">
            <li class="mb-2">
              <strong>Organizer:</strong> {{ $event->organizer }}
            </li>
            <li>
              <strong>Email:</strong>
              @if($event->email)
                <a href="mailto:{{ $event->email }}">{{ $event->email }}</a>
              @else
                <span class="text-muted">Not provided</span>
              @endif
            </li>
          </ul>
        </div>
      </div>

      {{-- 🔹 Documents --}}
      <div class="card mb-4 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> Documents
          </h6>

          @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
            <form action="{{ route('file.store') }}" method="POST"
                  enctype="multipart/form-data" class="mb-0">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <label class="btn btn-sm btn-outline-primary mb-0">
                Upload
                <input type="file" name="myFile" class="d-none"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                       onchange="this.form.submit()">
              </label>
            </form>
          @endif
        </div>

        <div class="card-body pb-2">
          @forelse($event->files as $file)
            <div class="file-item border-bottom py-2 d-flex justify-content-between align-items-center">
              <a href="{{ route('file.show', $file->id) }}" target="_blank"
                 class="fw-semibold text-dark text-decoration-none">
                {{ $file->name }}
              </a>

              @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
                <button class="btn btn-sm btn-outline-danger deleteFileButton"
                        data-id="{{ $file->id }}">
                  <i class="ti ti-trash"></i>
                </button>
              @endif
            </div>
          @empty
            <div class="text-muted">No documents uploaded yet.</div>
          @endforelse
        </div>
      </div>

      {{-- 🔹 Draws and Order of Play (Desktop only) --}}
      <div class="d-none d-md-block">
        @include('frontend.event.partials._draws_and_order_of_play')
      </div>

    </div>
  </div>
</div>
{{-- 🔹 Clothing Order Modal --}}
{{-- Clothing Order Modal --}}
@include('frontend.event.partials._clothing_order_modal')




