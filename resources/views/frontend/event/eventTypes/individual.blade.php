
<div class="row">

  {{-- LEFT COLUMN --}}
  <div class="col-xl-8 col-lg-7 col-md-7">

    {{-- ANNOUNCEMENTS --}}
    <div class="card p-4 mb-4">
      <h5 class="mb-4">Announcements</h5>

      @forelse($event->announcements as $a)
        <div class="card shadow-none border border-primary mb-3">
          <div class="card-body">
            {!! $a->message !!}
            <p class="mt-2">
              <small class="text-muted">
                <mark>Announcement · {{ $a->created_at->format('d M Y H:i') }}</mark>
              </small>
            </p>
          </div>
        </div>
      @empty
        <p class="text-muted">No announcements yet.</p>
      @endforelse
    </div>

    {{-- INFORMATION --}}
    <div class="card p-4 mb-4">
      <h5 class="mb-3">Information</h5>
      {!! $event->information !!}
    </div>

  </div>

  {{-- RIGHT COLUMN --}}
  <div class="col-xl-4 col-lg-5 col-md-5">

  {{-- ABOUT --}}
@if(
  $sDate ||
  $eDate ||
  $formatEntryLine ||
  $formatWithdrawalLine ||
  $event->organizer ||
  $event->email
)
<div class="card mb-4">
  <div class="card-body">
    <small class="text-uppercase">About</small>

    <ul class="list-unstyled mt-3">

      @if($sDate)
        <li class="mb-2">
          <strong>Start:</strong>
          <span class="badge bg-label-success">{{ $sDate }}</span>
        </li>
      @endif

      @if($eDate)
        <li class="mb-2">
          <strong>End:</strong>
          <span class="badge bg-label-success">{{ $eDate }}</span>
        </li>
      @endif

      @if($formatEntryLine)
        <li class="mb-2">
          <strong>Entry deadline:</strong>
          <span class="badge bg-label-warning">{{ $formatEntryLine }}</span>
        </li>
      @endif

      @if($formatWithdrawalLine)
        <li class="mb-2">
          <strong>Withdrawal deadline:</strong>
          <span class="badge bg-label-danger">{{ $formatWithdrawalLine }}</span>
        </li>
      @endif

    </ul>

    @if($event->organizer || $event->email)
      <small class="text-uppercase">Contact</small>

      <ul class="list-unstyled mt-3">

        @if($event->organizer)
          <li class="mb-2">
            <strong>Organizer:</strong> {{ $event->organizer }}
          </li>
        @endif

        @if($event->email)
          <li class="mb-2">
            <strong>Email:</strong>
            <a href="mailto:{{ $event->email }}">{{ $event->email }}</a>
          </li>
        @endif

      </ul>
    @endif
  </div>
</div>
@endif

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
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between">
        <small class="text-uppercase">Documents</small>

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
          <div class="d-flex justify-content-between align-items-center mb-2 file">
            <a href="{{ route('file.show', $file->id) }}">{{ $file->name }}</a>

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
          <p class="text-muted">No documents uploaded.</p>
        @endforelse
      </div>
    </div>

      {{-- DRAWS & ORDER OF PLAY --}}
    @if(isset($eventDraws) && $eventDraws->count() > 0)
    <div class="card mb-4">
      <div class="card-header">
        <small class="text-uppercase">Draws & Order of Play</small>
      </div>
      <div class="card-body">
<div class="d-flex flex-wrap gap-2">
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
  <div class="d-flex align-items-center gap-1">

    {{-- PUBLISHED --}}
    @if($draw->published)
      <a href="{{ route('public.roundrobin.show', $draw->id) }}"
         class="btn btn-sm btn-success">
        <i class="ti ti-tournament me-1"></i>
        {{ $draw->drawName ?? 'Draw #'.$draw->id }}
      </a>

      @if($isConvenorOrSuper)
        <a href="{{ route('frontend.fixtures.enter-scores', ['draw' => $draw->id]) }}"
           class="btn btn-sm btn-light border"
           title="Insert Score">
          <i class="bi bi-clipboard-data"></i>
        </a>
      @endif

    {{-- UNPUBLISHED --}}
    @else

      @if($isConvenorOrSuper)
        {{-- Convenor/Admin/Super can open --}}
        <a href="{{ route('public.roundrobin.show', $draw->id) }}"
           class="btn btn-sm btn-outline-secondary">
          <i class="ti ti-tournament me-1"></i>
          {{ $draw->drawName ?? 'Draw #'.$draw->id }}
          <span class="badge bg-danger ms-1">Unpublished</span>
        </a>
      @else
        {{-- Others see button but cannot open --}}
        <span class="btn btn-sm btn-outline-secondary disabled">
          <i class="ti ti-tournament me-1"></i>
          {{ $draw->drawName ?? 'Draw #'.$draw->id }}
          <span class="badge bg-danger ms-1">Unpublished</span>
        </span>
      @endif

    @endif

  </div>
