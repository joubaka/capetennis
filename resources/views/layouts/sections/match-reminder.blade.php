<style>
  #match-reminder article { overflow-wrap: anywhere; }
  #match-reminder .modal-header { gap: .5rem; }
  #match-reminder .modal-title { flex: 1; white-space: normal; overflow: visible; text-overflow: clip; min-width: 0; overflow-wrap: anywhere; }
  #match-reminder .modal-header .btn-close { position: static; flex: 0 0 auto; margin: 0; }
  #match-reminder .reminder-time { color: #12358f; }
  @media (max-width: 575.98px) {
    #match-reminder .modal-dialog { margin: .5rem; }
    #match-reminder .modal-body, #match-reminder .modal-header, #match-reminder .modal-footer { padding: 1rem; }
  }
</style>
<div class="modal fade" id="match-reminder" tabindex="-1" aria-labelledby="match-reminder-title" aria-describedby="match-reminder-description" aria-hidden="true"
     data-endpoint="{{ route('my.tennis.match-reminder') }}" data-account="{{ auth()->id() }}" data-login="{{ session('match_reminder_login', 'existing-session') }}" data-day="{{ now('Africa/Johannesburg')->toDateString() }}">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-4" id="match-reminder-title">Tennis in the next 7 days</h2>
        <button type="button" class="btn-close p-3" data-bs-dismiss="modal" aria-label="Close reminder"></button>
      </div>
      <div class="modal-body">
        <p id="match-reminder-description">Your players’ published matches at a glance. Check fixtures for the latest details.</p>
        <div data-reminder-players></div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('my.tennis') }}" class="btn btn-primary" style="min-height:44px">View all in My Tennis</a>
        <button type="button" class="btn btn-outline-secondary" style="min-height:44px" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script src="{{ asset('js/match-reminder.js') }}?v={{ filemtime(public_path('js/match-reminder.js')) }}" defer></script>
