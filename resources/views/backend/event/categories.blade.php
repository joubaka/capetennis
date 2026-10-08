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
    'eventWorkspaceActive' => 'more',
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
              <td colspan="4" class="text-center text-muted py-3">
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
@endsection


{{-- =========================
   PASS CONFIG TO MIX JS
========================= --}}
@section('page-script')

<script>
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
