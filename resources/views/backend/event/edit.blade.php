@extends('layouts/layoutMaster')

@section('title', 'Edit Event')

{{-- =========================
   VENDOR STYLES
========================= --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.min.css') }}">
@endsection


{{-- =========================
   VENDOR SCRIPTS
========================= --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>

@endsection


@section('content')
<div class="container-xl event-edit-page">

  <style>
    .event-edit-page .card { border: 1px solid #ebeaf0; box-shadow: 0 .25rem 1rem rgba(47,43,61,.05); }
    .event-edit-page .card-header { background: #fff; border-bottom: 1px solid #ebeaf0; }
    .event-edit-page .setup-status { display:inline-flex; align-items:center; gap:.35rem; font-size:.75rem; font-weight:600; border-radius:999px; padding:.35rem .65rem; }
    .event-edit-page .setup-status.ready { background:#e8f8ef; color:#198754; }
    .event-edit-page .setup-status.warning { background:#fff4dd; color:#9a6700; }
    .event-edit-page .setup-status.blocked { background:#fde8e7; color:#b42318; }
    .event-edit-page .mapping-row { border-bottom:1px solid #f0eff3; padding:.65rem 0; }
    .event-edit-page .mapping-row:last-child { border-bottom:0; }
  </style>

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Edit Event</h4>

    @if($event->series_id)
      <a href="{{ route('series.events', $event->series_id) }}"
         class="btn btn-outline-secondary">
        Back to Series
      </a>
    @endif
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-1">Series and category setup</h5>
            <p class="text-muted small mb-0">Confirm the event is connected to the correct ranking structure.</p>
          </div>
          @if($event->series)
            <span class="setup-status ready"><i class="ti ti-check"></i> Series linked</span>
          @else
            <span class="setup-status blocked"><i class="ti ti-alert-circle"></i> No series linked</span>
          @endif
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-7"><span class="text-muted small d-block">Parent series</span><strong>{{ $event->series?->name ?? 'Not linked' }}</strong></div>
            <div class="col-md-2"><span class="text-muted small d-block">Series year</span><strong>{{ $event->series?->year ?? '—' }}</strong></div>
            <div class="col-md-3"><span class="text-muted small d-block">Ranking type</span><strong>{{ $event->series?->rank_type ?? '—' }}</strong></div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Configured categories</h6>
            <span class="badge bg-label-secondary">{{ $event->categoryEvents->count() }}</span>
          </div>
          @forelse($event->categoryEvents as $categoryEvent)
            <div class="mapping-row d-flex justify-content-between align-items-center">
              <div><strong>{{ $categoryEvent->category?->name ?? 'Unnamed category' }}</strong><span class="text-muted small ms-2">Category event #{{ $categoryEvent->id }}</span></div>
              <span class="text-muted">R {{ number_format((float)($categoryEvent->entry_fee ?? $event->entryFee ?? 0), 2) }}</span>
            </div>
          @empty
            <div class="alert alert-danger mb-0">Blocked: no age-group categories are attached to this event.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div><h5 class="mb-1">Masters readiness</h5><p class="text-muted small mb-0">Invitation and replacement status.</p></div>
          @if($mastersReadiness)
            <span class="setup-status {{ $mastersReadiness['status'] }}">{{ ucfirst($mastersReadiness['status']) }}</span>
          @else
            <span class="setup-status warning">Not generated</span>
          @endif
        </div>
        <div class="card-body">
          @if(!$event->series)
            <p class="text-danger mb-2"><i class="ti ti-alert-circle me-1"></i> Link this event to a series first.</p>
          @elseif($event->categoryEvents->isEmpty())
            <p class="text-danger mb-2"><i class="ti ti-alert-circle me-1"></i> Add at least one Masters age group.</p>
          @elseif(!$mastersBatch)
            <p class="text-warning mb-3"><i class="ti ti-alert-triangle me-1"></i> Event exists, but no invitation batch has been generated.</p>
            <a href="{{ route('series.events', $event->series_id) }}" class="btn btn-outline-primary btn-sm">Return to series setup</a>
          @else
            <p class="small mb-2">Batch #{{ $mastersBatch->id }} · ranking run <code>{{ $mastersBatch->ranking_run_id }}</code></p>
            @foreach($mastersReadiness['groups'] as $group)
              <div class="d-flex justify-content-between border-bottom py-2 small"><span>{{ $group['label'] ?? 'Age group' }}</span><strong class="{{ $group['status'] === 'blocked' ? 'text-danger' : ($group['status'] === 'warning' ? 'text-warning' : 'text-success') }}">{{ ucfirst($group['status']) }}</strong></div>
            @endforeach
            <a href="{{ route('backend.masters.show', $mastersBatch) }}" class="btn btn-primary btn-sm mt-3">Open Masters readiness</a>
          @endif
        </div>
      </div>
    </div>
  </div>

  <form id="event-edit-form"
        method="POST"
        action="{{ route('backend.events.update', $event) }}"
        enctype="multipart/form-data">

    @csrf
    @method('PATCH')

    <div class="row g-4">

      {{-- ================= LEFT SIDE ================= --}}
      <div class="col-xl-8">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Event Details</h5>
          </div>

          <div class="card-body">

            {{-- Name --}}
            <div class="mb-3">
              <label class="form-label">Event Name</label>
              <input name="name"
                     class="form-control"
                     value="{{ old('name', $event->name) }}"
                     required>
            </div>

            {{-- Dates --}}
            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Start Date</label>
                <input type="date"
                       name="start_date"
                       class="form-control"
                       value="{{ optional($event->start_date)->format('Y-m-d') }}">
              </div>
              <div class="col">
                <label class="form-label">End Date</label>
                <input type="date"
                       name="end_date"
                       class="form-control"
                       value="{{ optional($event->end_date)->format('Y-m-d') }}">
              </div>
            </div>

            {{-- Event Type --}}
            <div class="mb-3">
              <label class="form-label">Event Type</label>
              <select name="eventType" class="form-select" required>
                @foreach($eventTypes as $type)
                  <option value="{{ $type->id }}"
                    @selected($event->eventType == $type->id)>
                    {{ $type->type }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- QUILL --}}
            <div class="mb-3">
              <label class="form-label">Information</label>
              <div id="information-editor" class="border rounded">
                {!! old('information', $event->information) !!}
              </div>

              <input type="hidden"
                     name="information"
                     id="information-input"
                     value="{{ old('information', $event->information) }}">
            </div>

            {{-- Venue Notes --}}
            <div class="mb-3">
              <label class="form-label">Venue Notes</label>
              <textarea name="venue_notes"
                        rows="3"
                        class="form-control">{{ old('venue_notes', $event->venue_notes) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ================= RIGHT SIDE ================= --}}
      <div class="col-xl-4">

        {{-- LOGO --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Event Logo</h5>
          </div>

          <div class="card-body">

            <div class="mb-3">
              <img id="logo-preview"
                   src="{{ $event->logo ? asset('assets/img/logos/'.$event->logo) : '' }}"
                   class="img-thumbnail {{ $event->logo ? '' : 'd-none' }}"
                   style="max-height:120px">
            </div>

            <div class="mb-3">
              <label class="form-label">Select Existing Logo</label>
              <select name="logo_existing" class="form-select">
                <option value="">— Select existing logo —</option>
                @foreach(File::files(public_path('assets/img/logos')) as $file)
                  <option value="{{ $file->getFilename() }}"
                    @selected($event->logo === $file->getFilename())>
                    {{ $file->getFilename() }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Upload New Logo</label>
              <input type="file"
                     name="logo_upload"
                     class="form-control"
                     accept="image/*">
            </div>

          </div>
        </div>

        {{-- SETTINGS --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Settings</h5>
          </div>

          <div class="card-body">

            {{-- Entry Fee --}}
            <div class="mb-3">
              <label class="form-label">Entry Fee</label>
              <input type="number"
                     name="entryFee"
                     class="form-control"
                     value="{{ old('entryFee', $event->entryFee) }}">
            </div>

            {{-- Deadline --}}
            <div class="mb-3">
              <label class="form-label">Deadline (days before start)</label>
              <input type="number"
                     name="deadline"
                     class="form-control"
                     value="{{ old('deadline', $event->deadline) }}">
            </div>

            {{-- Withdrawal Deadline --}}
            <div class="mb-3">
              <label class="form-label">Withdrawal Deadline</label>
              <input type="datetime-local"
                     name="withdrawal_deadline"
                     class="form-control"
                     value="{{ optional($event->withdrawal_deadline)->format('Y-m-d\TH:i') }}">
            </div>

            {{-- Organizer --}}
            <div class="mb-3">
              <label class="form-label">Organizer</label>
              <input type="text"
                     name="organizer"
                     class="form-control"
                     value="{{ old('organizer', $event->organizer) }}">
            </div>

            {{-- Email --}}
            <div class="mb-3">
              <label class="form-label">Contact Email</label>
              <input type="email"
                     name="email"
                     class="form-control"
                     value="{{ old('email', $event->email) }}">
            </div>

            {{-- Admins --}}
            <div class="mb-3">
              <label class="form-label">Event Admins</label>
              <select name="admins[]"
                      class="form-select select2"
                      multiple
                      data-placeholder="Select admins">
                @foreach($users as $user)
                  <option value="{{ $user->id }}"
                    @selected(in_array($user->id, $adminIds))>
                    {{ $user->name }} ({{ $user->email }})
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Published --}}
            <div class="form-check mb-2">
              <input class="form-check-input"
                     type="checkbox"
                     name="published"
                     value="1"
                     @checked($event->published)>
              <label class="form-check-label">Published</label>
            </div>

            {{-- SignUp --}}
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     name="signUp"
                     value="1"
                     @checked($event->signUp)>
              <label class="form-check-label">Allow Sign-Up</label>
            </div>

          </div>
        </div>

      </div>
    </div>

    {{-- BUTTONS --}}
    <div class="d-flex justify-content-end mt-4 gap-2">
      <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary">
        Save Changes
      </button>
    </div>

  </form>
</div>
@endsection

@section('page-script')
<script>
window.eventConfig = {
    logoBaseUrl: "{{ asset('assets/img/logos') }}/"
};

if (window.toastr) {
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 2500
    };
}
</script>

<script src="{{ asset(mix('js/eventEdit.js')) }}"></script>
@endsection

