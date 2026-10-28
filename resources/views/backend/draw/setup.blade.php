@extends('layouts.backend')
@section('title', 'Choose draw format — '.$draw->drawName)
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')

@include('backend.draw.partials.workspace-header', ['workspaceContext' => 'settings', 'workspaceSurface' => 'standalone'])
@include('backend.draw.partials.workspace-links', ['workspaceTab' => 'settings'])
<div class="mx-auto" style="max-width:900px">
  <p class="text-primary mb-1">Step 1 of 5 · Format</p>
  <h3 class="mb-2">How should this draw start?</h3>
  <p class="text-muted mb-4">{{ $draw->drawName }} · Choose the format first. Player placement and format-specific settings come next.</p>
  <details class="alert alert-info mb-4">
    <summary class="fw-semibold" style="cursor:pointer">Help me choose a format</summary>
    <div class="small mt-3">
      <p class="mb-2"><strong>Most tournaments:</strong> choose Round robin → playoffs so everyone plays a group stage before the knockout rounds.</p>
      <p class="mb-2"><strong>Small field or league:</strong> choose Round robin only when final standings should decide the result.</p>
      <p class="mb-2"><strong>Fast knockout:</strong> choose Playoffs only; a player may have only one match.</p>
      <p class="mb-0"><strong>Placement matches:</strong> choose Monrad. Feed-in Monrad lets earlier-round losers meet later-round losers. Custom Monrad is an advanced option for placing players into different starting rounds.</p>
    </div>
  </details>
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @if($draw->locked || $draw->published || $draw->oop_published)
    <div class="alert alert-warning" role="alert">
      Unlock the draw and unpublish both the draw and schedule before changing its format.
    </div>
  @elseif($resetSummary['results'] > 0)
    <div class="alert alert-danger" role="alert">
      This draw has {{ $resetSummary['results'] }} recorded result{{ $resetSummary['results'] === 1 ? '' : 's' }} and cannot be reset from this page.
    </div>
  @elseif($resetSummary['has_format_state'])
    <div class="alert alert-warning" role="alert">
      <strong>Changing format resets this draw.</strong>
      It will permanently remove {{ $resetSummary['fixtures'] }} generated match{{ $resetSummary['fixtures'] === 1 ? '' : 'es' }},
      {{ $resetSummary['scheduled_times'] }} scheduled time{{ $resetSummary['scheduled_times'] === 1 ? '' : 's' }} and current group placements.
      Starting positions and byes are kept when switching between Monrad and Feed-in Monrad; other format changes reset them.
      The draw, venue allocation and {{ $resetSummary['roster_entries'] }} roster entr{{ $resetSummary['roster_entries'] === 1 ? 'y' : 'ies' }} remain available for rebuilding.
    </div>
  @endif
  <form method="POST" action="{{ route('draw.setup.store', $draw) }}" id="draw-format-form"
    data-current-workflow="{{ $draw->settings?->workflow }}" data-current-challenges="{{ ($draw->settings?->playoff_config['challenge_playoffs'] ?? false) ? '1' : '0' }}" data-current-pairs='@json($selectedChallengePairs)' data-has-format-state="{{ $resetSummary['has_format_state'] ? '1' : '0' }}">
    @csrf
    <fieldset @disabled($draw->locked || $draw->published || $draw->oop_published || $resetSummary['results'] > 0)>
      <legend class="visually-hidden">Draw format</legend>
      <div class="row g-3 mb-4">
        @foreach($options as $value => [$label, $description])
          <div class="col-12 col-md-6">
            <label class="card h-100 p-4 d-flex flex-row gap-3" style="cursor:pointer">
              <input type="radio" name="workflow" value="{{ $value }}" class="form-check-input flex-shrink-0" required
                @checked(old('workflow', $draw->settings?->workflow) === $value)>
              <span>
                <strong class="d-flex align-items-center gap-2 mb-2">
                  {{ $label }}
                  @if($value === 'round_robin_playoffs')<span class="badge bg-label-primary">Recommended</span>@endif
                  @if($value === 'custom_monrad')<span class="badge bg-label-secondary">Advanced</span>@endif
                </strong>
                <span class="text-muted d-block">{{ $description }}</span>
                <small class="d-block mt-2">
                  @if($value === 'round_robin') Best when every player should meet everyone in their group.
                  @elseif($value === 'round_robin_playoffs') Best for a full tournament with group play and a championship finish.
                  @elseif($value === 'playoffs') Fastest format, but eliminated players do not continue.
                  @elseif($value === 'monrad') Best when finishing positions and additional matches matter.
                  @elseif($value === 'feed_in_monrad') Use crossed feed-in paths and placement matches, as in the Interpro trials draws.
                  @else Use when seeded players must enter in different rounds.
                  @endif
                </small>
              </span>
            </label>
          </div>
        @endforeach
      </div>
      @if($resetSummary['has_format_state'])
        <p class="text-muted">Switching between Monrad and Feed-in Monrad preserves the bracket size, player starting positions and explicit byes. Fixtures and scheduled times still reset.</p>
        <label class="form-check border rounded p-3 mb-3" for="confirm-format-reset">
          <input class="form-check-input ms-0 me-2" type="checkbox" name="reset_existing" value="1" id="confirm-format-reset">
          <span class="form-check-label"><strong>I understand this removes the current fixtures and scheduled times.</strong></span>
        </label>
      @endif
      <label class="form-check border rounded p-3 mb-3">
        <input type="hidden" name="challenge_playoffs" value="0">
        <input type="checkbox" class="form-check-input ms-0 me-2" name="challenge_playoffs" value="1" id="challenge-playoffs"
          @checked(old('challenge_playoffs', $draw->settings?->playoff_config['challenge_playoffs'] ?? false))>
        <span class="form-check-label"><strong>Optional challenge play-offs (Feed-in Monrad only)</strong><br>
          After every base match is complete, base positions 2 v 3, 4 v 5, 6 v 7 and so on may play once more.
          Players who already played each other in this draw skip the challenge and keep their base positions.
          The winner takes the higher position; first and the last unpaired position stay unchanged.</span>
      </label>
      <div id="challenge-pair-options" class="border rounded p-3 mb-3" hidden>
        <input type="hidden" name="challenge_pairs_submitted" value="1">
        <p class="fw-semibold mb-2">Choose the base-position pairs that may challenge</p>
        <div class="d-flex flex-wrap gap-3">
          @for($upper = 2; $upper < $challengePairLimit; $upper += 2)
            <label class="form-check mb-1 border rounded px-3 py-2 d-flex align-items-center" style="min-height:44px;cursor:pointer"><input type="checkbox" class="form-check-input ms-0 me-2" name="challenge_pairs[]" value="{{ $upper }}"
              @checked(in_array($upper, array_map('intval', (array) old('challenge_pairs', old('challenge_pairs_submitted') ? [] : $selectedChallengePairs)), true))>
              <span class="form-check-label">{{ $upper }} v {{ $upper + 1 }}</span></label>
          @endfor
        </div>
        <p class="form-text mb-0">Select at least one pair. Ranks refer to the completed base draw and remain fixed after challenge upsets. Only pairs present in the final field can play.</p>
      </div>
      <button class="btn btn-primary" type="submit">{{ $resetSummary['has_format_state'] ? 'Change format and reset draw' : 'Continue to players →' }}</button>
    </fieldset>
  </form>
  <p class="text-muted small mt-3">Recorded results are always protected. A confirmed reset keeps the draw and eligible roster, but removes generated fixtures and timetable entries.</p>
