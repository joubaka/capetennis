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
@php
  $isAdmin = auth()->check() && in_array(auth()->id(), [1764, 584, 585, 763]);
@endphp
{{-- resources/views/frontend/event/eventTypes/interpro.blade.php --}}
<div class="col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      {{-- 🔹 Announcements --}}
      <div class="card p-4 mb-4">
        <h5 class="pb-4 mb-4 border-bottom">Announcements</h5>

        @forelse($event->announcements as $a)
          <div class="card shadow-none bg-transparent border border-primary mb-4">
            <div class="card-body">
              <p class="card-text">{!! $a->message !!}</p>
              <small class="text-muted">
                <mark>Announcement @ {{ optional($a->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}</mark>
              </small>
            </div>
          </div>
        @empty
          <div class="alert alert-info mb-0">No announcements yet.</div>
        @endforelse
      </div>

      {{-- 🔹 Information --}}
      <div class="card p-4 mb-4">
        <h5 class="pb-1 mb-4 border-bottom">Information</h5>
        {!! $event->information ?: '<div class="text-muted">No additional information provided.</div>' !!}
      </div>

   
      {{-- 🔹 MObile version only --}}
      @include('frontend.event.partials.interpro-draws-mobile')

      {{-- 🔹 Regions & Teams Tabs --}}
      <div class="card p-4">
        <div class="card-body pb-0">
          <div class="badge bg-label-primary mb-3" role="alert">
            Click on a Region below to register
          </div>
        </div>

        @php $regions = $event->region_in_events ?? collect(); @endphp

        @if($regions->isNotEmpty())
          <div class="nav-align-top nav-tabs-shadow">
            <ul class="nav nav-tabs flex-nowrap overflow-auto" role="tablist">
              @foreach($regions as $idx => $region)
                @php $tabId = 'team' . $region->id; @endphp
                <li class="nav-item" role="presentation">
                  <button type="button" class="nav-link {{ $idx === 0 ? 'active' : '' }}"
                          data-bs-toggle="tab" data-bs-target="#{{ $tabId }}">
                    {{ $region->region_name }}
                  </button>
                </li>
              @endforeach
            </ul>

            <div class="tab-content pt-3">
              @foreach($regions as $idx => $region)
                @php $tabId = 'team' . $region->id; @endphp
                <div class="tab-pane fade {{ $idx === 0 ? 'active show' : '' }}"
                     id="{{ $tabId }}">
                  <div class="row">
                    @forelse($region->teams as $team)
                      @if(($team->noProfile ?? 0) != 1)
                        @include('frontend.event.partials.profile-team', ['team' => $team])
                      @else
                        @include('frontend.event.partials.no-profile-team', ['team' => $team])
                      @endif
                    @empty
                      <div class="col-12">
                        <div class="alert alert-secondary mb-0">
                          No teams listed for this region yet.
                        </div>
                      </div>
                    @endforelse
                  </div>
                </div>
              @endforeach
            </div>

          </div>

        @else
          <div class="alert alert-secondary mt-3">
            Regions are not configured for this event.
          </div>
        @endif

      </div>
    </div>


    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      {{-- 🔹 About --}}
      <div class="card mb-4">
        <div class="card-body">
          <small class="card-text text-uppercase">About</small>

          <ul class="list-unstyled mb-4 mt-3">
            <li class="d-flex align-items-center mb-3">
              <i class="fa-regular fa-calendar"></i>
              <span class="fw-bold mx-2">Start Date:</span>
              <span class="badge bg-label-success">{{ $sDate }}</span>
            </li>

            <li class="d-flex align-items-center mb-3">
              <i class="fa-regular fa-calendar"></i>
              <span class="fw-bold mx-2">End Date:</span>
              <span class="badge bg-label-success">{{ $eDate }}</span>
            </li>

            @forelse($event->eventCategories as $ce)
              <li class="d-flex align-items-center mb-3">
                <i class="ti ti-flag"></i>
                <span class="fw-bold mx-2">{{ $ce->category->name }}</span>
                <span>R{{ number_format((float)$ce->entry_fee, 2) }}</span>
              </li>
            @empty
              <li class="d-flex align-items-center mb-3">
                <i class="ti ti-flag"></i>
                <span class="fw-bold mx-2">Entry Fee:</span>
                <span>R{{ number_format((float)$event->entryFee, 2) }}</span>
              </li>
            @endforelse
          </ul>

          <small class="card-text text-uppercase">Contact</small>

          <ul class="list-unstyled mt-3 mb-0">
            <li class="d-flex align-items-center mb-3">
              <i class="ti ti-phone-call"></i>
              <span class="fw-bold mx-2">Organizer:</span>
              <span>{{ $event->organizer }}</span>
            </li>

            <li class="d-flex align-items-center">
              <i class="ti ti-mail"></i>
              <span class="fw-bold mx-2">Email:</span>

              @if($event->email)
                <a href="mailto:{{ $event->email }}">{{ $event->email }}</a>
              @else
                <span class="text-muted">Not provided</span>
              @endif

            </li>
          </ul>
        </div>
      </div>

   

      {{-- 🔹 Documents --}}
      <div class="card mb-4 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> Documents
          </h6>

          @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
            <form action="{{ route('file.store') }}" method="POST" enctype="multipart/form-data"
                  class="d-flex align-items-center gap-2 mb-0">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">

              <label class="btn btn-sm btn-outline-primary mb-0">
                <i class="ti ti-upload me-1"></i> Upload
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

              <div class="d-flex align-items-center">
                <i class="ti ti-file-description text-primary fs-5 me-2"></i>
                <a href="{{ route('file.show', $file->id) }}" target="_blank"
                   class="fw-semibold text-dark text-decoration-none">
                  {{ $file->name }}
                </a>
              </div>

              <div class="d-flex align-items-center">
                @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
                  <button type="button" data-id="{{ $file->id }}"
                          class="btn btn-sm btn-icon btn-outline-danger deleteFileButton"
                          data-bs-toggle="tooltip" title="Delete">
                    <i class="ti ti-trash"></i>
                  </button>
                @endif
              </div>
            </div>
          @empty
            <div class="text-muted">No documents uploaded yet.</div>
          @endforelse

        </div>

      </div>


      {{-- 🔹 Draws and Order of Play (Desktop only) --}}
      @include('frontend.event.partials.interpro-draws-desktop')

      {{-- 🔹 My Clothing Orders --}}
      @includeWhen(isset($myClothingOrders) && $myClothingOrders->isNotEmpty(),
          'frontend.event.partials._my_clothing_orders')

    </div>
  </div>
</div>


{{-- 🔹 SweetAlert2-based File Delete --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.deleteFileButton').forEach(btn => {
    btn.addEventListener('click', function () {
      const fileId = this.dataset.id;
      const button = this;

      Swal.fire({
        title: 'Delete this file?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        customClass: {
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-secondary ms-2'
        },
        buttonsStyling: false
      }).then(result => {
        if (result.isConfirmed) {

          fetch(`{{ url('/file') }}/${fileId}`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ '_method': 'DELETE' })
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              Swal.fire('Deleted!', 'File removed successfully.', 'success');
              button.closest('.file-item').remove();
            } else {
              Swal.fire('Error', data.msg || 'Delete failed.', 'error');
            }
          })
          .catch(err => {
            console.error('Delete error:', err);
            Swal.fire('Error', 'Something went wrong.', 'error');
          });

        }
      });
    });
  });
});
</script>