@endforeach
</div>
      </div>
    </div>
    @endif

    {{-- PLAYERS --}}
    <div class="card mb-4">
      <div class="card-body">
        <small class="text-uppercase">Players</small>

        @foreach($eventCats as $eventCategory)
          @php
            $activeRegistrations = $eventCategory->registrations->filter(fn($r) => !str_contains(strtolower($r->pivot->status ?? ''), 'withdrawn'));
          @endphp
          <div class="border rounded p-2 mb-3">
            <span class="badge bg-label-primary mb-2">
              {{ $eventCategory->category->name }}
              ({{ $activeRegistrations->count() }})
            </span>

            <ul class="list-group list-group-flush">
              @foreach($eventCategory->registrations as $registration)
                @php $pivotStatus = strtolower($registration->pivot->status ?? ''); @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>
                    {{ optional($registration->players->first())->name }}
                    {{ optional($registration->players->first())->surname }}
                  </span>
                  @if(str_contains($pivotStatus, 'withdrawn'))
                    <span class="d-flex align-items-center gap-1">
                      <span class="badge bg-label-danger">
                        Withdrawn
                        @if($registration->pivot->withdrawn_at)
                          &nbsp;{{ \Carbon\Carbon::parse($registration->pivot->withdrawn_at)->format('j/n/Y') }}
                        @endif
                      </span>
                      @auth
                        @if((int)$registration->pivot->user_id === (int)auth()->id() || (int)auth()->id() === 584)
                          <button type="button"
                                  class="btn btn-xs btn-outline-secondary withdrawal-details-btn"
                                  title="View withdrawal details"
                                  data-bs-toggle="modal"
                                  data-bs-target="#withdrawalDetailsModal"
                                  data-player="{{ optional($registration->players->first())->name }} {{ optional($registration->players->first())->surname }}"
                                  data-withdrawn-at="{{ $registration->pivot->withdrawn_at ? \Carbon\Carbon::parse($registration->pivot->withdrawn_at)->format('j/n/Y H:i') : '—' }}"
                                  data-method="{{ ucfirst($registration->pivot->refund_method ?? 'none') }}"
                                  data-refund-method="{{ strtolower($registration->pivot->refund_method ?? '') }}"
                                  data-refund-status="{{ ucfirst($registration->pivot->refund_status ?? 'n/a') }}"
                                  data-show-wallet="{{ $registration->pivot->refund_method === 'wallet' ? '1' : '' }}"
                                  data-gross="{{ $registration->pivot->refund_gross ? 'R '.number_format($registration->pivot->refund_gross, 2) : '—' }}"
                                  data-net="{{ $registration->pivot->refund_net ? 'R '.number_format($registration->pivot->refund_net, 2) : '—' }}"
                                  data-refunded-at="{{ $registration->pivot->refunded_at ? \Carbon\Carbon::parse($registration->pivot->refunded_at)->format('j/n/Y') : '—' }}"
                                  data-event-name="{{ $event->name }}"
                                  data-reg-id="{{ $registration->pivot->id }}"
                                  data-user-id="{{ $registration->pivot->user_id }}">
                            <i class="ti ti-info-circle"></i>
                          </button>
                        @endif
                      @endauth
                    </span>
                  @elseif(auth()->check() && (int)$registration->pivot->user_id === (int)auth()->id() && !empty($canWithdraw) && $canWithdraw)
                    <button type="button"
                            class="btn btn-xs btn-outline-warning move-category-btn"
                            title="Change category"
                            data-bs-toggle="modal"
                            data-bs-target="#moveCategoryModal"
                            data-entry-id="{{ $registration->pivot->id }}"
                            data-player="{{ optional($registration->players->first())->name }} {{ optional($registration->players->first())->surname }}"
                            data-current-category="{{ $eventCategory->category->name }}"
                            data-current-category-id="{{ $eventCategory->id }}">
                      <i class="ti ti-switch-horizontal me-1"></i> Change Category
                    </button>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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

