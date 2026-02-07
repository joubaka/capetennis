<!-- 🔹 Replace Player Modal -->
<div class="modal fade" id="insert-player-team-modal" tabindex="-1" aria-labelledby="insertPlayerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="insertPlayerLabel">
          <i class="ti ti-user-plus me-1"></i> Replace Player
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Form -->
      <form id="playerForm">
        <div class="modal-body">
          <div class="mb-3">
            <label for="select2PlayerBasic" class="form-label fw-bold">Select Player</label>
            <select id="select2PlayerBasic"
                    name="player_id"
                    class="form-select select2"
                    style="width: 100%;"
                    data-placeholder="Choose a player"
                    data-allow-clear="true">
              <option></option>
              @foreach($players as $player)
                <option value="{{ $player->id }}">
                  {{ $player->name }} {{ $player->surname }}
                </option>
              @endforeach
            </select>
          </div>

          <!-- Hidden Fields -->
          <input type="hidden" name="team_id" id="teamId">
          <input type="hidden" name="position" id="position">
          <input type="hidden" name="pivot" id="pivot">
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="ti ti-x me-50"></i> Close
          </button>
          <button type="button" class="btn btn-primary" id="playerPosition">
            <i class="ti ti-check me-50"></i> Insert Player in Position
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
