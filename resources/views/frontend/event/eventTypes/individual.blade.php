
<div class="row individual-event-view">

  {{-- LEFT COLUMN --}}
  <div class="col-xl-8 col-lg-7 col-md-7">

    @if(($drawPublicationSummary['total'] ?? 0) > 0)
      <div class="mb-4">
        <a href="#event-draws-match-times" class="btn btn-primary w-100">
          <i class="ti ti-tournament me-1" aria-hidden="true"></i>
          View draws &amp; match times
          <i class="ti ti-arrow-down ms-1" aria-hidden="true"></i>
        </a>
      </div>
    @endif

    @include('frontend.event.partials.event-information')
    @include('frontend.event.partials.event-announcements')

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

    @include('frontend.event.partials.event-documents', [
      'documentCardClass' => 'event-section-card',
      'documentTitle' => 'Event documents',
      'documentDescription' => 'Downloads supplied by the organiser.',
      'documentEmptyMessage' => 'No event documents are available yet.',
      'documentNewWindow' => false,
      'documentUploadModal' => '#addFileModal',
    ])
    @include('frontend.event.partials.event-draws')

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
                    {{ $player->surname }}<x-player-rating :player-id="$player->id" :context="$eventCategory" />
                  </span>
                  @if(auth()->check() && (int)$cereg->user_id === (int)auth()->id() && $event->registrationIsOpen())
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
