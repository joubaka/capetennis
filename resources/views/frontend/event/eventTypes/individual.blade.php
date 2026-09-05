
<div class="row individual-event-view">

  {{-- LEFT COLUMN --}}
  <div class="col-xl-8 col-lg-7 col-md-7">

    @if(($drawPublicationSummary['total'] ?? 0) > 0)
      <div class="d-md-none mb-4">
        <a href="#event-draws-match-times" class="btn btn-primary w-100">
          <i class="ti ti-tournament me-1" aria-hidden="true"></i>
          View draws &amp; match times
          <i class="ti ti-arrow-down ms-1" aria-hidden="true"></i>
        </a>
      </div>
    @endif

    {{-- INFORMATION --}}
    <div class="card event-section-card mb-4">
      <div class="card-body event-card-padding p-4 p-xl-5">
        <div class="event-section-heading mb-4">
          <span class="event-section-icon" aria-hidden="true">
            <svg class="event-section-icon-svg" viewBox="0 0 24 24" focusable="false">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 10v6m0-9v.01" />
            </svg>
          </span>
          <div>
            <h4 class="mb-1">Event information</h4>
            <p class="text-muted mb-0">Please review these details before entering the tournament.</p>
          </div>
        </div>

        @if(filled(strip_tags($event->information ?? '')))
          <div class="event-information-content">
            {!! $event->information !!}
          </div>
        @else
          <div class="alert alert-secondary mb-0" role="status">
            The organiser has not added further event information yet.
          </div>
        @endif
      </div>
    </div>

    {{-- ANNOUNCEMENTS --}}
    @if($event->announcements->isNotEmpty())
      <div class="card event-section-card mb-4">
        <div class="card-body event-card-padding p-4">
          <div class="event-section-heading mb-4">
            <span class="event-section-icon" aria-hidden="true">
              <svg class="event-section-icon-svg" viewBox="0 0 24 24" focusable="false">
                <path d="M4 14h3l9 4V6l-9 4H4v4Zm3 0 2 5h3l-2-5m9-5a4 4 0 0 1 0 6" />
              </svg>
            </span>
            <div>
              <h5 class="mb-1">Latest announcements</h5>
              <p class="text-muted mb-0">Updates published by the event organiser.</p>
            </div>
          </div>

          @foreach($event->announcements as $a)
            <div class="alert alert-primary mb-3">
              @if(filled($a->title))
                <h6 class="alert-heading">{{ $a->title }}</h6>
              @endif
              <div class="event-information-content">{!! $a->message !!}</div>
              <div class="small text-muted mt-2">
                <i class="ti ti-clock me-1"></i>{{ $a->created_at->format('d M Y, H:i') }}
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

  </div>

  {{-- RIGHT COLUMN --}}
  <div class="col-xl-4 col-lg-5 col-md-5">

    {{-- ABOUT --}}
    @include('frontend.event.partials.event-about')

    {{-- RESULTS --}}
    @if($event->results_published == 1)
    <div class="card mb-4">
      <div class="card-header">
        <small class="text-uppercase">Results</small>
      </div>
      <div class="card-body">
        <a href="{{ route('events.results', $event->id) }}" class="btn bg-label-success btn-sm">
          <i class="ti ti-trophy me-1"></i> View Results
        </a>
      </div>
    </div>
    @endif

    {{-- DOCUMENTS --}}
    <div class="card event-section-card mb-4">
      <div class="card-header d-flex justify-content-between">
        <div>
          <h5 class="mb-1">Event documents</h5>
          <p class="text-muted small mb-0">Downloads supplied by the organiser.</p>
        </div>

        @auth
          @if(auth()->user()->is_admin($event->id)->count() > 0 || auth()->id() == 584)
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFileModal">
              Upload PDF
            </button>
          @endif
        @endauth
      </div>

      <div class="card-body">
        @forelse($event->files as $file)
          <div class="event-document d-flex justify-content-between align-items-center gap-2 mb-2 file">
            <a class="d-flex align-items-center gap-2 text-break" href="{{ route('events.documents.show', [$event, $file]) }}">
              <i class="ti ti-file-description fs-4 text-primary"></i>
              <span>{{ $file->name }}</span>
            </a>

            @can('admin')
              @if(auth()->id() == $event->admin || auth()->id() == 584)
                <button
                  class="btn btn-danger btn-sm deleteFileButton"
                  data-id="{{ $file->id }}">
                  Delete
                </button>
              @endif
            @endcan
          </div>
        @empty
          <div class="text-center py-3">
            <i class="ti ti-file-off fs-2 text-muted"></i>
            <p class="text-muted small mb-0 mt-2">No event documents are available yet.</p>
          </div>
        @endforelse
      </div>
    </div>

      {{-- DRAWS & ORDER OF PLAY --}}
    @if(($drawPublicationSummary['total'] ?? 0) > 0)
    <div id="event-draws-match-times" class="card mb-4" tabindex="-1">
      <div class="card-header">
        <h5 class="mb-1">Draws and match times</h5>
        <p class="text-muted small mb-0">Draws and schedules are released separately by the organiser.</p>
      </div>
      <div class="card-body">
