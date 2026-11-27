@can('replacePlayoffLuckyLoser', $draw)
<div id="playoff-ll-control" data-candidates-url="{{ route('draw.playoff-lucky-loser.show', $draw) }}" data-store-url="{{ route('draw.playoff-lucky-loser.store', $draw) }}" data-revision="{{ app(\App\Services\Draw\PlayoffLuckyLoserService::class)->revision($draw) }}">
  <div id="playoff-ll-menu" class="dropdown-menu p-1 shadow" role="menu" hidden style="position:fixed;z-index:1080">
    <button type="button" class="dropdown-item" role="menuitem" id="playoff-ll-action">Replace with lucky loser</button>
  </div>
  <div class="modal fade" id="playoff-ll-modal" tabindex="-1" aria-labelledby="playoff-ll-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="playoff-ll-form">
      <div class="modal-header"><h2 class="modal-title h5" id="playoff-ll-title">Replace with lucky loser</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p id="playoff-ll-outgoing"></p>
        <p class="small text-muted">Choose an active entrant from any division in this event. Players with another active playoff place are blocked. Round-robin results and event entries stay unchanged.</p>
        <label class="form-label" for="playoff-ll-search">Find a player</label>
        <input id="playoff-ll-search" class="form-control mb-3" maxlength="100" autocomplete="off">
        <label class="form-label" for="playoff-ll-candidate">Incoming lucky loser</label>
        <select id="playoff-ll-candidate" class="form-select mb-2" required></select>
        <button type="button" id="playoff-ll-more" class="btn btn-sm btn-outline-secondary mb-2" hidden>Load more players</button>
        <label class="d-flex gap-2 mt-3"><input type="checkbox" id="playoff-ll-confirm" required>Confirm withdrawal from this playoff place and replace with the selected lucky loser.</label>
        <div id="playoff-ll-error" class="text-danger mt-2" role="status" aria-live="polite"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="playoff-ll-submit">Confirm replacement</button></div>
    </form></div>
  </div>
</div>
<script src="{{ asset('js/playoff-lucky-loser.js') }}?v={{ filemtime(public_path('js/playoff-lucky-loser.js')) }}"></script>
@endcan
