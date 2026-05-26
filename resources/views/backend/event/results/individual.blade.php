@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Final Positions')

@section('page-style')
<style>
  .category-card {
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem;
    margin-bottom: 1.5rem;
  }

  .sortable-results li {
    cursor: grab;
  }

  .sortable-results li:active {
    cursor: grabbing;
  }

  .drag-handle {
    cursor: grab;
    opacity: .6;
  }

  .drag-handle:hover {
    opacity: 1;
  }

  .position-badge {
    min-width: 32px;
    display: inline-block;
    text-align: right;
  }

  .btn[disabled] {
    pointer-events: none;
    opacity: .6;
  }

  .removed-players {
    background: #fff8f8;
    border: 1px dashed #dc3545;
    border-radius: .4rem;
    padding: .5rem 1rem;
    margin-top: .75rem;
    font-size: .875rem;
  }

  .removed-players .badge {
    cursor: pointer;
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Final Positions</h4>
        <div class="text-muted">{{ $event->name }}</div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-label-primary">Individual Event</span>

        <button id="save-all" class="btn btn-sm btn-success">
          <i class="ti ti-device-floppy me-1"></i>
          Save All
        </button>
      </div>
    </div>
  </div>

  {{-- CATEGORIES --}}
  @forelse($categories as $category)
    <div class="category-card">

      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
          {{ $category->category->name }}
          <span class="text-muted">
            ({{ $category->registrations->count() }} players)
          </span>
        </h5>

        <button class="btn btn-sm btn-outline-primary save-positions"
                data-category="{{ $category->id }}">
          Save Positions
        </button>
      </div>

      <div class="card-body">
        <ul class="list-group sortable-results"
            data-category="{{ $category->id }}">

          @php $positionCounter = 1; @endphp
          @foreach($category->registrations as $reg)
            @php $isRemoved = $reg->position === null; @endphp
            <li class="list-group-item d-flex align-items-center"
                data-registration="{{ $reg->id }}"
                data-name="{{ $reg->display_name }}"
                @if($isRemoved) data-removed="1" style="display:none;" @endif>

              <span class="me-2 drag-handle">
                <i class="ti ti-grip-vertical"></i>
              </span>

              <strong class="me-3 position-badge">
                @if(!$isRemoved){{ $positionCounter++ }}.@endif
              </strong>

              <span class="flex-grow-1">
                {{ $reg->display_name }}
              </span>

              <button type="button"
                      class="btn btn-sm btn-outline-danger btn-remove-player ms-2"
                      title="Remove from results (did not play)"
                      onclick="removePlayer(this)">
                <i class="ti ti-user-minus"></i>
              </button>

            </li>
          @endforeach

        </ul>

        {{-- Removed players tray --}}
        <div class="removed-players d-none" id="removed-{{ $category->id }}">
          <span class="text-danger fw-semibold me-2"><i class="ti ti-user-off"></i> Removed (no points):</span>
          <span class="removed-list d-inline-flex flex-wrap gap-1"></span>
          <div class="text-muted small mt-1">These players will not receive ranking points. Click × to restore.</div>
        </div>
      </div>
    </div>
  @empty
    <div class="alert alert-warning">
      No categories found for this event.
    </div>
  @endforelse

</div>
@endsection

@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
console.log('[Final Positions] Script loaded');

const SAVE_URL_TEMPLATE = @json(
  route('admin.events.categories.results.store', [
    'event' => $event->id,
    'categoryEvent' => 0
  ])
);

// ------------------------------
// Toast helpers
// ------------------------------
function notifySuccess(msg) {
  window.toastr ? toastr.success(msg) : alert(msg);
}

function notifyError(msg) {
  window.toastr ? toastr.error(msg) : alert(msg);
}

// ------------------------------
// Init: restore removed-tray state on page load
// ------------------------------
document.querySelectorAll('.sortable-results').forEach(list => {
  const categoryId = list.dataset.category;
  const tray = document.getElementById('removed-' + categoryId);
  const removedList = tray.querySelector('.removed-list');

  list.querySelectorAll('li[data-removed]').forEach(li => {
    const regId = li.dataset.registration;
    const name = li.dataset.name;

    const badge = document.createElement('span');
    badge.className = 'badge bg-danger';
    badge.dataset.regId = regId;
    badge.title = 'Click to restore';
    badge.style.cursor = 'pointer';
    badge.innerHTML = name + ' &times;';
    badge.addEventListener('click', () => restorePlayer(regId, categoryId, badge));
    removedList.appendChild(badge);
  });

  if (removedList.children.length > 0) {
    tray.classList.remove('d-none');
  }
});

// ------------------------------
// Remove / Restore player
// ------------------------------
function removePlayer(btn) {
  const li = btn.closest('li');
  const regId = li.dataset.registration;
  const name = li.dataset.name || li.querySelector('.flex-grow-1').textContent.trim();
  const list = li.closest('.sortable-results');
  const categoryId = list.dataset.category;
  const tray = document.getElementById('removed-' + categoryId);
  const removedList = tray.querySelector('.removed-list');

  // Hide the row
  li.dataset.removed = '1';
  li.style.display = 'none';

  // Renumber visible rows
  renumberList(list);

  // Add badge to tray
  const badge = document.createElement('span');
  badge.className = 'badge bg-danger';
  badge.dataset.regId = regId;
  badge.title = 'Click to restore';
  badge.style.cursor = 'pointer';
  badge.innerHTML = name + ' &times;';
  badge.addEventListener('click', () => restorePlayer(regId, categoryId, badge));
  removedList.appendChild(badge);

  tray.classList.remove('d-none');
}

function restorePlayer(regId, categoryId, badge) {
  const list = document.querySelector(`.sortable-results[data-category="${categoryId}"]`);
  const li = list.querySelector(`li[data-registration="${regId}"]`);
  if (li) {
    li.removeAttribute('data-removed');
    li.style.display = '';
    renumberList(list);
  }
  badge.remove();
  const tray = document.getElementById('removed-' + categoryId);
  if (tray.querySelector('.removed-list').children.length === 0) {
    tray.classList.add('d-none');
  }
}

function renumberList(list) {
  let n = 1;
  list.querySelectorAll('li:not([data-removed])').forEach(li => {
    li.querySelector('.position-badge').textContent = n++ + '.';
  });
}

// ------------------------------
// Sortable init
// ------------------------------
document.querySelectorAll('.sortable-results').forEach(list => {
  new Sortable(list, {
    handle: '.drag-handle',
    filter: '[data-removed]',
    animation: 150,
    onEnd() {
      renumberList(list);
    }
  });
});

// ------------------------------
// Save single category
// ------------------------------
async function saveCategory(categoryId, button = null) {
  console.log('[saveCategory] Starting save for categoryId:', categoryId);

  const list = document.querySelector(
    `.sortable-results[data-category="${categoryId}"]`
  );

  if (!list) {
    console.error('[saveCategory] List not found for categoryId:', categoryId);
    throw new Error('List not found');
  }

  const positions = [];
  list.querySelectorAll('li:not([data-removed])').forEach((li, index) => {
    positions.push({
      registration_id: li.dataset.registration,
      position: index + 1
    });
  });

  console.log('[saveCategory] Positions to save:', positions);

  const url = SAVE_URL_TEMPLATE.replace('/0/', `/${categoryId}/`);
  console.log('[saveCategory] POST URL:', url);

  if (button) {
    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;
  }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ positions })
    });

    console.log('[saveCategory] Response status:', res.status, res.statusText);

    const responseText = await res.text();
    console.log('[saveCategory] Response body:', responseText);

    let data;
    try {
      data = JSON.parse(responseText);
      console.log('[saveCategory] Parsed JSON:', data);
    } catch (e) {
      console.error('[saveCategory] Failed to parse JSON response:', e);
    }

    if (!res.ok) {
      console.error('[saveCategory] Save failed with status:', res.status);
      throw new Error('Save failed');
    }

    console.log('[saveCategory] Save successful for categoryId:', categoryId);

    if (button) {
      button.disabled = false;
      button.innerHTML = 'Save Positions';
    }

    return data;
  } catch (error) {
    console.error('[saveCategory] Fetch error:', error);
    if (button) {
      button.disabled = false;
      button.innerHTML = 'Save Positions';
    }
    throw error;
  }
}

