@extends('layouts.backend')

@section('title', $event->name . ' – Categories')

{{-- =========================
   VENDOR STYLES
========================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
<style>
  .select2-container .select2-selection--multiple {
    min-height: 38px;
    border: 1px solid #d9dee3;
  }

  .container-xl :is(button, summary, input, select) { min-height:44px; }
  #allocateCourtsModal :is(button, input, select) { min-height:44px; }
  #allocateCourtsModal .btn-close { min-width:44px; padding:0; }
  .container-xl summary { cursor:pointer; align-content:center; }
  .fee-input {
    max-width: 120px;
  }
</style>
@endsection

{{-- =========================
   VENDOR SCRIPTS
========================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection


@section('content')
<div class="container-xl">
  @include('backend.event.partials.header', [
    'eventWorkspaceActive' => 'categories',
    'eventWorkspaceIcon' => 'ti-list-details',
    'eventWorkspaceSubtitle' => 'Category setup',
  ])
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Manage categories</h2><p class="text-muted mb-0">Attach categories, set event fees and remove unused setup.</p></div>

  </div>

  @php
    $attachedCategoryIds = $categoryEvents
      ->pluck('category_id')
      ->values()
      ->all();
  @endphp

  @error('entry_limit')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

  {{-- CATEGORY LIST --}}
  <div class="card">
    <div class="card-header"><h3 class="h5 mb-0">Categories in this event</h3></div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Category</th>
            <th class="text-center">Entries</th>
            <th class="text-center">Entry Fee Override</th>
            <th>Division entry cap</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
          @forelse($categoryEvents as $categoryEvent)
            <tr>
              <td>{{ $categoryEvent->category->name }}</td>

              <td class="text-center">
                {{ $categoryEvent->categoryEventRegistrations->count() }}
              </td>

              <td class="text-center">
                <div class="d-flex justify-content-center align-items-center gap-2">
                  <input type="number"
                         class="form-control form-control-sm text-end fee-input category-fee-input"
                         aria-label="Entry fee override for {{ $categoryEvent->category->name }}"
                         data-id="{{ $categoryEvent->id }}"
                         step="1"
                         min="0"
                         value="{{ $categoryEvent->entry_fee }}"
                         placeholder="Default">

                  <button class="btn btn-sm btn-outline-primary save-fee-btn" aria-label="Save fee for {{ $categoryEvent->category->name }}"
                          data-id="{{ $categoryEvent->id }}">
                    <i class="ti ti-device-floppy me-1"></i>Save fee
                  </button>
                </div>
              </td>

              <td style="min-width:260px">
                @if($event->isIndividual())
                <form method="POST" action="{{ route('admin.events.category-limit.update', $categoryEvent) }}" class="division-limit-form">
                  @csrf @method('PATCH')
                  <label for="entry-limit-{{ $categoryEvent->id }}" class="form-label small">Maximum entries (blank = unlimited)</label>
                  <div class="d-flex gap-2">
                    <input id="entry-limit-{{ $categoryEvent->id }}" name="entry_limit" class="form-control" type="number" min="1" max="100000" value="{{ $categoryEvent->entry_limit }}">
                    <button class="btn btn-outline-primary" type="submit">Save cap</button>
                  </div>
                  <button class="btn btn-sm btn-outline-secondary mt-2" type="button"
                          data-bs-toggle="modal" data-bs-target="#allocateCourtsModal"
                          data-cap-input="entry-limit-{{ $categoryEvent->id }}"
                          data-division-name="{{ $categoryEvent->category->name }}">Allocate courts</button>
                </form>
                @else
                  <span class="text-muted small">Available for individual entry events.</span>
                @endif
              </td>
              <td class="text-end">
                @if($categoryEvent->categoryEventRegistrations->isEmpty())
                  <button class="btn btn-sm btn-outline-danger delete-category-btn"
                          data-url="{{ route('admin.category.delete', $categoryEvent) }}">
                    <i class="ti ti-trash me-1"></i>Remove
                  </button>
                @else
                  <span class="badge bg-secondary">In use</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-3">
                No categories attached to this event.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

<div class="mt-4">
  {{-- ADD EXISTING CATEGORY --}}
  <div class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0">Add Existing Category</h5>
    </div>

    <div class="card-body">
      <form method="POST"
            action="{{ route('admin.categories.attach', $event) }}"
            class="d-flex flex-wrap gap-2 align-items-start">
        @csrf

        <div class="flex-grow-1">
          <label for="category-add-existing" class="form-label">Categories to add</label>
          <select name="category_ids[]" id="category-add-existing"
                  class="form-select select2"
                  multiple
                  data-placeholder="Select categories…">
            @foreach($allCategories as $cat)
              <option value="{{ $cat->id }}">
                {{ $cat->name }}
              </option>
            @endforeach
          </select>
        </div>

        <button class="btn btn-primary">
          <i class="ti ti-plus"></i> Add Selected
        </button>
      </form>
    </div>
  </div>

  {{-- CREATE NEW CATEGORY --}}
  <details class="card mb-4" @if($errors->has('name') || old('name')) open @endif>
    <summary class="card-header h5 mb-0">Create New Category</summary>

    <div class="card-body">
      <form method="POST"
            action="{{ route('admin.categories.create', $event) }}"
            class="d-flex flex-wrap gap-2">
        @csrf

        <label for="category-create-name" class="form-label">New category name</label>
        <input type="text"
               name="name" id="category-create-name"
               class="form-control"
               value="{{ old('name') }}" aria-describedby="category-name-error"
               placeholder="e.g. U14 Boys"
               required>

        @error('name')<div id="category-name-error" class="text-danger w-100" role="alert">{{ $message }}</div>@enderror
        <button class="btn btn-success">
          <i class="ti ti-plus"></i> Create
        </button>
      </form>
    </div>
  </details>

  <details class="card mb-3">
    <summary class="card-header h5 mb-0">Maintenance</summary>
    <div class="card-body">
      <p class="text-muted">Remove unused categories from this event. Review the confirmation before removing empty categories.</p>
      <button class="btn btn-outline-danger btn-sm" id="cleanupCategoriesBtn" data-url="{{ route('admin.categories.cleanup', $event) }}">
        <i class="ti ti-trash me-1"></i>Remove Empty Categories
      </button>
    </div>
  </details>
</div>

</div>

@if($event->isIndividual())
<div class="modal fade" id="allocateCourtsModal" tabindex="-1" aria-labelledby="allocateCourtsTitle" aria-describedby="allocateCourtsDivision" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="allocateCourtsForm">
      <div class="modal-header">
        <h2 class="modal-title h5" id="allocateCourtsTitle">Allocate courts</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="allocateCourtsDivision" class="fw-semibold"></p>
        <label for="allocateCourtsFormat" class="form-label">Ball format</label>
        <select id="allocateCourtsFormat" class="form-select mb-3" aria-describedby="allocateCourtsFormatHelp">
          <option value="red">Red</option>
          <option value="orange" selected>Orange</option>
          <option value="green">Green</option>
          <option value="yellow">Yellow</option>
        </select>
        <p id="allocateCourtsFormatHelp" class="small text-muted"></p>
        <label for="allocateCourtsCount" class="form-label">Normal courts allocated to this division</label>
        <input id="allocateCourtsCount" class="form-control" type="number" min="1" max="25000" step="1" value="1" required aria-describedby="allocateCourtsSharingHelp">
        <p id="allocateCourtsSharingHelp" class="small text-muted mt-2">Allocate shared venue courts once across divisions. This calculator does not reserve courts.</p>
        <div class="alert alert-primary mb-2" role="status" aria-live="polite" aria-atomic="true" id="allocateCourtsResult"></div>
        <p class="small mb-0">Use cap fills the division's maximum entries. Then select <strong>Save cap</strong> on that division to save it.</p>
      </div>
      <div class="modal-footer flex-wrap">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="allocateCourtsApply">Use cap</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection


{{-- =========================
   PASS CONFIG TO MIX JS
========================= --}}
@section('page-script')

<script>
(function () {
  const modal = document.getElementById('allocateCourtsModal');
  if (!modal) return;
  const form = document.getElementById('allocateCourtsForm');
  const format = document.getElementById('allocateCourtsFormat');
  const courts = document.getElementById('allocateCourtsCount');
  const result = document.getElementById('allocateCourtsResult');
  const apply = document.getElementById('allocateCourtsApply');
  let origin = null;

  function updateCalculation() {
    const red = format.value === 'red';
    courts.max = red ? '6250' : '25000';
    document.getElementById('allocateCourtsFormatHelp').textContent = red
      ? 'Each normal court holds four red mini-courts, with four players on each: 16 players per normal court.'
      : `${format.options[format.selectedIndex].text} uses four players per normal court.`;
    const valid = courts.checkValidity();
    apply.disabled = !valid;
    if (!valid) {
      result.textContent = `Enter a whole number of courts between 1 and ${courts.max}.`;
      return;
    }
    const count = Number(courts.value);
    const total = count * (red ? 16 : 4);
    result.textContent = red
      ? `${count} normal ${count === 1 ? 'court' : 'courts'} × 4 mini-courts × 4 players = ${total} players`
      : `${count} ${count === 1 ? 'court' : 'courts'} × 4 players = ${total} players`;
  }

  modal.addEventListener('show.bs.modal', function (event) {
    origin = event.relatedTarget;
    if (!origin?.dataset.capInput) { event.preventDefault(); return; }
    document.getElementById('allocateCourtsDivision').textContent = origin.dataset.divisionName;
    format.value = origin.dataset.ballFormat || 'orange';
    courts.value = origin.dataset.courtCount || '1';
    updateCalculation();
  });
  modal.addEventListener('shown.bs.modal', function () { format.focus(); });
  format.addEventListener('change', updateCalculation);
  courts.addEventListener('input', updateCalculation);
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.reportValidity() || !origin) return;
    const input = document.getElementById(origin.dataset.capInput);
    if (!input) return;
    input.value = Number(courts.value) * (format.value === 'red' ? 16 : 4);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    origin.dataset.ballFormat = format.value;
    origin.dataset.courtCount = courts.value;
    bootstrap.Modal.getInstance(modal).hide();
  });
})();
window.categoryConfig = {
    attachedIds: @json($attachedCategoryIds),
    feeUpdateUrl: "{{ route('admin.events.category-fee.update', ':id') }}"
};
</script>

<script>
if (window.toastr) {
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 2000
    };
}

