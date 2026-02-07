
<div class="row">

  {{-- LEFT COLUMN --}}
  <div class="col-xl-8 col-lg-7 col-md-7">

    {{-- ANNOUNCEMENTS --}}
    <div class="card p-4 mb-4">
      <h5 class="mb-4">Announcements</h5>

      @forelse($event->announcements as $a)
        <div class="card shadow-none border border-primary mb-3">
          <div class="card-body">
            {!! $a->message !!}
            <p class="mt-2">
              <small class="text-muted">
                <mark>Announcement · {{ $a->created_at->format('d M Y H:i') }}</mark>
              </small>
            </p>
          </div>
        </div>
      @empty
        <p class="text-muted">No announcements yet.</p>
      @endforelse
    </div>

    {{-- INFORMATION --}}
    <div class="card p-4 mb-4">
      <h5 class="mb-3">Information</h5>
      {!! $event->information !!}
    </div>

  </div>

  {{-- RIGHT COLUMN --}}
  <div class="col-xl-4 col-lg-5 col-md-5">

  {{-- ABOUT --}}
@if(
  $sDate ||
  $eDate ||
  $formatEntryLine ||
  $formatWithdrawalLine ||
  $event->organizer ||
  $event->email
)
<div class="card mb-4">
  <div class="card-body">
    <small class="text-uppercase">About</small>

    <ul class="list-unstyled mt-3">

      @if($sDate)
        <li class="mb-2">
          <strong>Start:</strong>
          <span class="badge bg-label-success">{{ $sDate }}</span>
        </li>
      @endif

      @if($eDate)
        <li class="mb-2">
          <strong>End:</strong>
          <span class="badge bg-label-success">{{ $eDate }}</span>
        </li>
      @endif

      @if($formatEntryLine)
        <li class="mb-2">
          <strong>Entry deadline:</strong>
          <span class="badge bg-label-warning">{{ $formatEntryLine }}</span>
        </li>
      @endif

      @if($formatWithdrawalLine)
        <li class="mb-2">
          <strong>Withdrawal deadline:</strong>
          <span class="badge bg-label-danger">{{ $formatWithdrawalLine }}</span>
        </li>
      @endif

    </ul>

    @if($event->organizer || $event->email)
      <small class="text-uppercase">Contact</small>

      <ul class="list-unstyled mt-3">

        @if($event->organizer)
          <li class="mb-2">
            <strong>Organizer:</strong> {{ $event->organizer }}
          </li>
        @endif

        @if($event->email)
          <li class="mb-2">
            <strong>Email:</strong>
            <a href="mailto:{{ $event->email }}">{{ $event->email }}</a>
          </li>
        @endif

      </ul>
    @endif
  </div>
</div>
@endif

    {{-- DOCUMENTS --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between">
        <small class="text-uppercase">Documents</small>

        @auth
          @if(auth()->user()->is_admin($event->id)->count() > 0 || auth()->id() == 584)
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFileModal">
              Upload PDF
            </button>
          @endif
        @endauth
      </div>

      <div class="card-body">
        @forelse($event->files as $file)
          <div class="d-flex justify-content-between align-items-center mb-2 file">
            <a href="{{ route('file.show', $file->id) }}">{{ $file->name }}</a>

            @can('admin')
              @if(auth()->id() == $event->admin || auth()->id() == 584)
                <button
                  class="btn btn-danger btn-sm deleteFileButton"
                  data-id="{{ $file->id }}">
                  Delete
                </button>
              @endif
            @endcan
          </div>
        @empty
          <p class="text-muted">No documents uploaded.</p>
        @endforelse
      </div>
    </div>

    {{-- PLAYERS --}}
    <div class="card mb-4">
      <div class="card-body">
        <small class="text-uppercase">Players</small>

        @foreach($eventCats as $eventCategory)
          <div class="border rounded p-2 mb-3">
            <span class="badge bg-label-primary mb-2">
              {{ $eventCategory->category->name }}
              ({{ $eventCategory->registrations->count() }})
            </span>

            <ul class="list-group list-group-flush">
              @foreach($eventCategory->registrations as $registration)
                <li class="list-group-item">
                  {{ optional($registration->players->first())->name }}
                  {{ optional($registration->players->first())->surname }}
                </li>
              @endforeach
            </ul>
          </div>
        @endforeach
      </div>
    </div>

  </div>
</div>



<script>
$(document).on('click', '.deleteFileButton', function (e) {
  e.preventDefault();

  const fileId = $(this).data('id');
  if (!fileId) return;

  const url = "{{ route('file.destroy', '__ID__') }}".replace('__ID__', fileId);

  Swal.fire({
    title: 'Delete file?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete'
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.post(url, {
      _method: 'DELETE',
      _token: $('meta[name="csrf-token"]').attr('content')
    }).done(() => {
      location.reload();
    });
  });
});

