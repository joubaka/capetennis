
<!-- ===================================================== -->
<!-- 🔹 EDIT PLAYER MODAL (AJAX) -->
<!-- ===================================================== -->
<div class="modal fade" id="playerEditModal" tabindex="-1" aria-labelledby="playerEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-simple modal-edit-player">
    <div class="modal-content p-3 p-md-4">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="text-center mb-4">
          <h3 class="mb-2"><i class="ti ti-user-cog me-1"></i> Edit Player Information</h3>
          <p class="text-muted">Update the player’s details and confirm changes.</p>
        </div>

        <form id="playerEditForm" class="" novalidate>
          @csrf
          <input type="hidden" name="player_id" id="player-id">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="player-name">First Name</label>
            <input type="text" name="name" id="player-name" class="form-control" placeholder="John" required>
            <div class="invalid-feedback">Please enter a first name.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="player-surname">Surname</label>
            <input type="text" name="surname" id="player-surname" class="form-control" placeholder="Doe" required>
            <div class="invalid-feedback">Please enter a surname.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="player-email">Email</label>
            <input type="email" name="email" id="player-email" class="form-control" placeholder="example@domain.com">
            <div class="invalid-feedback">Please enter a valid email address.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="player-cell">Phone Number</label>
            <input type="text" name="cell_nr" id="player-cell" class="form-control" placeholder="202 555 0111">
          </div>

          <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">
              <i class="ti ti-check me-1"></i> Save Changes
            </button>
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
              <i class="ti ti-x me-1"></i> Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
