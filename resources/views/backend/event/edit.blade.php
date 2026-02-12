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
<div class="container-xl">

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

