@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Categories')

{{-- =========================
   VENDOR STYLES
========================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<style>
  .select2-container .select2-selection--multiple {
    min-height: 38px;
    border: 1px solid #d9dee3;
  }

  select.select2 {
    display: none;
  }


</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Manage Categories</h4>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-danger btn-sm" id="cleanupCategoriesBtn">
          <i class="ti ti-trash me-1"></i>Remove Empty Categories
        </button>

        <a href="{{ route('admin.events.overview', $event) }}"
           class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back to Event
        </a>
      </div>
    </div>
  </div>

  @php
    $attachedCategoryIds = $categoryEvents
      ->pluck('category_id')
      ->values()
      ->all();
  @endphp

  {{-- ADD EXISTING CATEGORY --}}
  <div class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0">Add Existing Category</h5>
    </div>

    <div class="card-body">
      <form method="POST"
            action="{{ route('admin.categories.attach', $event) }}"
            class="d-flex gap-2 align-items-start">
        @csrf

        <div class="flex-grow-1">
          <select name="category_ids[]"
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
  <div class="card mb-4 border-start border-success border-3">
    <div class="card-header">
      <h5 class="mb-0">Create New Category</h5>
    </div>

    <div class="card-body">
      <form method="POST"
            action="{{ route('admin.categories.create', $event) }}"
            class="d-flex gap-2">
        @csrf

        <input type="text"
               name="name"
               class="form-control"
               placeholder="e.g. U14 Boys"
               required>

        <button class="btn btn-success">
          <i class="ti ti-plus"></i> Create
        </button>
      </form>
    </div>
  </div>

  {{-- CATEGORY LIST --}}
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>Category</th>
            <th class="text-center">Entries</th>
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
              <td colspan="3" class="text-center text-muted py-3">
                No categories attached to this event.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection

{{-- =========================
   VENDOR SCRIPTS
========================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {

  const attachedIds = @json($attachedCategoryIds);

  console.group('Category Select2 Debug');
  console.log('Attached IDs:', attachedIds);

  const $select = $('.select2');
  console.log('Select found:', $select.length);
  console.log('Total options:', $select.find('option').length);

  $select.select2({
    width: '100%',
    placeholder: function () {
      return $(this).data('placeholder');
    },
    closeOnSelect: false
  });

  // FORCE selection
  $select.val(attachedIds).trigger('change');
  console.log('Selected after .val():', $select.val());

  // Disable attached categories
  attachedIds.forEach(id => {
    const $opt = $select.find(`option[value="${id}"]`);
    console.log(`Option ${id} exists:`, $opt.length);
    $opt.prop('disabled', true);
  });

  $select.trigger('change.select2');

  // Clear search after select
  $select.on('select2:select', function () {
    const search = $(this)
      .data('select2')
      .$dropdown
      .find('.select2-search__field');
    if (search.length) {
      search.val('');
      console.log('Search cleared');
    }
  });

  // Prevent disabled options submitting
  $select.closest('form').on('submit', function () {
    $select.find('option:disabled').prop('selected', false);
    console.log('Disabled options stripped before submit');
  });

  console.groupEnd();
});
</script>
@endsection
