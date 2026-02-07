<form id="venuesForm" action="{{ route('backend.draw.venues.store', $draw->id) }}" method="POST">
  @csrf

  <div id="venuesRepeater">
    @forelse($draw->venues as $venue)
      <div class="row venue-row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Venue</label>
          <select name="venue_id[]" class="form-select select2" required>
            @foreach($venues as $v)
              <option value="{{ $v->id }}" {{ $venue->id == $v->id ? 'selected' : '' }}>
                {{ $v->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"># of Courts</label>
          <input type="number" name="num_courts[]" class="form-control"
                 value="{{ $venue->pivot->num_courts ?? 1 }}" min="1">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-outline-danger remove-venue-row">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>
    @empty
      {{-- Default row --}}
      <div class="row venue-row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Venue</label>
          <select name="venue_id[]" class="form-select select2" required>
            @foreach($venues as $v)
              <option value="{{ $v->id }}">{{ $v->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"># of Courts</label>
          <input type="number" name="num_courts[]" class="form-control" value="1" min="1">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-outline-danger remove-venue-row">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>
    @endforelse
  </div>

  <button type="button" id="addVenueRow" class="btn btn-sm btn-outline-primary">
    + Add Another Venue
  </button>

  <div class="mt-3 text-end">
    <button type="submit" class="btn btn-primary">Save Venues</button>
  </div>
</form>

<template id="venueTemplate">
  <div class="row venue-row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label">Venue</label>
      <select name="venue_id[]" class="form-select select2" required>
        @foreach($venues as $v)
          <option value="{{ $v->id }}">{{ $v->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label"># of Courts</label>
      <input type="number" name="num_courts[]" class="form-control" value="1" min="1">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="button" class="btn btn-outline-danger remove-venue-row">
        <i class="ti ti-trash"></i>
      </button>
    </div>
  </div>
</template>
