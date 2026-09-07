@extends('layouts.backend')
@section('title', 'Choose draw format — '.$draw->drawName)
@section('content')
@include('backend.draw.partials.workspace-header', ['workspaceContext' => 'settings'])
@include('backend.draw.partials.workspace-links', ['workspaceTab' => 'settings'])
<div class="mx-auto" style="max-width:900px">
  <p class="text-primary mb-1">Step 1 · Draw format</p>
  <h3 class="mb-2">How should this draw start?</h3>
  <p class="text-muted mb-4">{{ $draw->drawName }} · Choose the format first. Player placement and format-specific settings come next.</p>
  <details class="alert alert-info mb-4">
    <summary class="fw-semibold" style="cursor:pointer">Help me choose a format</summary>
    <div class="small mt-3">
      <p class="mb-2"><strong>Most tournaments:</strong> choose Round robin → playoffs so everyone plays a group stage before the knockout rounds.</p>
      <p class="mb-2"><strong>Small field or league:</strong> choose Round robin only when final standings should decide the result.</p>
      <p class="mb-2"><strong>Fast knockout:</strong> choose Playoffs only; a player may have only one match.</p>
      <p class="mb-0"><strong>Placement matches:</strong> choose Monrad. Custom Monrad is an advanced option for placing players into different starting rounds.</p>
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
      {{ $resetSummary['scheduled_times'] }} scheduled time{{ $resetSummary['scheduled_times'] === 1 ? '' : 's' }}, and current group or starting-position placements.
      The draw, venue allocation and {{ $resetSummary['roster_entries'] }} roster entr{{ $resetSummary['roster_entries'] === 1 ? 'y' : 'ies' }} remain available for rebuilding.
    </div>
  @endif
  <form method="POST" action="{{ route('draw.setup.store', $draw) }}" id="draw-format-form"
    data-current-workflow="{{ $draw->settings?->workflow }}" data-has-format-state="{{ $resetSummary['has_format_state'] ? '1' : '0' }}">
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
                  @else Use when seeded players must enter in different rounds.
                  @endif
                </small>
              </span>
            </label>
          </div>
        @endforeach
      </div>
      @if($resetSummary['has_format_state'])
        <label class="form-check border rounded p-3 mb-3" for="confirm-format-reset">
          <input class="form-check-input ms-0 me-2" type="checkbox" name="reset_existing" value="1" id="confirm-format-reset">
          <span class="form-check-label"><strong>I understand this removes the current fixtures and scheduled times.</strong></span>
        </label>
      @endif
      <button class="btn btn-primary" type="submit">{{ $resetSummary['has_format_state'] ? 'Change format and reset draw' : 'Continue to setup →' }}</button>
    </fieldset>
  </form>
  <p class="text-muted small mt-3">Recorded results are always protected. A confirmed reset keeps the draw and eligible roster, but removes generated fixtures and timetable entries.</p>
</div>
@if($resetSummary['has_format_state'])
<script>
document.getElementById('draw-format-form')?.addEventListener('submit', event => {
  const form = event.currentTarget;
  const selected = form.querySelector('input[name="workflow"]:checked')?.value;
  if (!selected || selected === form.dataset.currentWorkflow) return;
  const confirmation = document.getElementById('confirm-format-reset');
  if (!confirmation?.checked) {
    event.preventDefault();
    confirmation?.focus();
    confirmation?.setCustomValidity('Confirm the draw reset before changing format.');
    confirmation?.reportValidity();
    return;
  }
  confirmation.setCustomValidity('');
  if (!window.confirm('Change draw format? This permanently removes the current fixtures, scheduled times, and group or starting-position placements. Recorded results are protected.')) {
    event.preventDefault();
  }
});
document.getElementById('confirm-format-reset')?.addEventListener('change', event => event.currentTarget.setCustomValidity(''));
</script>
@endif
@endsection
