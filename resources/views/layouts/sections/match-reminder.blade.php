<style>
  #match-reminder { color: #3e4c48; }
  #match-reminder .modal-dialog { max-width: 720px; }
  #match-reminder .modal-content { background: #fcfcfa; border: 1px solid #e1e6e0; border-radius: 18px; box-shadow: 0 18px 60px rgba(34, 48, 42, .14); }
  #match-reminder .modal-header { gap: .5rem; padding: 1.35rem 1.5rem .75rem; border: 0; }
  #match-reminder .modal-title { flex: 1; color: #35463f; font-size: 1.25rem; font-weight: 600; white-space: normal; overflow: visible; text-overflow: clip; min-width: 0; overflow-wrap: anywhere; }
  #match-reminder .modal-header .btn-close { position: static; flex: 0 0 auto; margin: 0; opacity: .55; }
  #match-reminder .modal-body { padding: .25rem 1.5rem 1rem; }
  #match-reminder-description { color: #68756e; font-size: .9rem; line-height: 1.55; margin-bottom: 1.35rem; }
  #match-reminder .reminder-player { margin-bottom: 1.25rem; }
  #match-reminder .reminder-player:last-child { margin-bottom: 0; }
  #match-reminder .reminder-player-name { color: #56645c; font-size: .9rem; font-weight: 600; margin-bottom: .5rem; }
  #match-reminder article { overflow-wrap: anywhere; padding: .9rem 1rem; background: #f2f5f0; border-radius: 10px; margin-bottom: .5rem; }
  #match-reminder .reminder-time { color: #586e5d; font-size: .85rem; font-weight: 600; margin-bottom: .35rem; }
  #match-reminder .reminder-participants { color: #35463f; font-weight: 600; line-height: 1.5; }
  #match-reminder .reminder-event { color: #657168; font-size: .8rem; margin-top: .2rem; }
  #match-reminder .reminder-venue { color: #58665e; font-size: .9rem; margin-top: .4rem; }
  #match-reminder .reminder-fixtures { display: inline-flex; align-items: center; min-height: 44px; color: #526b58; font-size: .85rem; text-decoration: underline; text-underline-offset: 3px; }
  #match-reminder .reminder-fixtures:hover { color: #304b38; }
  #match-reminder .modal-footer { display: block; padding: 1rem 1.5rem 1.25rem; background: #f7f8f5; border-top: 1px solid #e6eae3; border-radius: 0 0 18px 18px; }
  #match-reminder .reminder-settings { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem .75rem; align-items: end; margin: 0; }
  #match-reminder .reminder-settings label { display: block; margin-bottom: .35rem; color: #68756e; font-size: .8rem; }
  #match-reminder .reminder-preference { min-width: 0; }
  #match-reminder .form-select { width: 100%; min-height: 44px; border-color: #dce3d9; background-color: #fcfcfa; color: #56645c; font-size: .875rem; }
  #match-reminder .select2-container { max-width: 100%; }
  #match-reminder .select2-selection { background: #fcfcfa; border-color: #dce3d9; }
  #match-reminder .reminder-help { margin: .65rem 0 .9rem; color: #657168; font-size: .8rem; }
  #match-reminder .reminder-help summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; text-decoration: underline; text-underline-offset: 3px; }
  #match-reminder .reminder-help p { line-height: 1.5; margin-bottom: .5rem; }
  #match-reminder .reminder-actions { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin: 0; }
  #match-reminder .btn { min-height: 44px; font-size: .875rem; box-shadow: none; }
  #match-reminder .reminder-apply { background: #e5ece2; color: #435c47; border: 1px solid #d7e1d2; }
  #match-reminder .reminder-apply:hover { background: #d9e4d5; }
  #match-reminder .reminder-all { display: inline-flex; align-items: center; min-height: 44px; color: #526b58; font-size: .875rem; text-decoration: underline; text-underline-offset: 3px; }
  #match-reminder .reminder-close { color: #647067; background: transparent; border: 1px solid #dce3d9; }
  #match-reminder a:focus-visible, #match-reminder summary:focus-visible, #match-reminder button:focus-visible { outline: 2px solid #697f6d; outline-offset: 3px; }
  @media (max-width: 575.98px) {
    #match-reminder .modal-dialog { margin: .5rem; }
    #match-reminder .modal-body, #match-reminder .modal-header, #match-reminder .modal-footer { padding-left: 1rem; padding-right: 1rem; }
    #match-reminder .reminder-settings { grid-template-columns: minmax(0, 1fr); }
    #match-reminder .reminder-apply { width: 100%; }
  }
  @media (max-width: 575.98px), (max-height: 600px) {
    #match-reminder .modal-content { max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); overflow-y: auto; }
    #match-reminder .modal-body { overflow-y: visible; flex: 0 0 auto; }
    #match-reminder .modal-header, #match-reminder .modal-footer { flex-shrink: 0; }
  }
</style>
<div class="modal fade" id="match-reminder" tabindex="-1" aria-labelledby="match-reminder-title" aria-describedby="match-reminder-description" aria-hidden="true"
     data-endpoint="{{ route('my.tennis.match-reminder') }}" data-account="{{ auth()->id() }}" data-login="{{ session('match_reminder_login', 'existing-session') }}" data-day="{{ now('Africa/Johannesburg')->toDateString() }}">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="match-reminder-title">Your week in tennis</h2>
        <button type="button" class="btn-close p-3" data-bs-dismiss="modal" aria-label="Close reminder"></button>
      </div>
      <div class="modal-body">
        <p id="match-reminder-description">Published matches for your players over the next 7 days. Check fixtures for the latest details.</p>
        <div data-reminder-players></div>
      </div>
      <div class="modal-footer">
        <div class="reminder-settings">
          <div class="reminder-preference">
            <label for="match-reminder-preference">Remind me</label>
            <select id="match-reminder-preference" class="form-select" data-reminder-preference data-select2-script="{{ asset('assets/vendor/libs/select2/select2.js') }}" data-select2-style="{{ asset('assets/vendor/libs/select2/select2.css') }}" aria-describedby="match-reminder-timing-help">
              <option value="login">Close for this login</option>
              <option value="hour">Snooze for 1 hour</option>
              <option value="week">Hide for 7 days</option>
              <option value="match-day" data-reminder-next-day disabled>On the next match day</option>
            </select>
          </div>
          <button type="button" class="btn reminder-apply" data-reminder-apply>Apply and close</button>
        </div>
        <details class="reminder-help">
          <summary>About reminders</summary>
          <p id="match-reminder-timing-help">The next match day starts at midnight in South Africa. If only today’s matches remain, we remind you at the next match time. Reopen anytime using Match reminder in My Tennis.</p>
        </details>
        <div class="reminder-actions">
          <a href="{{ route('my.tennis') }}" class="reminder-all">View all in My Tennis</a>
          <button type="button" class="btn reminder-close" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="{{ asset('js/match-reminder.js') }}?v={{ filemtime(public_path('js/match-reminder.js')) }}" defer></script>
