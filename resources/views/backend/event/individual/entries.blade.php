@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Entries')

{{-- Vendor CSS --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">

@endsection

{{-- Vendor JS --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>

@endsection

@section('page-style')
<style>
  /* ============================
   CATEGORY CARD STYLING
============================ */
  .category-card {
    border: 1px solid rgba(105,108,255,.35);
    border-left: 4px solid #696cff;
    border-radius: .375rem;
  }

    .category-card:hover {
      box-shadow: 0 0 0 1px rgba(105,108,255,.25);
    }

    .category-card .card-header {
      background: #f8f8f8;
    }

    /* ============================
   TABLE LAYOUT
============================ */
    .category-card table {
      table-layout: fixed;
      width: 100%;
      min-width: 760px; /* forces scroll on mobile */
    }

    .category-card th,
    .category-card td {
      vertical-align: middle;
      white-space: nowrap;
      font-size: 0.85rem;
    }

  /* Bootstrap scroll wrapper */
  .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* ============================
   COLUMN WIDTHS
============================ */
  .col-idx {
    width: 48px;
    text-align: center;
  }

  .col-player {
    width: 200px;
  }

  .col-email {
    width: 220px;
    font-size: 0.8rem;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .col-cell {
    width: 120px;
    font-size: 0.8rem;
    text-align: center;
  }

  .col-status {
    width: 90px;
    text-align: center;
  }

  .col-payment {
    width: 90px;
    text-align: center;
  }

  .col-actions {
    width: 110px;
  }

  /* ============================
   ROW CONSISTENCY
============================ */
  .category-card tbody tr {
    height: 44px;
  }

  /* ============================
   BADGES
============================ */
  .badge {
    font-weight: 500;
    font-size: 0.7rem;
  }

  /* ============================
   BUTTON SAFETY
============================ */
  .add-player-btn,
  .remove-player-btn,
  .email-btn,
  .category-lock-btn {
    position: relative;
    z-index: 2;
  }

  /* ============================
   MOBILE OPTIMISATION
============================ */
  @media (max-width: 768px) {

    /* Hide email column */
    .col-email {
      display: none;
    }

    /* Allow player name wrapping */
    .col-player {
      white-space: normal;
      font-size: 0.85rem;
    }

    /* Smaller cell column */
    .col-cell {
      width: 90px;
      font-size: 0.75rem;
    }

    /* Stack action buttons */
    .col-actions {
      width: 110px;
    }

    /* Touch-friendly rows */
    .category-card tbody tr {
      height: auto;
    }
  }

  /* ============================
   DROPDOWN OVERFLOW FIX
   Ensures action menus are always
   visible even on small/narrow cards
============================ */
  .category-card {
    overflow: visible !important;
  }

  .category-card .card-body {
    overflow: visible !important;
  }

  .category-card .table-responsive {
    overflow: visible !important;
  }

  /* Keep the horizontal scroll only via the wrapper */
  .table-scroll-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* Dropdowns escape the card/table stacking context */
  .dropdown-menu {
    z-index: 1055;
  }

</style>
@endsection



@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3 event-header-card">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h4 class="mb-0">{{ $event->name }}</h4>
        <small class="text-muted">Category Entries</small>
      </div>

      <div class="d-flex gap-2 flex-wrap">
       <button type="button"
        class="btn btn-outline-primary btn-sm email-btn"
        data-scope="event">

          <i class="ti ti-mail me-1"></i>Email All
        </button>

        <a href="{{ route('admin.events.entries.export', $event) }}" class="btn btn-outline-success btn-sm">
          <i class="ti ti-download me-1"></i>Export
        </a>

        <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back
        </a>
      </div>
    </div>
  </div>

  {{-- CATEGORY LIST --}}
  @foreach($categoryEvents as $categoryEvent)
    <div class="card mb-4 category-card"
         data-category-id="{{ $categoryEvent->id }}"
         data-locked="{{ $categoryEvent->isLocked() ? '1' : '0' }}">
<div class="card-header d-flex justify-content-between align-items-center">
  <div class="category-meta">
    <h5 class="mb-0">{{ $categoryEvent->category?->name }}</h5>
    <small class="text-muted">
        <span class="entry-count">{{ $categoryEvent->allCategoryEventRegistrations->where('status', '!=', 'withdrawn')->count() }}</span> entries
        @if($categoryEvent->allCategoryEventRegistrations->where('status', 'withdrawn')->count() > 0)
          &nbsp;<span class="text-danger withdrawn-count">({{ $categoryEvent->allCategoryEventRegistrations->where('status', 'withdrawn')->count() }} withdrawn)</span>
        @endif
      </small>
  </div>

<div class="category-actions d-flex gap-2">

  {{-- EMAIL CATEGORY --}}
  <button type="button"
          class="btn btn-outline-primary btn-sm email-btn"
          data-scope="category"
          data-category="{{ $categoryEvent->id }}">
    <i class="ti ti-mail me-1"></i>Email Category
  </button>

  {{-- LOCK / UNLOCK --}}
  @if($categoryEvent->isLocked())
    <button type="button"
            class="btn btn-outline-warning btn-sm category-lock-btn"
            data-locked="1"
            data-url-unlock="{{ route('admin.category.unlock', $categoryEvent) }}">
      <i class="ti ti-lock-open me-1"></i>Unlock
    </button>
  @else
    <button type="button"
            class="btn btn-outline-secondary btn-sm category-lock-btn"
            data-locked="0"
            data-url-lock="{{ route('admin.category.lock', $categoryEvent) }}">
      <i class="ti ti-lock me-1"></i>Lock
    </button>
  @endif

  {{-- ADD PLAYER --}}
  @unless($categoryEvent->isLocked())
    <button type="button"
            class="btn btn-outline-success btn-sm add-player-btn"
            data-category="{{ $categoryEvent->id }}"
            data-locked="0">
      <i class="ti ti-plus me-1"></i>Add Player
    </button>
  @endunless

</div>


</div>

     <div class="card-body p-0">
  <div class="table-scroll-wrapper">
    <table class="table table-striped mb-0">

   <thead class="table-light">
  <tr>
    <th class="col-idx">#</th>
    <th class="col-player">Player</th>
    <th class="col-email">Email</th>
    <th class="col-cell">Cell</th>
    <th class="col-status">Status</th>
    <th class="col-payment">Payment</th>
    <th class="col-actions text-end">Actions</th>
  </tr>
</thead>



          <tbody>
            @foreach($categoryEvent->allCategoryEventRegistrations as $reg)

              @php $player = optional($reg->registration?->players)->first(); @endphp
              <tr class="{{ $reg->status === 'withdrawn' ? 'table-danger text-muted' : '' }}" data-entry-id="{{ $reg->id }}">
                <td>{{ $reg->status !== 'withdrawn' ? $loop->iteration : '—' }}</td>
                <td>{{ $player?->name }} {{ $player?->surname }}</td>
                <td class="col-email">
  @if($player?->email)
    <a href="mailto:{{ $player->email }}" class="text-decoration-none">
      {{ $player->email }}
    </a>
  @else
    —
  @endif
</td>


<td class="col-cell">
  {{ $player?->cellNr ?? $player?->cellNr ?? '—' }}
</td>

                <td>
                  <span class="badge {{ $reg->status === 'withdrawn' ? 'bg-danger' : 'bg-success' }}">
                    {{ ucfirst($reg->status ?? 'active') }}
                  </span>
                  @if($reg->status === 'withdrawn' && $reg->refund_status)
                    <br>
                    @php
                      $rsBadge = match($reg->refund_status) {
                        'completed'    => 'bg-success',
                        'pending'      => 'bg-warning text-dark',
                        'not_refunded' => 'bg-secondary',
                        default        => 'bg-light text-dark',
                      };
                    @endphp
                    <span class="badge {{ $rsBadge }} mt-1" style="font-size:.65rem;">
                      {{ str_replace('_', ' ', ucfirst($reg->refund_status)) }}
                    </span>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $reg->payment_status_id == 1 ? 'bg-success' : 'bg-warning' }}">
                    {{ $reg->payment_status_id == 1 ? 'Paid' : 'Unpaid' }}
                  </span>
                </td>
               <td class="col-actions text-end">
  <div class="dropdown">
    <button type="button"
            class="btn btn-outline-secondary btn-sm dropdown-toggle"
            data-bs-toggle="dropdown"
            data-bs-strategy="fixed"
            aria-expanded="false">
      Actions
    </button>
    <ul class="dropdown-menu dropdown-menu-end">

      <li>
        <button type="button"
                class="dropdown-item email-btn"
                data-scope="player"
                data-registration="{{ $reg->registration_id }}">
          <i class="ti ti-mail me-1"></i>Email
        </button>
      </li>

      <li>
        <button type="button"
                class="dropdown-item move-player-btn"
                data-entry="{{ $reg->id }}"
                data-player="{{ $player?->name }} {{ $player?->surname }}"
                data-from-category="{{ $categoryEvent->category?->name }}">
          <i class="ti ti-arrows-transfer-up me-1"></i>Move
        </button>
      </li>

      @if($reg->status !== 'withdrawn')
        <li>
          <button type="button"
                  class="dropdown-item text-warning withdraw-player-btn"
                  data-url="{{ route('admin.category.registration.withdraw', $reg) }}"
                  data-player="{{ trim(($player?->name ?? '') . ' ' . ($player?->surname ?? '')) }}">
            <i class="ti ti-user-minus me-1"></i>Withdraw
          </button>
        </li>
      @endif

      @if(auth()->user()->hasRole('super-user'))
        <li><hr class="dropdown-divider"></li>
        <li>
          <button type="button"
                  class="dropdown-item text-info view-entry-details-btn"
                  data-url="{{ route('admin.entry.details', $reg) }}">
            <i class="ti ti-info-circle me-1"></i>View Details
          </button>
        </li>
      @endif

    </ul>
  </div>
</td>

              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
</div>
    </div>
  @endforeach
</div>

{{-- EMAIL MODAL --}}
@include('backend.event.partials.email-modal')

{{-- ADD PLAYER MODAL (SINGLE) --}}
<div class="modal fade" id="addPlayerModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <form id="addPlayerForm" class="modal-content">
      @csrf
      <input type="hidden" id="add_player_category_id">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Add Player</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label class="form-label fw-semibold">Registration</label>
     <select name="registration_id"
        id="addPlayerRegistration"
        class="form-select select2-player"
        style="width:100%;"
        required>

          <option value="">Select player</option>
        </select>
      </div>

      <div class="modal-footer">
       <button type="button"
        class="btn btn-outline-secondary"
        data-bs-dismiss="modal">
  Cancel
</button>
     <button class="btn btn-primary">Add Player</button>
      </div>
    </form>
  </div>
</div>



<div class="modal fade" id="movePlayerModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <form id="movePlayerForm" class="modal-content">
      @csrf
      <input type="hidden" id="move_entry_id">

      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Move Player</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="alert alert-light border mb-3">
          <div><strong>Player:</strong> <span id="move_player_name"></span></div>
          <div><strong>From:</strong> <span id="move_from_category"></span></div>
          <div><strong>To:</strong> <span id="move_to_category" class="text-primary"></span></div>
        </div>

        <label class="form-label">Select New Category</label>
        <select name="new_category_id" id="moveCategorySelect" class="form-select" required>
          @foreach($categoryEvents as $cat)
              <option value="{{ $cat->id }}">
                  {{ $cat->category?->name }}
              </option>
          @endforeach
        </select>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button class="btn btn-info">
          Move
        </button>
      </div>

    </form>
  </div>
</div>


<script>
window.routes = {
  availableRegistrations: @json(route('admin.category.availableRegistrations', ':id')),
  addPlayer: @json(route('admin.category.addPlayer', ':id')),
  movePlayer: @json(route('admin.category.movePlayer', ':id'))
};
</script>



@endsection

{{-- ENTRY DETAILS MODAL (Super Admin only) --}}
<div class="modal fade" id="entryDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="ti ti-search me-2"></i>Entry Provenance Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="entryDetailsBody">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@section('page-script')
<script>

console.log('📧 Entries page JS loaded');

const csrf = document.querySelector('meta[name="csrf-token"]').content;

/* =====================
   SAFE MODAL INIT
===================== */
const sendMailEl   = document.getElementById('sendMailModal');
const addPlayerEl  = document.getElementById('addPlayerModal');
const movePlayerEl = document.getElementById('movePlayerModal');

const sendMailModal  = sendMailEl  ? new bootstrap.Modal(sendMailEl)  : null;
const addPlayerModal = addPlayerEl ? new bootstrap.Modal(addPlayerEl) : null;
const movePlayerModal = movePlayerEl ? new bootstrap.Modal(movePlayerEl) : null;

// Dropdown strategy is set via data-bs-strategy="fixed" on each button.

/* =====================
   QUILL INIT
===================== */
let quill = null;
if (document.getElementById('messageEditor')) {
    quill = new Quill('#messageEditor', {
        theme: 'snow',
        placeholder: 'Type your message here…'
    });
}

/* =====================
   SELECT2 INIT
===================== */
function initPlayerSelect2() {
    const select = $('#addPlayerRegistration');
    if (!select.length) return;

    select.select2({
        dropdownParent: $('#addPlayerModal'),
        placeholder: 'Search player...',
        allowClear: true,
        width: '100%'
    });
}

/* =====================
   EMAIL MODAL OPEN
===================== */
document.addEventListener('click', function(e) {

    const btn = e.target.closest('.email-btn');
    if (!btn || !sendMailModal) return;

    e.preventDefault();

    const form = document.getElementById('sendMailForm');
    if (!form) return;

    form.reset();
    if (quill) quill.setText('');

    document.getElementById('mail_scope').value = btn.dataset.scope || 'event';
    document.getElementById('mail_category').value = btn.dataset.category || '';
    document.getElementById('mail_registration').value = btn.dataset.registration || '';

    sendMailModal.show();
});

/* =====================
   SEND EMAIL
===================== */
const mailForm = document.getElementById('sendMailForm');
if (mailForm) {
    mailForm.addEventListener('submit', function(e) {
        e.preventDefault();

        if (quill) {
            document.getElementById('emailMessage').value = quill.root.innerHTML;
        }

        fetch('{{ route('admin.events.email.send') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: new FormData(mailForm)
        })
        .then(r => r.json())
        .then(res => {
            alert(`Email sent to ${res.sent} recipients`);
            sendMailModal.hide();
        })
        .catch(() => alert('Email failed'));
    });
}

/* =====================
   CATEGORY LOCK / UNLOCK
===================== */
document.addEventListener('click', function(e) {

    const btn = e.target.closest('.category-lock-btn');
    if (!btn) return;

    e.preventDefault();

    const locked = btn.dataset.locked === '1';
    const url = locked ? btn.dataset.urlUnlock : btn.dataset.urlLock;

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(res => {
        const card = btn.closest('.category-card');
        const nowLocked = res.locked;

        // Update card data attribute
        if (card) card.dataset.locked = nowLocked ? '1' : '0';

        // Toggle button appearance
        if (nowLocked) {
            btn.dataset.locked = '1';
            btn.className = 'btn btn-outline-warning btn-sm category-lock-btn';
            btn.innerHTML = '<i class="ti ti-lock-open me-1"></i>Unlock';
            // Swap data-url attributes
            btn.dataset.urlUnlock = url;
            delete btn.dataset.urlLock;
            // Hide Add Player button
            if (card) card.querySelectorAll('.add-player-btn').forEach(b => b.style.display = 'none');
        } else {
            btn.dataset.locked = '0';
            btn.className = 'btn btn-outline-secondary btn-sm category-lock-btn';
            btn.innerHTML = '<i class="ti ti-lock me-1"></i>Lock';
            btn.dataset.urlLock = url;
            delete btn.dataset.urlUnlock;
            // Show Add Player button (or create one if missing)
            if (card) {
                let addBtn = card.querySelector('.add-player-btn');
                if (addBtn) {
                    addBtn.style.display = '';
                } else {
                    const newBtn = document.createElement('button');
                    newBtn.type = 'button';
                    newBtn.className = 'btn btn-outline-success btn-sm add-player-btn';
                    newBtn.dataset.category = card.dataset.categoryId;
                    newBtn.dataset.locked = '0';
                    newBtn.innerHTML = '<i class="ti ti-plus me-1"></i>Add Player';
                    btn.parentElement.appendChild(newBtn);
                }
            }
        }
    })
    .catch(() => alert('Lock / unlock failed'));
});

/* =====================
   REMOVE PLAYER
===================== */
document.addEventListener('click', function(e) {

    const btn = e.target.closest('.remove-player-btn');
    if (!btn) return;

    e.preventDefault();

    if (!confirm('Remove player from category?')) return;

    fetch(btn.dataset.url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(() => {
        const row = btn.closest('tr');
        const card = btn.closest('.category-card');
        if (row) row.remove();
        // Reindex row numbers
        if (card) reindexRows(card);
        updateEntryCount(card, -1);
    })
    .catch(() => alert('Remove failed'));
});

/* =====================
   WITHDRAW PLAYER
===================== */
document.addEventListener('click', function(e) {

    const btn = e.target.closest('.withdraw-player-btn');
    if (!btn) return;

    e.preventDefault();

    const playerName = btn.dataset.player || 'this player';
    if (!confirm('Withdraw ' + playerName + ' from this event? This cannot be undone.')) return;

    fetch(btn.dataset.url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => {
        if (!r.ok && r.status !== 200) throw new Error('Withdraw failed');
        // If JSON response, update DOM; if redirect (non-JSON), reload
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            return r.json().then(res => {
                // If a redirect URL is returned (e.g. refund page for paid super-users), follow it
                if (res.redirect) {
                    window.location.href = res.redirect;
                    return;
                }
                const row = btn.closest('tr');
                const card = btn.closest('.category-card');
                if (row) {
                    row.classList.add('table-danger', 'text-muted');
                    // Update status badge cell
                    const statusCell = row.querySelector('.col-status, td:nth-child(5)');
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge bg-danger">Withdrawn</span>';
                    }
                    // Update index cell to dash
                    const idxCell = row.querySelector('td:first-child');
                    if (idxCell) idxCell.textContent = '—';
                    // Remove the withdraw button from the dropdown
                    btn.closest('li')?.remove();
                }
                updateWithdrawnCount(card, +1);
                reindexRows(card);
            });
        } else {
            // Controller returned a redirect (e.g. refund page) – follow it
            if (r.redirected) {
                window.location.href = r.url;
            } else {
                location.reload();
            }
        }
    })
    .catch(() => alert('Withdraw failed'));
});

/* =====================
   ADD PLAYER MODAL
===================== */
document.addEventListener('click', function(e) {

    const btn = e.target.closest('.add-player-btn');
    if (!btn || !addPlayerModal) return;

    e.preventDefault();

    if (btn.dataset.locked === '1') {
        alert('Category is locked');
        return;
    }

    const categoryId = btn.dataset.category;
    document.getElementById('add_player_category_id').value = categoryId;

    const select = $('#addPlayerRegistration');

    if (select.hasClass('select2-hidden-accessible')) {
        select.select2('destroy');
    }

    select.html('<option>Loading…</option>');

    const url = window.routes.availableRegistrations.replace(':id', categoryId);

    fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(list => {

        select.empty();

        if (!list.length) {
            select.append('<option disabled>No available players</option>');
        } else {
            list.forEach(p => {
                select.append(new Option(p.name, p.id, false, false));
            });
        }

        initPlayerSelect2();
        addPlayerModal.show();
    })
    .catch(() => alert('Failed to load registrations'));
});

/* =====================
   ADD PLAYER SUBMIT
===================== */
const addForm = document.getElementById('addPlayerForm');
if (addForm) {
    addForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const categoryId = document.getElementById('add_player_category_id').value;
        const url = window.routes.addPlayer.replace(':id', categoryId);

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: new FormData(addForm)
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || 'Add player failed');
                return;
            }
            addPlayerModal.hide();
            // Find the category card
            const categoryId = document.getElementById('add_player_category_id').value;
            const card = document.querySelector(`.category-card[data-category-id="${categoryId}"]`);
            if (card && res.row) {
                const tbody = card.querySelector('tbody');
                if (tbody) {
                    tbody.insertAdjacentHTML('beforeend', res.row);
                    reindexRows(card);
                    updateEntryCount(card, +1);
                }
            }
        })
        .catch(() => alert('Add player failed'));
    });
}

