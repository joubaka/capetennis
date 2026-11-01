@if(auth()->user()?->hasRole('super-user'))
@once
<link rel="stylesheet" href="{{ asset('css/event-assistant.css') }}?v={{ filemtime(public_path('css/event-assistant.css')) }}">
<div id="event-assistant-backdrop" class="ea-backdrop" hidden></div>
<section id="event-assistant-panel" class="ea-panel" role="dialog" aria-modal="true" aria-labelledby="ea-title" hidden
  data-context-url="{{ route('backend.event-assistant.context', $event) }}"
  data-message-url="{{ route('backend.event-assistant.message', $event) }}"
  data-preview-url="{{ route('backend.event-assistant.preview', $event) }}"
  data-publication-url="{{ route('backend.event-assistant.publication-preview', $event) }}"
  data-generation-url="{{ route('backend.event-assistant.generation-preview', $event) }}"
  data-confirm-url="{{ route('backend.event-assistant.confirm', $event) }}"
  data-feedback-url="{{ route('backend.event-assistant.feedback', $event) }}"
  data-preferences-url="{{ route('backend.event-assistant.preferences', $event) }}"
  data-transcribe-url="{{ route('backend.event-assistant.transcribe', $event) }}"
  data-improvements-url="{{ route('backend.event-assistant.improvements', $event) }}"
  data-timing-url="{{ route('backend.event-assistant.timing-observations', $event) }}"
  data-timing-matches-url="{{ route('backend.event-assistant.timing-matches', $event) }}"
  data-duration-url="{{ route('backend.event-assistant.duration-suggestion', $event) }}"
  data-event-name="{{ $event->name }}">
  <header class="ea-header">
    <div><h2 id="ea-title">Tournament assistant</h2><p>{{ $event->name }}</p></div>
    <button type="button" class="ea-icon" data-ea-close aria-label="Close assistant">×</button>
  </header>
  <div class="ea-scroll">
    <p class="ea-intro">Plan schedules, review the order of play or prepare a draw. Review each proposal before confirming it.</p>
    <p class="ea-intro"><a href="{{ route('backend.event-assistant.workspace', $event) }}">Open the dedicated assistant workspace</a></p>
    <p class="ea-status" role="status" aria-live="polite" data-ea-status></p>
    <div class="ea-messages" role="log" aria-label="Assistant conversation" aria-live="polite" data-ea-messages></div>
    <div class="ea-suggestions" data-ea-suggestions>
      <button type="button" data-ea-prompt="Help me schedule this event">Plan a schedule</button>
      <button type="button" data-ea-prompt="Review publication">Review order of play</button>
      <button type="button" data-ea-prompt="Help me create a draw">Prepare a draw</button>
    </div>
    <section class="ea-proposal" data-ea-proposal hidden aria-label="Proposed changes"></section>
    <details class="ea-details" data-ea-planner>
      <summary>Schedule details</summary>
      <form data-ea-plan-form class="ea-grid">
        <label>Date<input type="date" name="date" required></label>
        <label>Start<input type="time" name="start_time" value="08:00" required></label>
        <label>Finish by<input type="time" name="end_time" value="18:00" required></label>
        <label>Playing time (minutes)<input type="number" name="duration" value="60" min="15" max="240" required></label>
        <label>Changeover (minutes)<input type="number" name="court_gap" value="5" min="0" max="60" required></label>
        <label>Player rest (minutes)<input type="number" name="player_rest" value="60" min="0" max="480" required></label>
        <fieldset class="ea-full"><legend>Draws</legend><div data-ea-draws class="ea-choices"></div></fieldset>
        <fieldset class="ea-full"><legend>Venues</legend><div data-ea-venues class="ea-choices"></div></fieldset>
        <button type="submit" class="ea-primary ea-full">Calculate preview</button>
        <button type="button" class="ea-full" data-ea-publication>Review selected order of play</button>
        <button type="button" class="ea-full" data-ea-generation>Review round-robin generation</button>
      </form>
    </details>
    <details class="ea-details">
      <summary>Preferences and improvement</summary>
      <p>Remember timing defaults for this event and your account. Match rules still take priority.</p>
      <button type="button" data-ea-remember>Remember current timing defaults</button>
      <button type="button" data-ea-review>Show feedback summary</button>
      <p data-ea-metrics></p>
      <button type="button" data-ea-duration>Suggest duration for selected draw</button>
      <p data-ea-duration-result></p>
      <details class="ea-details">
        <summary>Record verified match timing</summary>
        <p>Use actual observed playing times. Result-entry times are not match finish times.</p>
        <button type="button" data-ea-load-matches>Load completed matches for selected draw</button>
        <form data-ea-timing-form class="ea-grid">
          <input type="hidden" name="fixture_kind" value="individual">
          <label class="ea-full">Completed match<select name="fixture_id" required><option value="">Load matches from one selected draw</option></select></label>
          <label>Actual start<input type="datetime-local" name="actual_start" required></label>
          <label>Actual finish<input type="datetime-local" name="actual_finish" required></label>
          <label class="ea-full ea-ack"><input type="checkbox" name="interrupted"> Match had interruptions</label>
          <label class="ea-full ea-ack"><input type="checkbox" name="verified" required> I verified these actual playing times</label>
          <button type="submit" class="ea-full">Record timing</button>
        </form>
      </details>
    </details>
  </div>
  <form class="ea-compose" data-ea-message-form>
    <label for="ea-input">Your request</label>
    <textarea id="ea-input" name="message" rows="3" maxlength="2000" placeholder="Schedule under-14 boys on Saturday from 8 am…" required></textarea>
    <label class="ea-ai"><input type="checkbox" data-ea-ai> Use AI to interpret this request</label>
    <div class="ea-compose-actions">
      <button type="button" data-ea-mic>Speak</button>
      <button type="button" data-ea-cancel-voice hidden>Discard recording</button>
      <button type="submit" class="ea-primary" data-ea-send>Send</button>
      <button type="button" data-ea-new>New conversation</button>
    </div>
    <p class="ea-privacy" data-ea-voice-privacy>Speak sends your recording for transcription. Review the transcript before sending. AI interpretation is optional; confirmations always use Cape Tennis permissions.</p>
  </form>
</section>
<script src="{{ asset('js/event-assistant.js') }}?v={{ filemtime(public_path('js/event-assistant.js')) }}" defer></script>
@endonce
@endif
