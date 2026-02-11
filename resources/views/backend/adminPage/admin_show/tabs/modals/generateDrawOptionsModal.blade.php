<!-- Modal -->
<div class="modal fade" id="generateDrawModal" tabindex="-1" aria-labelledby="generateDrawModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('draws.generate.from.modal') }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>


        <div class="modal-body">
          <div class="mb-3">
            <label for="category_event_id" class="form-label">Select Category</label>

            <select name="category_event_id" class="form-select select2" required>
              <option value="">-- Choose Category --</option>
              @foreach ($eventCategories as $eventCategory)
                <option value="{{ $eventCategory->id }}">
                  {{ optional($eventCategory->category)->name }}
                </option>
              @endforeach
            </select>

          </div>

          <div class="mb-3">
            <label for="draw_name" class="form-label">Draw Name</label>
            <input type="text" name="draw_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="draw_format_id" class="form-label">Draw Format</label>
            <select name="draw_format_id" class="form-select" required>
              <option value="1">Knockout</option>
              <option value="2">Feed-In</option>
              <option value="3">Round Robin</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Create Draw</button>
        </div>
      </div>
    </form>
  </div>
</div>