(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  async function deleteRequest(url) {
    const res = await fetch(url, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json'
      }
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      throw new Error(data.message || 'Request failed');
    }

    return data;
  }

  document.addEventListener('click', async function (e) {
    const removeBtn = e.target.closest('.delete-category-btn');
    if (removeBtn) {
      e.preventDefault();
      if (!confirm('Remove this category from the event?')) return;

      try {
        await deleteRequest(removeBtn.dataset.url);
        const row = removeBtn.closest('tr');
        if (row) row.remove();
        if (window.toastr) toastr.success('Category removed successfully.');
      } catch (err) {
        if (window.toastr) toastr.error(err.message || 'Could not remove category.');
      }
      return;
    }

    const cleanupBtn = e.target.closest('#cleanupCategoriesBtn');
    if (cleanupBtn) {
      e.preventDefault();
      if (!confirm('Remove all empty categories from this event?')) return;

      try {
        const result = await deleteRequest(cleanupBtn.dataset.url);
        if (window.toastr) toastr.success(`${result.removed ?? 0} empty categories removed.`);
        window.location.reload();
      } catch (err) {
        if (window.toastr) toastr.error(err.message || 'Cleanup failed.');
      }
    }
  });
})();
</script>

<script src="{{ asset(mix('js/eventCategories.js')) }}"></script>

@endsection
