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

<script>
(function () {

  const $modal  = $('#insert-player-team-modal');
  const $select = $modal.find('.select2ReplacePlayer');

  // 🔥 CRITICAL: destroy if already initialised
  if ($select.hasClass('select2-hidden-accessible')) {
    $select.select2('destroy');
  }

  // ✅ Clean re-init
  $select.select2({
    dropdownParent: $modal,
    width: '100%',
    placeholder: 'Search player...',
    allowClear: true,
    matcher: function (params, data) {
      if (!params.term) return data;
      if (!data.text) return null;

      return data.text
        .toLowerCase()
        .includes(params.term.toLowerCase())
        ? data
        : null;
    }
  });

})();
</script>