/* =====================
   MOVE PLAYER
===================== */
document.addEventListener('click', function(e) {

    const btn = e.target.closest('.move-player-btn');
    if (!btn || !movePlayerModal) return;

    const entryId = btn.dataset.entry;
    const playerName = btn.dataset.player || '';
    const fromCategory = btn.dataset.fromCategory || '';

    document.getElementById('move_entry_id').value = entryId;

    const nameEl = document.getElementById('move_player_name');
    const fromEl = document.getElementById('move_from_category');
    const toEl   = document.getElementById('move_to_category');
    const select = document.getElementById('moveCategorySelect');

    if (nameEl) nameEl.textContent = playerName;
    if (fromEl) fromEl.textContent = fromCategory;
    if (toEl && select) {
        toEl.textContent = select.options[select.selectedIndex].text;
    }

    movePlayerModal.show();
});

/* =====================
   UPDATE DESTINATION LIVE
===================== */
const moveSelect = document.getElementById('moveCategorySelect');
if (moveSelect) {
    moveSelect.addEventListener('change', function () {
        const toEl = document.getElementById('move_to_category');
        if (toEl) {
            toEl.textContent = this.options[this.selectedIndex].text;
        }
    });
}

/* =====================
   MOVE PLAYER SUBMIT
===================== */
const moveForm = document.getElementById('movePlayerForm');
if (moveForm) {
    moveForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const entryId = document.getElementById('move_entry_id').value;
        const url = window.routes.movePlayer.replace(':id', entryId);

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: new FormData(moveForm)
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success === false) {
                alert(res.message || 'Move failed');
                return;
            }
            movePlayerModal.hide();
            // Remove row from current card
            const entryId = document.getElementById('move_entry_id').value;
            const row = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (row) {
                const card = row.closest('.category-card');
                row.remove();
                reindexRows(card);
                updateEntryCount(card, -1);
            }
            // If new row HTML is returned, append to destination card
            const newCatId = document.getElementById('moveCategorySelect').value;
            const destCard = document.querySelector(`.category-card[data-category-id="${newCatId}"]`);
            if (destCard && res && res.row) {
                const tbody = destCard.querySelector('tbody');
                if (tbody) {
                    tbody.insertAdjacentHTML('beforeend', res.row);
                    reindexRows(destCard);
                    updateEntryCount(destCard, +1);
                }
            }
        })
        .catch(() => alert('Move failed'));
    });
}

