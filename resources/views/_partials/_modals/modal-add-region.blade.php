<!-- Modal: Add Region to Event -->
<div class="modal fade" id="modalToggle" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 p-md-5">

      <h4>Add Region to {{ $event->name }}</h4>

      <div class="modal-body">
        <form id="regionEventForm">
          <input type="hidden" name="event_id" value="{{ $event->id }}"> <!-- ✅ FIXED key -->

          <div class="mb-3">
            <label for="select2Region" class="form-label">Select Region</label>
            <select id="select2Region" name="region_id"
                    class="select2Region form-select form-select-lg"
                    data-placeholder="Select a region" data-allow-clear="true" style="width: 100%;">
              <option></option>
              @foreach($regions as $region)
                <option value="{{ $region->id }}">{{ $region->region_name }}</option>
              @endforeach
            </select>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ti ti-x me-1"></i> Close
        </button>
        <button type="button" id="addRegionToEventButton" class="btn btn-primary">
          <i class="ti ti-plus me-1"></i> Add Region to Event
        </button>
      </div>
    </div>
  </div>
</div>
