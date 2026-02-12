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
    width: 200px;
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
      width: 120px;
    }

      .col-actions .btn-group {
        flex-direction: column;
        gap: 4px;
      }

      .col-actions .btn {
        width: 100%;
        font-size: 0.75rem;
        padding: 4px 6px;
      }

    /* Touch-friendly rows */
    .category-card tbody tr {
      height: auto;
    }
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
    <div class="card mb-4 category-card">
<div class="card-header d-flex justify-content-between align-items-center">
  <div class="category-meta">
    <h5 class="mb-0">{{ $categoryEvent->category?->name }}</h5>
    <small class="text-muted">
      {{ $categoryEvent->categoryEventRegistrations->count() }} entries
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
  <div class="table-responsive">
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
            @foreach($categoryEvent->categoryEventRegistrations as $reg)

              @php $player = optional($reg->registration?->players)->first(); @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
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
                </td>
                <td>
                  <span class="badge {{ $reg->payment_status_id == 1 ? 'bg-success' : 'bg-warning' }}">
                    {{ $reg->payment_status_id == 1 ? 'Paid' : 'Unpaid' }}
                  </span>
                </td>
               <td class="col-actions text-end">
  <div class="btn-group btn-group-sm">
    <button type="button"
            class="btn btn-outline-secondary email-btn"
            data-scope="player"
            data-registration="{{ $reg->registration_id }}">
      Email
    </button>
   <button type="button"
    class="btn btn-outline-info move-player-btn"
    data-entry="{{ $reg->id }}"
    data-player="{{ $player?->name }} {{ $player?->surname }}"
    data-from-category="{{ $categoryEvent->category?->name }}">
    Move
</button>


    @unless($categoryEvent->isLocked())
      <button type="button"
              class="btn btn-outline-danger remove-player-btn"
              data-url="{{ route('admin.category.removePlayer', [$categoryEvent, $reg->registration]) }}">
        Remove
      </button>
    @endunless
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
    .then(() => location.reload())
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
    .then(() => location.reload())
    .catch(() => alert('Remove failed'));
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
        .then(() => location.reload())
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
        .then(() => location.reload())
        .catch(() => alert('Move failed'));
    });
}

</script>
@endsection