/* =====================
   DOM HELPER FUNCTIONS
===================== */
function reindexRows(card) {
    if (!card) return;
    let idx = 1;
    card.querySelectorAll('tbody tr').forEach(row => {
        const idxCell = row.querySelector('td:first-child');
        if (!idxCell) return;
        if (row.classList.contains('table-danger')) {
            idxCell.textContent = '—';
        } else {
            idxCell.textContent = idx++;
        }
    });
}

function updateEntryCount(card, delta) {
    if (!card) return;
    const countEl = card.querySelector('.entry-count');
    if (countEl) {
        const current = parseInt(countEl.textContent, 10) || 0;
        countEl.textContent = Math.max(0, current + delta);
    }
}

function updateWithdrawnCount(card, delta) {
    if (!card) return;
    let wEl = card.querySelector('.withdrawn-count');
    const current = wEl ? (parseInt(wEl.textContent, 10) || 0) : 0;
    const newVal = current + delta;
    if (newVal <= 0) {
        if (wEl) wEl.remove();
    } else if (wEl) {
        wEl.textContent = `(${newVal} withdrawn)`;
    } else {
        const meta = card.querySelector('.category-meta small');
        if (meta) {
            meta.insertAdjacentHTML('beforeend', ` &nbsp;<span class="text-danger withdrawn-count">(${newVal} withdrawn)</span>`);
        }
    }
}

