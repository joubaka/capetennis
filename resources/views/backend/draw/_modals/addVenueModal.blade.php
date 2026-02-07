<div class="modal fade" id="basicModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add Venues</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label">Venue</label>
          <select id="venueDrawSelect2" class="form-select select2">
            @foreach($venues as $venue)
              <option value="{{ $venue->id }}">{{ $venue->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3 row">
          <label class="col-5 col-form-label">Number of courts</label>
          <div class="col-7">
            <input class="form-control" id="numCourtsInput" type="number" value="6">
          </div>
        </div>

        <input type="hidden" id="drawIdInput">

        <button type="button"
                id="save-draw-venue-button"
                class="btn btn-success btn-sm">
          Save Venue
        </button>

      </div>
    </div>
  </div>
</div>
