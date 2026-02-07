<!-- Add Player to Category Modal -->
<div class="modal fade" id="addPlayerToCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
    <div class="modal-content p-3 p-md-5">
      <div class="modal-body">
        <div class="col-12">
          <h5 class="card-header mb-3">Add Player to Category</h5>

          <form id="addPlayerToCategoryForm">
            <input type="hidden" id="event_id" name="event_id" value="{{ $event->id }}">
           <input type="hidden" name="category_event_id" id="categoryEvent">


            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Player</label>
                <select id="select2AddPlayer" name="player_id" class="form-select form-select-lg" data-allow-clear="true">
                  @foreach($players as $player)
                    <option value="{{ $player->id }}">{{ $player->name }} {{ $player->surname }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
              <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" id="addPlayerToCategoryButton">Add Player</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
