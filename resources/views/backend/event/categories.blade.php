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
    @if($event->isIndividual() && $categoryEvents->isNotEmpty())
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#allocateCourtsModal">Allocate courts</button>
    @endif
  </div>

  @php
    $attachedCategoryIds = $categoryEvents
      ->pluck('category_id')
      ->values()
      ->all();
  @endphp

  @error('entry_limit')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
  <p id="courtAllocationFeedback" role="status" aria-live="polite" class="text-success"></p>

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
                  <p class="small text-danger mb-0 mt-1 cap-save-error" role="alert"></p>
                  <p class="small text-muted mb-0 mt-1 cap-save-status" role="status"></p>
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
<div class="modal fade" id="allocateCourtsModal" tabindex="-1" aria-labelledby="allocateCourtsTitle" aria-describedby="allocateCourtsHelp" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="allocateCourtsForm" data-save-url="{{ route('admin.events.category-limits.update', $event) }}">
      <div class="modal-header">
        <h2 class="modal-title h5" id="allocateCourtsTitle">Allocate courts</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="allocateCourtsHelp">Enter normal courts beside each category, then select <strong>Calculate</strong>. Leave courts blank to keep that category's cap unchanged.</p>
        <p class="small text-muted">Red: 4 mini-courts × 4 players = 16 per normal court. Orange, Green and Yellow: 4 players per normal court. Allocate shared venue courts once across categories; this calculator does not reserve courts.</p>
        @foreach($categoryEvents as $division)
          @php
            preg_match('/\b(red|orange|green|yellow)\b/i', $division->category->name, $ballMatch);
            $suggestedBall = strtolower($ballMatch[1] ?? 'standard');
          @endphp
          <div class="row g-3 align-items-end border rounded p-2 mb-3 court-allocation-row" data-cap-input="entry-limit-{{ $division->id }}" data-category-event-id="{{ $division->id }}">
            <div class="col-12 col-md-3"><h3 class="h6 mb-2">{{ $division->category->name }}</h3><span class="small text-muted" id="current-cap-{{ $division->id }}">Current cap: {{ $division->entry_limit ?? 'Unlimited' }}</span></div>
            <div class="col-12 col-sm-6 col-md-3">
              <label for="court-format-{{ $division->id }}" class="form-label">Ball format</label>
              <select id="court-format-{{ $division->id }}" class="form-select court-format" aria-label="Ball format for {{ $division->category->name }}">
                <option value="standard" @selected($suggestedBall === 'standard')>Standard — 4 per court</option>
                @foreach(['red' => 'Red', 'orange' => 'Orange', 'green' => 'Green', 'yellow' => 'Yellow'] as $ball => $label)
                  <option value="{{ $ball }}" @selected($suggestedBall === $ball)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
              <label for="court-count-{{ $division->id }}" class="form-label">Normal courts</label>
              <input id="court-count-{{ $division->id }}" class="form-control court-count" type="number" min="1" max="{{ $suggestedBall === 'red' ? 6250 : 25000 }}" step="1" placeholder="Unchanged" aria-label="Normal courts for {{ $division->category->name }}">
            </div>
            <div class="col-12 col-md-4"><output class="court-result d-block small" for="court-format-{{ $division->id }} court-count-{{ $division->id }}">Unchanged</output></div>
          </div>
        @endforeach
        <p class="small mb-2">After reviewing the results, <strong>Use caps</strong> saves all calculated caps together. Categories with blank courts stay unchanged.</p>
        <p class="text-danger mb-2" role="alert" id="allocateCourtsError"></p>
        <div role="status" aria-live="polite" aria-atomic="true" id="allocateCourtsStatus">Enter courts to calculate caps.</div>
      </div>
      <div class="modal-footer flex-wrap">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-outline-primary">Calculate caps</button>
        <button type="button" class="btn btn-primary" id="allocateCourtsApply" disabled>Use caps</button>
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
  const rows = Array.from(modal.querySelectorAll('.court-allocation-row'));
  const status = document.getElementById('allocateCourtsStatus');
  const apply = document.getElementById('allocateCourtsApply');
  const error = document.getElementById('allocateCourtsError');
  let calculated = [];
  let saving = false;
  function invalidate() {
    if (saving) return;
    calculated = [];
    error.textContent = '';
    apply.disabled = true;
    rows.forEach(row => {
      const courts = row.querySelector('.court-count');
      courts.max = row.querySelector('.court-format').value === 'red' ? '6250' : '25000';
      row.querySelector('.court-result').textContent = courts.value === '' ? 'Unchanged' : 'Select Calculate';
    });
    status.textContent = 'Select Calculate to review the current allocations.';
  }
  form.addEventListener('input', invalidate);
  form.addEventListener('change', invalidate);
  modal.addEventListener('shown.bs.modal', function () { rows[0]?.querySelector('.court-count').focus(); });
  modal.addEventListener('hide.bs.modal', function (event) { if (saving) event.preventDefault(); });
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (saving) return;
    invalidate();
    if (!form.reportValidity()) return;
    calculated = rows.filter(row => row.querySelector('.court-count').value !== '').map(row => {
      const courts = Number(row.querySelector('.court-count').value);
      const red = row.querySelector('.court-format').value === 'red';
      const cap = courts * (red ? 16 : 4);
      row.querySelector('.court-result').textContent = red
        ? `${courts} normal ${courts === 1 ? 'court' : 'courts'} × 4 mini-courts × 4 players = ${cap} players`
        : `${courts} ${courts === 1 ? 'court' : 'courts'} × 4 players = ${cap} players`;
      return { inputId: row.dataset.capInput, categoryId: Number(row.dataset.categoryEventId), cap, courts };
    });
    apply.disabled = calculated.length === 0;
    const totalCourts = calculated.reduce((sum, row) => sum + row.courts, 0);
    const totalPlayers = calculated.reduce((sum, row) => sum + row.cap, 0);
    status.textContent = calculated.length ? `${calculated.length} category caps calculated: ${totalCourts} normal courts allocated, ${totalPlayers} players. Review the results, then select Use caps.` : 'Enter courts for at least one category; blank categories stay unchanged.';
  });
  apply.addEventListener('click', async function () {
    if (saving || apply.disabled || !calculated.length || !form.reportValidity()) return;
    const pending = calculated.slice();
    const inputs = pending.map(row => document.getElementById(row.inputId));
    if (inputs.some(input => !input || input.disabled)) {
      error.textContent = 'Wait for the current category save to finish, then try again.';
      return;
    }
    saving = true;
    error.textContent = '';
    const controls = [...form.querySelectorAll('input, select, button'), ...inputs.flatMap(input => [input, input.closest('form').querySelector('[type="submit"]')])];
    const previousStates = controls.map(control => control.disabled);
    controls.forEach(control => { control.disabled = true; });
    const label = apply.textContent;
    apply.textContent = 'Saving caps…';
    status.textContent = 'Saving all calculated caps…';
    let saved = false;
    try {
      const response = await fetch(form.dataset.saveUrl, {
        method: 'PATCH', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify({ limits: pending.map(row => ({ category_event_id: row.categoryId, entry_limit: row.cap })) })
      });
      const data = await response.json().catch(() => ({}));
      if (response.redirected || !response.ok || data.success !== true) {
        throw new Error(response.redirected || response.status === 419 ? 'Your session expired. Reload this page before saving.'
          : Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save these caps. Please try again.');
      }
      pending.forEach(({ inputId, cap }) => {
        const input = document.getElementById(inputId);
        input.value = cap;
        const rowForm = input.closest('form');
        rowForm.querySelector('.cap-save-status').textContent = 'Cap saved.';
        rowForm.querySelector('.cap-save-error').textContent = '';
        const current = document.getElementById(inputId.replace('entry-limit-', 'current-cap-'));
        if (current) current.textContent = `Current cap: ${cap}`;
      });
      document.getElementById('courtAllocationFeedback').textContent = `${pending.length} category caps saved.`;
      status.textContent = 'All calculated caps saved.';
      calculated = [];
      saved = true;
    } catch (failure) {
      error.textContent = failure.message || 'Could not save these caps. Please try again.';
      status.textContent = 'Caps were not confirmed saved. Your allocations are ready to retry.';
    } finally {
      saving = false;
      controls.forEach((control, index) => { control.disabled = previousStates[index]; });
      apply.textContent = label;
      apply.disabled = calculated.length === 0;
      if (saved) bootstrap.Modal.getInstance(modal).hide();
    }
  });
})();
// Save one cap without discarding the other categories' unsaved allocations.
(function () {
  document.querySelectorAll('.division-limit-form').forEach(function (form) {
    form.querySelector('[name="entry_limit"]').addEventListener('input', function () {
      form.querySelector('.cap-save-status').textContent = 'Unsaved cap.';
      form.querySelector('.cap-save-error').textContent = '';
    });
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      if (button.disabled || !form.reportValidity()) return;
      const input = form.querySelector('[name="entry_limit"]');
      const error = form.querySelector('.cap-save-error');
      const status = form.querySelector('.cap-save-status');
      const body = new FormData(form);
      const submittedValue = input.value;
      const label = button.textContent;
      button.disabled = true;
      button.textContent = 'Saving…';
      input.disabled = true;
      error.textContent = '';
      status.textContent = '';
      try {
        const response = await fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
        const data = await response.json().catch(() => ({}));
        if (response.redirected || !response.ok || data.success !== true) {
          throw new Error(response.redirected || response.status === 419
            ? 'Your session expired. Reload this page before saving.'
            : Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save this cap. Please try again.');
        }
        status.textContent = input.value === submittedValue ? 'Cap saved.' : 'Previous cap saved; current value is unsaved.';
        const current = document.getElementById(input.id.replace('entry-limit-', 'current-cap-'));
        if (current) current.textContent = `Current cap: ${data.entry_limit ?? 'Unlimited'}`;
      } catch (failure) {
        error.textContent = failure.message || 'Could not save this cap. Please try again.';
      } finally {
        button.disabled = false;
        button.textContent = label;
        input.disabled = false;
      }
    });
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
