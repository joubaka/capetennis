<div class="modal fade" id="venuesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="venuesForm" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Venues</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div id="venues-container"></div>
          <button type="button" class="btn btn-sm btn-secondary" id="addVenueRow">+ Add another venue row</button>

          <div class="border-top mt-3 pt-3">
            <button type="button" class="btn btn-sm btn-outline-primary" id="toggle-create-venue" aria-expanded="false" aria-controls="create-venue-panel">
              <i class="ti ti-map-pin-plus me-1" aria-hidden="true"></i>Create a venue not in the list
            </button>
            <div class="border rounded p-3 mt-2 d-none" id="create-venue-panel">
              <div class="mb-2">
                <label class="form-label" for="new-draw-venue-name">Venue name</label>
                <input type="text" class="form-control" id="new-draw-venue-name" maxlength="191" placeholder="Enter the venue name">
              </div>
              <div class="row g-2">
                <div class="col-sm-6">
                  <label class="form-label" for="new-draw-venue-courts">Number of courts</label>
                  <input type="number" class="form-control" id="new-draw-venue-courts" min="1" max="100" value="1">
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="new-draw-venue-ball">Court type</label>
                  <select class="form-select" id="new-draw-venue-ball">
                    <option value="standard">Standard</option>
                    <option value="yellow">Yellow ball</option>
                    <option value="orange">Orange ball</option>
                    <option value="green">Green ball</option>
                    <option value="red">Red ball</option>
                  </select>
                </div>
              </div>
              <button type="button" class="btn btn-primary btn-sm mt-3" id="create-draw-venue">Create and select venue</button>
              <div class="small mt-2" id="create-draw-venue-status" role="status" aria-live="polite"></div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