</div>
<script>
(() => {
  const form = document.getElementById('draw-format-form');
  const option = document.getElementById('challenge-playoffs');
  const pairs = document.getElementById('challenge-pair-options');
  const refresh = () => {
    option.disabled = form.querySelector('[name="workflow"]:checked')?.value !== 'feed_in_monrad';
    pairs.hidden = option.disabled || !option.checked;
    pairs.querySelectorAll('input').forEach(input => { input.disabled = pairs.hidden; });
  };
  form.querySelectorAll('[name="workflow"]').forEach(input => input.addEventListener('change', refresh));
  option.addEventListener('change', refresh);
  refresh();
})();
</script>
@if($resetSummary['has_format_state'])
<script>
document.getElementById('draw-format-form')?.addEventListener('submit', event => {
  const form = event.currentTarget;
  const selected = form.querySelector('input[name="workflow"]:checked')?.value;
  const challenges = selected === 'feed_in_monrad' && form.querySelector('#challenge-playoffs').checked;
  const pairs = challenges ? [...form.querySelectorAll('[name="challenge_pairs[]"]:checked')].map(input => Number(input.value)).sort((a, b) => a - b) : [];
  const previousPairs = JSON.parse(form.dataset.currentPairs || '[]').map(Number).sort((a, b) => a - b);
  if (!selected || (selected === form.dataset.currentWorkflow && challenges === (form.dataset.currentChallenges === '1') && JSON.stringify(pairs) === JSON.stringify(previousPairs))) return;
  const confirmation = document.getElementById('confirm-format-reset');
  if (!confirmation?.checked) {
    event.preventDefault();
    confirmation?.focus();
    confirmation?.setCustomValidity('Confirm the draw reset before changing format.');
    confirmation?.reportValidity();
    return;
  }
  confirmation.setCustomValidity('');
  const preservesStarts = ['monrad', 'feed_in_monrad'].includes(form.dataset.currentWorkflow) && ['monrad', 'feed_in_monrad'].includes(selected);
  const placementWarning = preservesStarts ? ' Starting positions and byes are kept.' : ' Group and starting-position placements are removed.';
  if (!window.confirm('Change draw format or challenge option? This permanently removes the current fixtures and scheduled times.' + placementWarning + ' Recorded results are protected.')) {
    event.preventDefault();
  }
});
document.getElementById('confirm-format-reset')?.addEventListener('change', event => event.currentTarget.setCustomValidity(''));
</script>
@endif
<dialog id="draw-progress-modal" aria-labelledby="draw-progress-title" aria-describedby="draw-progress-description" style="border:0;border-radius:1rem;padding:2rem;max-width:min(420px,calc(100vw - 2rem));text-align:center;color:inherit">
  <div class="spinner-border text-primary mb-3" aria-hidden="true"></div>
  <h2 id="draw-progress-title" class="h5">Updating draw…</h2>
  <p id="draw-progress-description" class="mb-0" role="status" aria-live="polite">Please wait while we save the draw setup. This can take a moment.</p>
</dialog>
<style>#draw-progress-modal::backdrop{background:rgba(15,23,42,.55)}</style>
<script>
(() => {
  const form = document.getElementById('draw-format-form');
  const modal = document.getElementById('draw-progress-modal');
  const button = form.querySelector('button[type="submit"]');
  let submitting = false;
  let slowTimer;
  modal.addEventListener('cancel', event => event.preventDefault());
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    if (submitting) {
      event.preventDefault();
      return;
    }
    submitting = true;
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    modal.showModal();
    slowTimer = window.setTimeout(() => {
      document.getElementById('draw-progress-description').textContent = 'This is taking longer than usual. Please keep this page open and avoid submitting again while the request is processing.';
    }, 20000);
  });
  window.addEventListener('pageshow', () => {
    window.clearTimeout(slowTimer);
    submitting = false;
    button.disabled = false;
    form.removeAttribute('aria-busy');
    if (modal.open) modal.close();
    document.getElementById('draw-progress-description').textContent = 'Please wait while we save the draw setup. This can take a moment.';
  });
})();
</script>
</div>
@endsection
