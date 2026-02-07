@extends('layouts/layoutMaster')

@section('title', 'Series – Manage Events')

{{-- =========================
   VENDOR STYLES
========================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.min.css') }}">
@endsection



@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
      {{ $series->name }} – Events
    </h4>
    <a href="{{ route('series.index') }}" class="btn btn-outline-secondary">
      Back to Series
    </a>
  </div>

  <div class="row g-4">

    {{-- EVENTS IN SERIES --}}
    <div class="col-xl-7">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Events in this Series</h5>
        </div>

        <div class="card-body p-0">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Event</th>
                <th>Dates</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($seriesEvents as $event)
                <tr>
                  <td><strong>{{ $event->name }}</strong></td>
              
                  <td class="text-end d-flex gap-1 justify-content-end">

  <a href="{{ route('backend.events.edit', $event) }}"
     class="btn btn-sm btn-outline-primary">
    Edit
  </a>

  <form method="POST"
        action="{{ route('series.events.copy', [$series, $event]) }}">
    @csrf
    <button class="btn btn-sm btn-outline-warning">
      Copy
    </button>
  </form>

  <form method="POST"
        action="{{ route('series.events.remove', [$series, $event]) }}"
        onsubmit="return confirm('Remove this event from the series?')">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm btn-outline-danger">
      Remove
    </button>
  </form>

</td>

                </tr>
              @empty
                <tr>
                  <td colspan="3" class="text-center text-muted py-3">
                    No events in this series yet
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ADD / CREATE EVENT --}}
    <div class="col-xl-5">

      {{-- ADD EXISTING EVENT --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Add Existing Event</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('series.events.add', $series) }}">
            @csrf

            <div class="mb-3">
              <label class="form-label">Event</label>
              <select name="event_id"
                      class="form-select select2"
                      data-placeholder="Select event…"
                      required>
                <option></option>
                @foreach($availableEvents as $event)
                  <option value="{{ $event->id }}">
                    {{ $event->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <button class="btn btn-primary w-100">
              Add to Series
            </button>
          </form>
        </div>
      </div>
{{-- CREATE NEW EVENT --}}
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Create New Event in Series</h5>
  </div>

  <div class="card-body">
    <form method="POST"
      action="{{ route('series.events.create', $series) }}"
      enctype="multipart/form-data">

      @csrf

      {{-- Name --}}
      <div class="mb-3">
        <label class="form-label">Event Name</label>
        <input type="text"
               name="name"
               class="form-control"
               required>
      </div>

      {{-- Dates --}}
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label">Start Date</label>
          <input type="date"
                 name="start_date"
                 class="form-control">
        </div>
        <div class="col">
          <label class="form-label">End Date</label>
          <input type="date"
                 name="end_date"
                 class="form-control">
        </div>
      </div>

      {{-- Event Type --}}
      <div class="mb-3">
        <label class="form-label">Event Type</label>
        <select name="eventType"
                class="form-select"
                required>
          <option value="">Select type…</option>
          <option value="1">Individual</option>
          <option value="2">Team</option>
          <option value="3">Camp</option>
        </select>
      </div>

      {{-- Entry Fee & Deadline --}}
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label">Entry Fee</label>
          <input type="number"
                 name="entryFee"
                 class="form-control"
                 min="0">
        </div>
        <div class="col">
          <label class="form-label">
            Registration Closes (days before start)
          </label>
          <input type="number"
                 name="deadline"
                 class="form-control"
                 min="0">
        </div>
      </div>

      {{-- Email --}}
      <div class="mb-3">
        <label class="form-label">Contact Email</label>
        <input type="email"
               name="email"
               class="form-control">
      </div>

      {{-- Information --}}
      <div class="mb-3">
        <label class="form-label">Event Information</label>
        <textarea name="information"
                  class="form-control"
                  rows="4"></textarea>
      </div>

      {{-- Venue Notes --}}
      <div class="mb-3">
        <label class="form-label">Venue Notes</label>
        <textarea name="venue_notes"
                  class="form-control"
                  rows="3"></textarea>
      </div>

      {{-- Flags --}}
      <div class="form-check mb-2">
        <input class="form-check-input"
               type="checkbox"
               name="published"
               value="1">
        <label class="form-check-label">
          Published
        </label>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input"
               type="checkbox"
               name="signUp"
               value="1">
        <label class="form-check-label">
          Allow Sign Ups
        </label>
      </div>
      {{-- LOGO --}}
<div class="mb-3">
  <label class="form-label">Event Logo</label>

  {{-- Preview --}}
  <img id="logo-preview"
       class="img-thumbnail d-none mb-2"
       style="max-height:120px">

  {{-- Existing logos --}}
  <select name="logo_existing"
          class="form-select mb-2">
    <option value="">— Select existing logo —</option>
    @foreach(File::files(public_path('assets/img/logos')) as $logo)
      <option value="{{ $logo->getFilename() }}">
        {{ $logo->getFilename() }}
      </option>
    @endforeach
  </select>

  {{-- Upload --}}
  <input type="file"
         name="logo_upload"
         class="form-control"
         accept="image/*">

  <small class="text-muted">
    Upload overrides selected logo
  </small>
</div>

      <button class="btn btn-success w-100">
        Create Event
      </button>
    </form>
  </div>
</div>


    </div>
  </div>
</div>
@endsection

{{-- =========================
   VENDOR SCRIPTS
========================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.min.js') }}"></script>
@endsection



@section('page-script')
<script>
  $(document).ready(function () {

    // ================= SELECT2 =================
    $('.select2').select2({
      width: '100%',
      placeholder: function () {
        return $(this).data('placeholder');
      },
      allowClear: true
    });

    // ================= TOASTR CONFIG =================
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: 'toast-top-right',
      timeOut: 4000,
      showMethod: 'fadeIn',
      hideMethod: 'fadeOut'
    };

    // ================= FLASH MESSAGES =================
    @if(session('success'))
      toastr.success(@json(session('success')));
    @endif

    @if(session('error'))
      toastr.error(@json(session('error')));
    @endif

    @if($errors->any())
      toastr.error('Please fix the highlighted errors.');
    @endif

    // ================= LOGO PREVIEW =================
    const preview = document.getElementById('logo-preview');
    const existingSelect = document.querySelector('select[name="logo_existing"]');
    const uploadInput = document.querySelector('input[name="logo_upload"]');

    const logoBaseUrl = "{{ asset('assets/img/logos') }}/";

    if (existingSelect && preview) {
      existingSelect.addEventListener('change', function () {
        if (this.value) {
          preview.src = logoBaseUrl + this.value;
          preview.classList.remove('d-none');
        } else {
          preview.classList.add('d-none');
          preview.src = '';
        }
      });
    }

    if (uploadInput && preview) {
      uploadInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
          preview.src = e.target.result;
          preview.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
      });
    }

  });
</script>
@endsection


