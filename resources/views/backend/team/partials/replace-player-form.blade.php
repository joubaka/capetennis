<form id="replacePlayerForm">
  @csrf

  <input type="hidden" name="pivot_id" value="{{ $slot->id }}">
  <input type="hidden" name="team_id" value="{{ $teamId }}">

  <div class="mb-3">
    <label class="form-label">
      Replace player at position {{ $rank }}
    </label>

 <select name="player_id"
        class="form-select select2ReplacePlayer"
        required>

      <option value="">— Select player —</option>

      @foreach($players as $player)
        <option value="{{ $player->id }}">
          {{ $player->name }} {{ $player->surname }}
        </option>
      @endforeach
    </select>
  </div>

  <div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-primary">
      Replace Player
    </button>
  </div>
</form>