@if($eventDraws->isEmpty())
  <div class="alert alert-info mb-0" role="status">
    <div class="fw-semibold">The draws are being finalised.</div>
    <div class="small">They will appear here as soon as the organiser publishes them. Match times and venues may follow later.</div>
  </div>
@else
<div class="event-draw-grid">
@php
  $sortedDraws = $eventDraws->sortBy([
    ['published', 'desc'],
    [fn($d) => $d->draw_types?->ageCategory ?? $d->drawName ?? '', 'asc'],
    ['drawName', 'asc'],
  ]);

  $isConvenorOrSuper = auth()->check() && (
    (method_exists(auth()->user(), 'isConvenorForEvent') && auth()->user()->isConvenorForEvent($event->id))
    || (method_exists(auth()->user(), 'hasRole') && (auth()->user()->hasRole('convenor') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-user')))
  );
@endphp

@foreach($sortedDraws as $draw)
  @php
    $firstSchedule = $draw->order_of_play->whereNotNull('time')->sortBy('time')->first();
    $drawVenueNames = $draw->venues->pluck('name')->filter()->unique()->values();
    $publicDrawUrl = $draw->usesFlexibleMonrad()
      ? route('public.flexible-monrad.show', $draw)
      : route('public.roundrobin.show', $draw);
  @endphp
  <article class="event-draw-card">
    <div>
      <div class="event-draw-name">{{ $draw->drawName ?? 'Draw #'.$draw->id }}</div>
      <div class="event-draw-meta">
        @if($drawVenueNames->isNotEmpty())<span><i class="ti ti-map-pin" aria-hidden="true"></i> {{ $drawVenueNames->join(', ') }}</span>@endif
        @if($draw->oop_published && $firstSchedule?->time)
          <span><i class="ti ti-clock" aria-hidden="true"></i> First match {{ \Carbon\Carbon::parse($firstSchedule->time)->format('H:i') }}</span>
        @endif
      </div>
    </div>
    <div class="event-draw-actions">

    {{-- PUBLISHED --}}
    @if($draw->published)
      <a href="{{ $publicDrawUrl }}#draw"
         class="btn btn-sm btn-outline-primary">
        <i class="ti ti-tournament me-1"></i>
        View draw
      </a>
      @if($draw->oop_published)
        <a href="{{ $publicDrawUrl }}#schedule" class="btn btn-sm btn-success">
          <i class="ti ti-clock me-1" aria-hidden="true"></i> View schedule
        </a>
      @else
        <span class="badge bg-label-secondary">Times to follow</span>
      @endif

      @if($isConvenorOrSuper)
        <a href="{{ route('frontend.fixtures.enter-scores', ['draw' => $draw->id]) }}"
           class="btn btn-sm btn-light border"
           title="Insert Score">
          <i class="ti ti-clipboard-data" aria-hidden="true"></i>
        </a>
      @endif

    {{-- UNPUBLISHED --}}
    @else

      @if($isConvenorOrSuper)
        {{-- Convenor/Admin/Super can open --}}
        <a href="{{ $publicDrawUrl }}#draw"
           class="btn btn-sm btn-outline-secondary">
          <i class="ti ti-tournament me-1"></i>
          Preview draw
        </a>
        <span class="badge bg-label-warning">Draft</span>
      @endif

    @endif

    </div>
  </article>
@endforeach
</div>
@endif
      </div>
    </div>
    @endif

    {{-- PLAYERS --}}
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="mb-1">Players</h5>
        <p class="text-muted small mb-3">Only active entries are shown. Open a category to view its players.</p>
        <label class="visually-hidden" for="event-player-search">Search players</label>
        <input id="event-player-search" type="search" class="form-control mb-3"
               placeholder="Search by player name or category">

        @foreach($eventCats as $eventCategory)
          @php
            $activeRegistrations = $eventCategory->categoryEventRegistrations
              ->where('payment_status_id', 1)
              ->filter(fn($r) => !str_contains(strtolower($r->status ?? ''), 'withdrawn'));
          @endphp
          @if($activeRegistrations->isNotEmpty())
          <details class="event-category border rounded p-2 mb-3"
                   data-search="{{ strtolower($eventCategory->category->name.' '.$activeRegistrations->map(fn($r) => optional(optional($r->registration)->players->first())->full_name)->filter()->implode(' ')) }}">
            <summary class="d-flex align-items-center justify-content-between gap-2" style="cursor:pointer">
            <span class="badge bg-label-primary">
              {{ $eventCategory->category->name }}
              ({{ $activeRegistrations->count() }})
            </span>
            <span class="small text-muted">View players</span>
            </summary>

            <ul class="list-group list-group-flush mt-2">
              @foreach($activeRegistrations as $cereg)
                @php
                  $registration = $cereg->registration;
                  $pivotStatus = strtolower($cereg->status ?? '');
                  $player = $registration ? $registration->players->first() : null;
                @endphp
                @if(!$registration || !$player) @continue @endif
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>
                    {{ $player->name }}
                    {{ $player->surname }}
                  </span>
                  @if(auth()->check() && (int)$cereg->user_id === (int)auth()->id() && !empty($canWithdraw) && $canWithdraw)
                    <button type="button"
                            class="btn btn-xs btn-outline-warning move-category-btn"
                            title="Change category"
                            data-bs-toggle="modal"
                            data-bs-target="#moveCategoryModal"
                            data-entry-id="{{ $cereg->id }}"
                            data-player="{{ $player->name }} {{ $player->surname }}"
                            data-current-category="{{ $eventCategory->category->name }}"
                            data-current-category-id="{{ $eventCategory->id }}">
                      <i class="ti ti-switch-horizontal me-1"></i> Change Category
                    </button>
                  @endif
                </li>
              @endforeach
            </ul>
          </details>
          @endif
        @endforeach
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var search = document.getElementById('event-player-search');
  if (!search) return;

  search.addEventListener('input', function () {
    var term = search.value.trim().toLowerCase();
    document.querySelectorAll('.event-category[data-search]').forEach(function (category) {
      var match = !term || category.dataset.search.includes(term);
      category.hidden = !match;
      if (term && match) category.open = true;
    });
  });
});
</script>
<div class="modal fade" id="addFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Upload Document</h5>
        <button type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>

      <form method="POST"
            action="{{ route('file.store') }}"
            enctype="multipart/form-data">

        @csrf

        <div class="modal-body">

          {{-- REQUIRED BY CONTROLLER --}}
          <input type="hidden" name="event_id" value="{{ $event->id }}">

          <div class="mb-3">
            <label class="form-label">Select file</label>
            <input type="file"
                   name="myFile"
                   class="form-control"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                   required>
            <small class="text-muted">
              Allowed: PDF, Word, Excel (max 5MB)
            </small>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit"
                  class="btn btn-success">
            Upload
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

