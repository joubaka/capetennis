@extends('layouts.backend')

@section('title', $isCopy ? 'Copy Event' : 'Create Event')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.min.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ $isCopy ? 'Copy Event' : 'Create New Event' }}</h4>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger mb-3">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ route('backend.events.store') }}"
        enctype="multipart/form-data">

    @csrf

    @if ($isCopy)
      <input type="hidden" name="source_event_id" value="{{ $sourceEvent?->id }}">
    @endif

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
              <label class="form-label">Event Name <span class="text-danger">*</span></label>
              <input name="name"
                     class="form-control @error('name') is-invalid @enderror"
                     value="{{ old('name', $isCopy ? (($sourceEvent?->name ?? '') . ' (Copy)') : '') }}"
                     required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Dates --}}
            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Start Date</label>
                <input type="date"
                       name="start_date"
                       class="form-control @error('start_date') is-invalid @enderror"
                       value="{{ old('start_date', optional($sourceEvent?->start_date)->format('Y-m-d')) }}">
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col">
                <label class="form-label">End Date</label>
                <input type="date"
                       name="end_date"
                       class="form-control @error('end_date') is-invalid @enderror"
                       value="{{ old('end_date', optional($sourceEvent?->end_date)->format('Y-m-d')) }}">
                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            {{-- Event Type --}}
            <div class="mb-3">
              <label class="form-label">Event Type <span class="text-danger">*</span></label>
              <select name="eventType"
                      class="form-select @error('eventType') is-invalid @enderror"
                      required>
                <option value="">— Select type —</option>
                @foreach($eventTypes as $type)
                  <option value="{{ $type->id }}" @selected(old('eventType', $sourceEvent?->eventType) == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              @error('eventType')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Information --}}
            <div class="mb-3">
              <label class="form-label">Information</label>
              <textarea name="information"
                        class="form-control"
                        rows="4">{{ old('information', $sourceEvent?->information ?? '') }}</textarea>
            </div>

            {{-- Venue Notes --}}
            <div class="mb-3">
              <label class="form-label">Venue Notes</label>
              <textarea name="venue_notes"
                        class="form-control"
                        rows="3">{{ old('venue_notes', $sourceEvent?->venue_notes ?? '') }}</textarea>
            </div>

            {{-- Logo --}}
            <div class="mb-3">
              <label class="form-label">Logo</label>
              <input type="file"
                     name="logo_upload"
                     class="form-control @error('logo_upload') is-invalid @enderror"
                     accept="image/*">
              @error('logo_upload')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

          </div>
        </div>
      </div>

      {{-- ================= RIGHT SIDE ================= --}}
      <div class="col-xl-4">
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
                     value="{{ old('entryFee', $sourceEvent?->entryFee ?? '') }}">
            </div>

            {{-- Deadline --}}
            <div class="mb-3">
              <label class="form-label">Deadline (days before start)</label>
              <input type="number"
                     name="deadline"
                     class="form-control"
                     value="{{ old('deadline', $sourceEvent?->deadline ?? '') }}">
            </div>

            {{-- Withdrawal Deadline --}}
            <div class="mb-3">
              <label class="form-label">Withdrawal Deadline</label>
              <input type="datetime-local"
                     name="withdrawal_deadline"
                     class="form-control"
                     value="{{ old('withdrawal_deadline', optional($sourceEvent?->withdrawal_deadline)->format('Y-m-d\TH:i')) }}">
            </div>

            {{-- Organizer --}}
            <div class="mb-3">
              <label class="form-label">Organizer</label>
              <input type="text"
                     name="organizer"
                     class="form-control"
                     value="{{ old('organizer', $sourceEvent?->organizer ?? '') }}">
            </div>

            {{-- Email --}}
            <div class="mb-3">
              <label class="form-label">Contact Email</label>
              <input type="email"
                     name="email"
                     class="form-control @error('email') is-invalid @enderror"
                     value="{{ old('email', $sourceEvent?->email ?? '') }}">
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    @selected(is_array(old('admins', $adminIds ?? [])) ? in_array($user->id, old('admins', $adminIds ?? [])) : in_array($user->id, $adminIds ?? []))>
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
                     @checked(old('published', false))>
              <label class="form-check-label">Published</label>
            </div>

            {{-- SignUp --}}
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     name="signUp"
                     value="1"
                     @checked(old('signUp', false))>
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
        {{ $isCopy ? 'Save Copied Event' : 'Create Event' }}
      </button>
    </div>

  </form>
</div>
@endsection

@section('page-script')
<script>
  $(document).ready(function () {
    $('.select2').select2();
  });
</script>
@endsection
