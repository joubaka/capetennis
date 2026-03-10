
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

      {{-- DRAWS & ORDER OF PLAY --}}
    @if(isset($eventDraws) && $eventDraws->count() > 0)
    <div class="card mb-4">
      <div class="card-header">
        <small class="text-uppercase">Draws & Order of Play</small>
      </div>
      <div class="card-body">
<div class="d-flex flex-wrap gap-2">
@php
  $sortedDraws = $eventDraws->sortBy([
    ['published', 'desc'],
    [fn($d) => $d->draw_types?->ageCategory ?? $d->drawName ?? '', 'asc'],
    ['drawName', 'asc'],
  ]);

  $isConvenorOrSuper = auth()->check() && (
    (method_exists(auth()->user(), 'isConvenorForEvent') && auth()->user()->isConvenorForEvent($event->id))
    || (method_exists(auth()->user(), 'hasRole') && (auth()->user()->hasRole('convenor') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-user')))
  );
@endphp

@foreach($sortedDraws as $draw)
  <div class="d-flex align-items-center gap-1">

    {{-- PUBLISHED --}}
    @if($draw->published)
      <a href="{{ route('public.roundrobin.show', $draw->id) }}"
         class="btn btn-sm btn-success">
        <i class="ti ti-tournament me-1"></i>
        {{ $draw->drawName ?? 'Draw #'.$draw->id }}
      </a>

      @if($isConvenorOrSuper)
        <a href="{{ route('frontend.fixtures.enter-scores', ['draw' => $draw->id]) }}"
           class="btn btn-sm btn-light border"
           title="Insert Score">
          <i class="bi bi-clipboard-data"></i>
        </a>
      @endif

    {{-- UNPUBLISHED --}}
    @else

      @if($isConvenorOrSuper)
        {{-- Convenor/Admin/Super can open --}}
        <a href="{{ route('public.roundrobin.show', $draw->id) }}"
           class="btn btn-sm btn-outline-secondary">
          <i class="ti ti-tournament me-1"></i>
          {{ $draw->drawName ?? 'Draw #'.$draw->id }}
          <span class="badge bg-danger ms-1">Unpublished</span>
        </a>
      @else
        {{-- Others see button but cannot open --}}
        <span class="btn btn-sm btn-outline-secondary disabled">
          <i class="ti ti-tournament me-1"></i>
          {{ $draw->drawName ?? 'Draw #'.$draw->id }}
          <span class="badge bg-danger ms-1">Unpublished</span>
        </span>
      @endif

    @endif

  </div>
@endforeach
</div>
      </div>
    </div>
    @endif

    {{-- PLAYERS --}}
    <div class="card mb-4">
      <div class="card-body">
        <small class="text-uppercase">Players</small>

        @foreach($eventCats as $eventCategory)
          @php
            $activeRegistrations = $eventCategory->registrations->where('status', '!=', 'withdrawn');
          @endphp
          <div class="border rounded p-2 mb-3">
            <span class="badge bg-label-primary mb-2">
              {{ $eventCategory->category->name }}
              ({{ $activeRegistrations->count() }})
            </span>

            <ul class="list-group list-group-flush">
              @foreach($activeRegistrations as $registration)
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<div class="modal fade" id="addFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Upload Document</h5>
        <button type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>

      <form method="POST"
            action="{{ route('file.store') }}"
            enctype="multipart/form-data">

        @csrf

        <div class="modal-body">

          {{-- REQUIRED BY CONTROLLER --}}
          <input type="hidden" name="event_id" value="{{ $event->id }}">

          <div class="mb-3">
            <label class="form-label">Select file</label>
            <input type="file"
                   name="myFile"
                   class="form-control"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                   required>
            <small class="text-muted">
              Allowed: PDF, Word, Excel (max 5MB)
            </small>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit"
                  class="btn btn-success">
            Upload
          </button>
        </div>

      </form>

    </div>
  </div>
</div>