{{-- ================= WITHDRAWAL DETAILS MODAL ================= --}}
@auth
<div class="modal fade" id="withdrawalDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Withdrawal Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <h6 id="wd-player" class="fw-semibold mb-3"></h6>

        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <small class="text-muted d-block">Withdrawn</small>
            <span id="wd-date"></span>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Refund Method</small>
            <span id="wd-method"></span>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Refund Status</small>
            <span id="wd-status"></span>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Amount</small>
            <span id="wd-gross"></span>
            <small class="text-muted" id="wd-net-wrap"> (net: <span id="wd-net"></span>)</small>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Refunded On</small>
            <span id="wd-refunded-at"></span>
          </li>
        </ul>
      </div>

      <div class="modal-footer flex-column gap-2">
        <a id="wd-wallet-link" href="#" class="btn btn-outline-success btn-sm w-100" style="display:none;">
          <i class="ti ti-wallet me-1"></i> View Wallet Transactions
        </a>
        <a id="wd-inquiry-link" href="#" class="btn btn-outline-primary btn-sm w-100">
          <i class="ti ti-mail me-1"></i> Send Inquiry to Support
        </a>
        <button type="button" class="btn btn-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('withdrawalDetailsModal');
  if (!modal) return;

  var baseUrl = @json(url('/'));

  modal.addEventListener('show.bs.modal', function (e) {
    // Always reset wallet link first
    var walletLink = document.getElementById('wd-wallet-link');
    walletLink.style.display = 'none';
    walletLink.href = '#';

    var btn = e.relatedTarget;
    if (!btn) return;

    var d = btn.dataset;

    document.getElementById('wd-player').textContent      = d.player       || '';
    document.getElementById('wd-date').textContent         = d.withdrawnAt  || '—';
    document.getElementById('wd-method').textContent       = d.method       || 'None';
    document.getElementById('wd-gross').textContent        = d.gross        || '—';
    document.getElementById('wd-net').textContent          = d.net          || '—';
    document.getElementById('wd-refunded-at').textContent  = d.refundedAt   || '—';

    // Status badge color
    var statusEl = document.getElementById('wd-status');
    var st = (d.refundStatus || 'n/a').toLowerCase();
    var cls = 'bg-label-secondary';
    if (st === 'completed') cls = 'bg-label-success';
    else if (st === 'pending')   cls = 'bg-label-warning';
    statusEl.innerHTML = '<span class="badge ' + cls + '">' + (d.refundStatus || 'N/A') + '</span>';

    // Hide net if no value
    document.getElementById('wd-net-wrap').style.display = (d.net && d.net !== '—') ? '' : 'none';

    // Wallet link — only show when refund method is explicitly 'wallet'
    if (d.showWallet === '1' && d.userId) {
      walletLink.href = baseUrl + '/backend/wallet/' + d.userId;
      walletLink.style.display = '';
    }

    // Inquiry mailto
    var supportEmail = 'support@capetennis.co.za';
    var subject = encodeURIComponent('Withdrawal Inquiry – ' + (d.eventName || '') + ' (Ref #' + (d.regId || '') + ')');
    var body    = encodeURIComponent(
      'Hi,\n\nI would like to enquire about my withdrawal:\n\n'
      + 'Event: ' + (d.eventName || '') + '\n'
      + 'Player: ' + (d.player || '') + '\n'
      + 'Registration Ref: #' + (d.regId || '') + '\n'
      + 'Withdrawn on: ' + (d.withdrawnAt || '') + '\n'
      + 'Refund method: ' + (d.method || '') + '\n'
      + 'Refund status: ' + (d.refundStatus || '') + '\n\n'
      + 'Please advise.\n\nThank you.'
    );
    document.getElementById('wd-inquiry-link').href = 'mailto:' + supportEmail + '?subject=' + subject + '&body=' + body;
  });
});
</script>
@endauth