// ------------------------------
// Bind per-category buttons
// ------------------------------
document.querySelectorAll('.save-positions').forEach(btn => {
  btn.addEventListener('click', async () => {
    console.log('[save-positions] Button clicked for category:', btn.dataset.category);
    try {
      await saveCategory(btn.dataset.category, btn);
      console.log('[save-positions] Success for category:', btn.dataset.category);
      notifySuccess('Positions saved');
    } catch (e) {
      console.error('[save-positions] Error:', e);
      notifyError('Failed to save positions');
    }
  });
});

// ------------------------------
// Save ALL (SEQUENTIAL)
// ------------------------------
document.getElementById('save-all').addEventListener('click', async () => {
  console.log('[save-all] Save All button clicked');

  const saveAllBtn = document.getElementById('save-all');
  const buttons = Array.from(document.querySelectorAll('.save-positions'));

  console.log('[save-all] Found categories to save:', buttons.length);

  if (!buttons.length) {
    console.warn('[save-all] No categories found');
    notifyError('No categories to save');
    return;
  }

  saveAllBtn.disabled = true;
  saveAllBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Saving…`;

  let success = 0;
  let failed = 0;

  for (const btn of buttons) {
    const categoryId = btn.dataset.category;
    console.log('[save-all] Saving category:', categoryId);
    try {
      await saveCategory(categoryId);
      success++;
      console.log('[save-all] Category saved successfully:', categoryId);
    } catch (e) {
      failed++;
      console.error('[save-all] Category failed:', categoryId, e);
    }
  }

  saveAllBtn.disabled = false;
  saveAllBtn.innerHTML = `<i class="ti ti-device-floppy me-1"></i> Save All`;

  console.log('[save-all] Complete. Success:', success, 'Failed:', failed);

  if (failed === 0) {
    notifySuccess(`All ${success} categories saved`);
  } else {
    notifyError(`Saved ${success}, failed ${failed}`);
  }
});
</script>
@endsection
