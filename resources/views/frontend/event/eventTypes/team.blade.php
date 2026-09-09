



<style>
.file-item:hover {
  background-color: #f8f9fa;
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.card .btn-outline-primary {
  border-radius: 50px;
  font-size: 0.875rem;
  padding: 0.25rem 0.75rem;
}

.card .btn-outline-primary:hover {
  background-color: #0d6efd;
  color: #fff;
}
</style>

{{-- resources/views/frontend/event/eventTypes/team.blade.php --}}
@php
  $regions = $event->regions ?? collect();
@endphp

<div class="col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      @include('frontend.event.partials.event-information')
      @include('frontend.event.partials.event-announcements')

      {{-- 🔹 Draws and Order of Play (Mobile / Tablet only) --}}
      <div class="card d-block d-md-none mb-4">
        <div class="card-body">
          @include('frontend.event.partials._draws_and_order_of_play')
        </div>
      </div>

      {{-- 🔹 Regions & Teams --}}
      <div class="card p-4">
        <div class="card-body pb-0">
          <div class="badge bg-label-primary mb-3">
            Click on a Region below to register
          </div>
        </div>

        @if($regions->isNotEmpty())
          @include('frontend.event.partials._region_team_picker', ['regions' => $regions])
        @else
          <div class="alert alert-secondary mt-3">
            Regions are not configured for this event.
          </div>
        @endif
      </div>
    </div>

    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      @include('frontend.event.partials.event-about')

      {{-- 🔹 Documents --}}
      <div class="card mb-4 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> Documents
          </h6>

          @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
            <form action="{{ route('file.store') }}" method="POST"
                  enctype="multipart/form-data" class="mb-0">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <label class="btn btn-sm btn-outline-primary mb-0">
                Upload
                <input type="file" name="myFile" class="d-none"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                       onchange="this.form.submit()">
              </label>
            </form>
          @endif
        </div>

        <div class="card-body pb-2">
          @forelse($event->files as $file)
            <div class="file-item border-bottom py-2 d-flex justify-content-between align-items-center">
              <a href="{{ route('events.documents.show', [$event, $file]) }}" target="_blank"
                 class="fw-semibold text-dark text-decoration-none">
                {{ $file->name }}
              </a>

              @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
                <button class="btn btn-sm btn-outline-danger deleteFileButton"
                        data-id="{{ $file->id }}">
                  <i class="ti ti-trash"></i>
                </button>
              @endif
            </div>
          @empty
            <div class="text-muted">No documents uploaded yet.</div>
          @endforelse
        </div>
      </div>

      {{-- 🔹 Draws and Order of Play (Desktop only) --}}
      <div class="d-none d-md-block">
        @include('frontend.event.partials._draws_and_order_of_play')
      </div>

    </div>
  </div>
</div>
{{-- 🔹 Clothing Order Modal --}}
{{-- Clothing Order Modal --}}
@include('frontend.event.partials._clothing_order_modal')