{{-- ================= MOVE CATEGORY MODAL ================= --}}
@auth
<div class="modal fade" id="moveCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-switch-horizontal me-1"></i> Change Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="mb-1"><strong id="mc-player"></strong></p>
        <p class="text-muted small mb-3">Current: <span id="mc-current-cat" class="badge bg-label-primary"></span></p>

        <label for="mc-new-category" class="form-label">Move to</label>
        <select id="mc-new-category" class="form-select" style="width:100%">
          <option value="">Select category…</option>
          @foreach($eventCats as $ec)
            <option value="{{ $ec->id }}">{{ $ec->category->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning btn-sm" id="mc-submit-btn">
          <i class="ti ti-switch-horizontal me-1"></i> Move
        </button>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var mcModal = document.getElementById('moveCategoryModal');
  if (!mcModal) return;

  var mcBaseUrl = @json(url('/'));
  var mcEntryId = null;
  var mcCurrentCatId = null;

  // Init Select2 when modal opens
  $(mcModal).on('shown.bs.modal', function () {
    $('#mc-new-category').select2({
      dropdownParent: $(mcModal),
      placeholder: 'Select category…',
      width: '100%',
      allowClear: true
    });
  });

  mcModal.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    if (!btn) return;

    var d = btn.dataset;
    mcEntryId = d.entryId;
    mcCurrentCatId = d.currentCategoryId;

    document.getElementById('mc-player').textContent = d.player || '';
    document.getElementById('mc-current-cat').textContent = d.currentCategory || '';

    // Reset select and hide current category option
    var $sel = $('#mc-new-category');
    $sel.val('').trigger('change');
    $sel.find('option').each(function () {
      $(this).prop('disabled', $(this).val() === mcCurrentCatId);
    });
  });

  // Close cleanup
  $(mcModal).on('hidden.bs.modal', function () {
    if ($('#mc-new-category').data('select2')) {
      $('#mc-new-category').select2('destroy');
    }
  });

  document.getElementById('mc-submit-btn').addEventListener('click', function () {
    var newCatId = $('#mc-new-category').val();
    var newCatText = $('#mc-new-category option:selected').text().trim();

    if (!newCatId) {
      toastr.warning('Please select a category');
      return;
    }

    // Close the modal first
    var modalInstance = bootstrap.Modal.getInstance(mcModal);
    if (modalInstance) modalInstance.hide();

    // SweetAlert "Are you sure?" confirmation
    Swal.fire({
      title: 'Change Category?',
      html: 'Move player to <strong>' + newCatText + '</strong>?<br><small class="text-muted">The player will be notified by email.</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, move',
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: 'btn btn-warning me-2',
        cancelButton: 'btn btn-secondary'
      },
      buttonsStyling: false
    }).then(function (confirmResult) {
      if (!confirmResult.isConfirmed) return;

      Swal.fire({
        title: 'Moving player…',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
      });

      var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

      fetch(mcBaseUrl + '/registrations/' + mcEntryId + '/move-category', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ new_category_event_id: newCatId })
      })
      .then(function (res) {
        return res.json().then(function (body) { return { ok: res.ok, data: body }; });
      })
      .then(function (response) {
        Swal.close();

        if (response.ok && response.data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Category Changed',
            text: response.data.message,
            timer: 2000,
            showConfirmButton: false
          }).then(function () {
            location.reload();
          });
        } else {
          toastr.error(response.data.message || 'Move failed');
        }
      })
      .catch(function (err) {
        Swal.close();
        console.error('Category move error:', err);
        toastr.error('Something went wrong');
      });
    });
  });
});
</script>
@endauth