// ============================
//  ENTRY DETAILS (Super Admin)
// ============================
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.view-entry-details-btn');
    if (!btn) return;

    const url = btn.dataset.url;
    const modal = new bootstrap.Modal(document.getElementById('entryDetailsModal'));
    document.getElementById('entryDetailsBody').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(d => {
            if (d.error) {
                document.getElementById('entryDetailsBody').innerHTML =
                    `<div class="alert alert-danger">${d.error}</div>`;
                return;
            }
            const na = v => v ?? '—';
            const badge = (label, cls) => `<span class="badge ${cls} me-1">${label}</span>`;

            let txHtml = '';
            if (d.transactions && d.transactions.length) {
                txHtml = `<table class="table table-sm table-bordered mt-2 mb-0">
                  <thead class="table-light"><tr>
                    <th>TX ID</th><th>PayFast ID</th><th>Gross</th><th>Fee</th><th>Net</th>
                    <th>CT Fee</th><th>Item</th><th>Payer Name</th><th>Test</th><th>Date</th>
                  </tr></thead><tbody>`;
                d.transactions.forEach(t => {
                    txHtml += `<tr>
                      <td>${na(t.id)}</td>
                      <td>${na(t.pf_payment_id)}</td>
                      <td>R${parseFloat(t.amount_gross||0).toFixed(2)}</td>
                      <td>R${parseFloat(t.amount_fee||0).toFixed(2)}</td>
                      <td>R${parseFloat(t.amount_net||0).toFixed(2)}</td>
                      <td>R${parseFloat(t.cape_tennis_fee||0).toFixed(2)}</td>
                      <td>${na(t.item_name)}</td>
                      <td>${na(t.payer_name)}</td>
                      <td>${t.is_test ? badge('TEST','bg-danger') : badge('Live','bg-success')}</td>
                      <td style="white-space:nowrap">${na(t.created_at)}</td>
                    </tr>`;
                });
                txHtml += '</tbody></table>';
            } else {
                txHtml = '<em class="text-muted">No transactions found for this entry.</em>';
            }

            let addedByHtml = '—';
            if (d.added_by) {
                const roleClass = d.added_by.role === 'Super Admin' ? 'bg-danger' : 'bg-secondary';
                addedByHtml = `
                  <strong>${na(d.added_by.name)}</strong>
                  ${badge(d.added_by.role, roleClass)}
                  <br><small class="text-muted">ID: ${d.added_by.id} &nbsp;|&nbsp; ${na(d.added_by.email)} &nbsp;|&nbsp; userType: ${na(d.added_by.userType)}</small>`;
            }

            const pmClass = d.payment_method?.includes('PayFast') && d.payment_method?.includes('Wallet') ? 'bg-success'
                          : d.payment_method?.includes('PayFast') ? 'bg-success'
                          : d.payment_method?.includes('Wallet') ? 'bg-info text-dark'
                          : d.payment_method?.includes('Admin') ? 'bg-warning text-dark'
                          : 'bg-secondary';

            const pmBadge = d.payment_method?.includes('Wallet') && d.payment_method?.includes('PayFast')
                ? `<span class="badge bg-success me-1">PayFast</span><span class="badge bg-info text-dark">+ Wallet</span>`
                : `<span class="badge ${pmClass}">${na(d.payment_method)}</span>`;

            // Wallet section
            let walletHtml = '';
            if (d.wallet_payment || d.wallet_refund) {
                walletHtml = `<div class="col-12"><div class="card border-0 bg-light"><div class="card-body">
                  <h6 class="card-title text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.08em">Wallet</h6>`;
                if (d.wallet_payment) {
                    const meta = (() => { try { return JSON.parse(d.wallet_payment.meta); } catch(e) { return {}; } })();
                    walletHtml += `<p class="mb-1"><strong>Payment:</strong> R${parseFloat(d.wallet_payment.amount||0).toFixed(2)}
                      &nbsp;<span class="badge bg-info text-dark">Wallet debit</span>
                      &nbsp;<small class="text-muted">${na(d.wallet_payment.created_at)}</small></p>
                      <p class="mb-1 text-muted" style="font-size:.82rem">WT ID: ${d.wallet_payment.wt_id} &nbsp;|&nbsp; Payable: ${na(d.wallet_payment.payable)}</p>
                      ${meta.reference ? `<p class="mb-0 text-muted" style="font-size:.82rem">Ref: ${meta.reference}</p>` : ''}`;
                }
                if (d.wallet_refund) {
                    const meta2 = (() => { try { return JSON.parse(d.wallet_refund.meta); } catch(e) { return {}; } })();
                    walletHtml += `<p class="mb-1 mt-2"><strong>Refund credited to wallet:</strong> R${parseFloat(d.wallet_refund.amount||0).toFixed(2)}
                      &nbsp;<span class="badge bg-success">Wallet credit</span>
                      &nbsp;<small class="text-muted">${na(d.wallet_refund.created_at)}</small></p>
                      <p class="mb-0 text-muted" style="font-size:.82rem">WT ID: ${d.wallet_refund.wt_id}${meta2.gross ? ' &nbsp;|&nbsp; Gross: R'+meta2.gross : ''}</p>`;
                }
                walletHtml += `</div></div></div>`;
            }

            document.getElementById('entryDetailsBody').innerHTML = `
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="card border-0 bg-light h-100">
                    <div class="card-body">
                      <h6 class="card-title text-uppercase text-muted mb-3" style="font-size:.7rem;letter-spacing:.08em">Player</h6>
                      <p class="mb-1"><strong>${na(d.player)}</strong></p>
                      <p class="mb-1 text-muted" style="font-size:.82rem">${na(d.player_email)}</p>
                      <p class="mb-1 text-muted" style="font-size:.82rem">Cell: ${na(d.player_cell)}</p>
                      <p class="mb-0 text-muted" style="font-size:.82rem">Player ID: ${na(d.player_id)}</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card border-0 bg-light h-100">
                    <div class="card-body">
                      <h6 class="card-title text-uppercase text-muted mb-3" style="font-size:.7rem;letter-spacing:.08em">Entry Info</h6>
                      <p class="mb-1"><strong>${na(d.category)}</strong></p>
                      <p class="mb-1 text-muted" style="font-size:.82rem">${na(d.event)}</p>
                      <p class="mb-1">
                        Status: ${badge(d.entry_status ?? '—', d.entry_status === 'withdrawn' ? 'bg-danger' : 'bg-success')}
                        ${badge(d.payment_status ?? '—', d.payment_status === 'Paid' ? 'bg-success' : 'bg-warning text-dark')}
                      </p>
                      <p class="mb-1">Payment: ${pmBadge}</p>
                      <p class="mb-1 text-muted" style="font-size:.82rem">PF Transaction ID: ${na(d.pf_transaction_id)}</p>
                      <p class="mb-0 text-muted" style="font-size:.82rem">CER ID: ${na(d.entry_id)} &nbsp;|&nbsp; Created: ${na(d.created_at)}</p>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="card border-0 bg-light">
                    <div class="card-body">
                      <h6 class="card-title text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.08em">Added By</h6>
                      ${addedByHtml}
                    </div>
                  </div>
                </div>
                ${d.entry_status === 'withdrawn' ? `                <div class="col-12">
                  <div class="card border-0 bg-light">
                    <div class="card-body">
                      <h6 class="card-title text-uppercase text-muted mb-2" style="font-size:.7rem;letter-spacing:.08em">Withdrawal / Refund</h6>
                      <p class="mb-1">Withdrawn at: ${na(d.withdrawn_at)}</p>
                      <p class="mb-1">Refund status: ${na(d.refund_status)} &nbsp;|&nbsp; Method: ${na(d.refund_method)}</p>
                      <p class="mb-0">Refund gross: R${parseFloat(d.refund_gross||0).toFixed(2)}</p>
                    </div>
                  </div>
                </div>` : ''}
                ${walletHtml}
                <div class="col-12">
                  <h6 class="text-uppercase text-muted mb-1" style="font-size:.7rem;letter-spacing:.08em">Transactions (transactions_pf)</h6>
                  <div class="table-responsive">${txHtml}</div>
                </div>
              </div>`;
        })
        .catch(() => {
            document.getElementById('entryDetailsBody').innerHTML =
                '<div class="alert alert-danger">Failed to load entry details.</div>';
        });
});

</script>
@endsection








