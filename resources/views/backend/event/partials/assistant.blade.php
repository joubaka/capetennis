@if(auth()->user()?->hasRole('super-user'))
@once
<link rel="stylesheet" href="{{ asset('css/event-assistant.css') }}?v={{ filemtime(public_path('css/event-assistant.css')) }}">
<div id="event-assistant-backdrop" class="ea-backdrop" hidden></div>
<section id="event-assistant-panel" class="ea-panel" role="dialog" aria-modal="true" aria-labelledby="ea-title" hidden
  data-auto-open="{{ request()->routeIs('backend.event-assistant.workspace') ? 'true' : 'false' }}"
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
    <p class="ea-intro">Plan schedules, review the order of play, prepare a draw or draft an email. Review changes and recipients before confirming.</p>
    <p class="ea-intro"><a href="{{ route('backend.event-assistant.workspace', $event) }}">Open the dedicated assistant workspace</a></p>
    @if(config('capester.enabled'))
    <p class="ea-intro"><a href="{{ route('backend.capester.index', $event) }}">Send a task to Capester</a></p>
    @endif
    @if(app(\App\Services\Assistant\AssistantImprovementHandoff::class)->optedIn(auth()->user()))
    <p class="ea-intro">Your account is opted in to background improvement. While the Capester queue is enabled and available, unsupported tasks and Needs improvement feedback can save one instruction excerpt for future capability review. Drafts and conversation history are excluded. A saved note does not execute the task or deploy a fix.</p>
    @endif
    <button type="button" data-ea-shortcut aria-expanded="false" aria-controls="ea-shortcut-help">Add to home screen</button>
    <div id="ea-shortcut-help" hidden>
      <p>Add a Cape Tennis Assistant shortcut for this event. Your browser requires you to confirm adding it.</p>
      <ol>
        <li>Open the <a href="{{ route('backend.event-assistant.workspace', ['event' => $event, 'home-screen' => 1]) }}" target="_blank" rel="noopener">assistant shortcut page</a> in Chrome (Safari on iPhone). If it stays inside the installed app, copy the address below and paste it into your browser.</li>
        <li>In Chrome, open the ⋮ menu, choose <strong>Add to home screen</strong>, then <strong>Create shortcut</strong> if offered. On iPhone, use Share → Add to Home Screen.</li>
        <li>Name it <strong>Cape Tennis Assistant</strong> and tap Add. If your browser only offers the main Cape Tennis installation, use a browser bookmark instead; that installation opens the main app.</li>
      </ol>
      <label>Shortcut address<input type="text" readonly data-ea-shortcut-url value="{{ route('backend.event-assistant.workspace', ['event' => $event, 'home-screen' => 1]) }}"></label>
      <button type="button" data-ea-shortcut-copy>Copy shortcut address</button>
      <p data-ea-shortcut-status role="status"></p>
      <p>The shortcut opens this event’s assistant. Sign in if asked, then tap the microphone to speak.</p>
    </div>
    <p class="ea-status" role="status" aria-live="polite" data-ea-status></p>
    <div class="ea-messages" role="log" aria-label="Assistant conversation" aria-live="polite" data-ea-messages></div>
    <div class="ea-suggestions" data-ea-suggestions>
      <button type="button" data-ea-prompt="Help me schedule this event">Plan a schedule</button>
      <button type="button" data-ea-prompt="Review publication">Review order of play</button>
      <button type="button" data-ea-prompt="Help me create a draw">Prepare a draw</button>
      <button type="button" data-ea-prompt="Draft an email">Draft an email</button>
    </div>
    <section class="ea-proposal" data-ea-proposal hidden aria-label="Proposed changes"></section>
    <section class="ea-proposal" data-ea-email hidden aria-label="Email draft">
      <h3>Email draft</h3>
      <p>Describe corrections in Your request, or edit this draft. With Use AI selected, your instructions and this visible draft are sent to the AI provider. Recorded player notification contacts may include parents; check the exact recipients on the next page.</p>
      <form method="post" action="{{ route('backend.event-communications.preview', $event) }}" data-ea-email-form class="ea-grid">
        @csrf
        <input type="hidden" name="assistant_conversation_uuid">
        <input type="hidden" name="filter" value="all">
        <input type="hidden" name="recipients" value="players">
        <label class="ea-full">Email subject<input name="subject" maxlength="200" required></label>
        <label class="ea-full">Email message<textarea name="body" rows="12" maxlength="30000" required></textarea></label>
        <label class="ea-full">Email audience<select name="scope" required><option value="">Choose recipients to review</option></select></label>
        <label class="ea-full" data-ea-email-direct hidden>Recipient email<input name="direct_email" type="email" maxlength="255"></label>
        <button type="submit" class="ea-primary ea-full">Review recipients and email</button>
      </form>
      <p>Reviewing does not send. The next page shows the sender, exact message, recipients and missing contacts, then asks you to send.</p>
      <p>For a specific region, team or player, copy this draft into the <a href="{{ route('backend.event-communications.index', $event) }}">event email composer</a> and choose that audience there.</p>
      <button type="button" data-ea-email-close>Close email draft</button>
    </section>
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
    <label class="ea-ai"><input type="checkbox" data-ea-ai> Use AI to interpret this request or draft an email</label>
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
