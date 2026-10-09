@extends('layouts.backend')

@section('title', 'Series – Manage Events')

{{-- =========================
   VENDOR STYLES
========================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection




@section('content')
<div class="container-xl series-events-admin">

  {{-- HEADER --}}
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
      {{ $series->name }} – Events
    </h4>
    <a href="{{ route('series.index') }}" class="btn btn-outline-secondary">
      Back to Series
    </a>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <p class="text-muted">{{ $seriesEvents->count() }} events in this series. Edit an event for its full setup; removing it here keeps the event and its records.</p>
  <div class="row g-4">

    {{-- EVENTS IN SERIES --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Events in this Series</h5>
        </div>

        <div class="card-body p-0 table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Event</th>
                <th>Dates</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($seriesEvents as $event)
                <tr>
                  <td><strong>{{ $event->name }}</strong><div class="small text-muted">{{ $event->published ? 'Published' : 'Unpublished' }} · {{ $event->signUp ? 'Registration open' : 'Registration closed' }}</div></td>
                  <td>{{ $event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date)->format('d M Y') : 'Date not set' }}@if($event->end_date && $event->end_date !== $event->start_date)<br>{{ \Illuminate\Support\Carbon::parse($event->end_date)->format('d M Y') }}@endif</td>
              
                  <td class="text-end series-event-actions">

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
    <div class="col-12">

      {{-- ADD EXISTING EVENT --}}
      <details class="card mb-4" {{ $errors->has('event_id') ? 'open' : '' }}>
        <summary class="card-header">Add Existing Event</summary>
        <div class="card-body">
          <form method="POST" action="{{ route('series.events.add', $series) }}">
            @csrf

            <div class="mb-3">
              <p class="small text-muted">Attach an existing event to this series. Selecting an event from another series moves its series association.</p>
              <label class="form-label" for="series-existing-event">Event</label>
              <select id="series-existing-event" name="event_id"
                      class="form-select select2"
                      data-placeholder="Select event…"
                      required>
                <option></option>
                @foreach($availableEvents as $event)
                  <option value="{{ $event->id }}" @selected((string) old('event_id') === (string) $event->id)>
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
      </details>
{{-- CREATE NEW EVENT --}}
<details class="card" {{ $errors->any() && !$errors->has('event_id') ? 'open' : '' }}>
  <summary class="card-header">Create New Event in Series</summary>

  <div class="card-body">
    <form method="POST"
      action="{{ route('series.events.create', $series) }}"
      enctype="multipart/form-data">

      @csrf

      <p class="small text-muted">Create a new event attached to this series. Publication and registration stay closed unless selected below.</p>
      {{-- Name --}}
      <div class="mb-3">
        <label class="form-label" for="series-name">Event Name</label>
        <input type="text"
               id="series-name" name="name" value="{{ old('name') }}"
               class="form-control"
               required>
      </div>

      {{-- Dates --}}
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label" for="series-start_date">Start Date</label>
          <input type="date"
                 id="series-start_date" name="start_date" value="{{ old('start_date') }}"
                 class="form-control">
        </div>
        <div class="col">
          <label class="form-label" for="series-end_date">End Date</label>
          <input type="date"
                 id="series-end_date" name="end_date" value="{{ old('end_date') }}"
                 class="form-control">
        </div>
      </div>

      {{-- Event Type --}}
      <div class="mb-3">
        <label class="form-label" for="series-eventType">Event Type</label>
        <select id="series-eventType" name="eventType"
                class="form-select"
                required>
          <option value="">Select type…</option>
          <option value="1" @selected((string) old('eventType') === '1')>Individual</option>
          <option value="2" @selected((string) old('eventType') === '2')>Team</option>
          <option value="3" @selected((string) old('eventType') === '3')>Camp</option>
        </select>
      </div>

      {{-- Entry Fee & Deadline --}}
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label" for="series-entryFee">Entry Fee</label>
          <input type="number"
                 id="series-entryFee" name="entryFee" value="{{ old('entryFee') }}"
                 class="form-control"
                 min="0">
        </div>
        <div class="col">
          <label class="form-label" for="series-deadline">
            Registration Closes (days before start)
          </label>
          <input type="number"
                 id="series-deadline" name="deadline" value="{{ old('deadline') }}"
                 class="form-control"
                 min="0">
        </div>
      </div>

      <details class="border rounded p-3 mb-3" {{ $errors->hasAny(['email','information','venue_notes','logo_existing','logo_upload']) ? 'open' : '' }}><summary>Contact, information and logo</summary>
      <div class="mt-3">
      {{-- Email --}}
      <div class="mb-3">
        <label class="form-label" for="series-email">Contact Email</label>
        <input type="email"
               id="series-email" name="email" value="{{ old('email') }}"
               class="form-control">
      </div>

      {{-- Information --}}
      <div class="mb-3">
        <label class="form-label" for="series-information">Event Information</label>
        <textarea id="series-information" name="information"
                  class="form-control"
                  rows="4">{{ old('information') }}</textarea>
      </div>

      {{-- Venue Notes --}}
      <div class="mb-3">
        <label class="form-label" for="series-venue_notes">Venue Notes</label>
        <textarea id="series-venue_notes" name="venue_notes"
                  class="form-control"
                  rows="3">{{ old('venue_notes') }}</textarea>
      </div>

      {{-- LOGO --}}
<div class="mb-3">
  <label class="form-label" for="series-logo_existing">Event Logo</label>

  {{-- Preview --}}
  <img id="logo-preview"
       class="img-thumbnail d-none mb-2"
       style="max-height:120px">

  {{-- Existing logos --}}
  <select id="series-logo_existing" name="logo_existing"
          class="form-select mb-2">
    <option value="">— Select existing logo —</option>
    @foreach(File::files(public_path('assets/img/logos')) as $logo)
      <option value="{{ $logo->getFilename() }}" @selected(old('logo_existing') === $logo->getFilename())>
        {{ $logo->getFilename() }}
      </option>
    @endforeach
  </select>

  {{-- Upload --}}
  <input type="file"
         id="series-logo-upload" name="logo_upload" aria-label="Upload event logo"
         class="form-control"
         accept="image/*">

  <small class="text-muted">
    Upload overrides selected logo
  </small>
</div>

      </div></details>
      {{-- Flags --}}
      <div class="form-check mb-2">
        <input class="form-check-input"
               type="checkbox"
               id="series-event-published" name="published"
               value="1" @checked(old('published'))>
        <label class="form-check-label" for="series-event-published">
          Publish event
        </label>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input"
               type="checkbox"
               id="series-event-registration" name="signUp"
               value="1" @checked(old('signUp'))>
        <label class="form-check-label" for="series-event-registration">
          Registration open
        </label>
      </div>
      <button class="btn btn-success w-100">
        Create Event
      </button>
    </form>
  </div>
</details>


    </div>
  </div>
</div>
<style>
.series-events-admin :is(.btn,input:not([type=checkbox]),select,summary) { min-height:44px !important; }
.series-events-admin summary { cursor:pointer; align-content:center; }
.series-event-actions { min-width:210px; }
.series-event-actions form { display:inline-block; margin:.2rem 0; }
.series-events-admin td { white-space:normal; overflow-wrap:anywhere; }
.series-events-admin .select2-container { max-width:100%; }
.series-events-admin .select2-selection { min-height:44px !important; }
.series-events-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }
@media(max-width:575px) { .series-events-admin table { min-width:560px; } .series-events-admin .row > .col { flex:0 0 100%; } }
</style>
@endsection





@section('page-script')

<script src="{{ asset(mix('js/seriesEvents.js')) }}"></script>
@endsection


