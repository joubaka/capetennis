<!-- Add Player to Profile Modal -->
<div class="modal fade" id="addProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <form id="playerProfileForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Player to Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          {{-- Player Select --}}
          <div class="mb-3">
            <label for="add-player-select" class="form-label fw-bold">
              Select Player to Add
            </label>

            <select
              name="player_id"
              id="add-player-select"
              class="form-select select2"
              data-placeholder="Select a player">
              <option></option>
              @foreach ($players as $player)
                <option value="{{ $player->id }}">
                  {{ $player->full_name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Target user --}}
          <input type="hidden" name="user_id" value="{{ $user->id }}">
          
        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-label-secondary"
                  data-bs-dismiss="modal">
            Close
          </button>

          <button type="button"
                  class="btn btn-primary"
                  id="addPlayerToProfileButton">
            Add Player
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
