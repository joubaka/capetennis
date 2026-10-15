@extends('layouts.backend')
@section('title', 'Round robin and placement playoffs')
@section('content')
@include('backend.draw.partials.guide-visibility')
<div class="container-xxl" id="placement-competition" data-url="{{ route('team-draw.competition.store', $event) }}">
  <style>#placement-competition .btn,#placement-competition select,#placement-competition input{min-height:44px}#placement-competition .match-rows{display:grid;gap:1rem}#placement-competition .match-row{padding:1rem;border:1px solid #d8dfe5;border-radius:.5rem}#placement-competition .row>*{min-width:0}</style>
  <a href="{{ route('headOffice.show', $event) }}" class="btn btn-link px-0 mb-2">← Back to event</a>
  <h1 class="h4">{{ $event->name }}: create competition &amp; programme</h1>
  <ol class="d-flex flex-wrap gap-3 small" aria-label="Draw setup progress"><li>Competition: teams</li><li>Participants</li><li>Competition format</li><li>Match setup</li><li>Programme &amp; schedule</li><li>Review &amp; publish</li></ol>
  <p class="text-primary" data-competition-step role="status">Step 2 of 6 · Participants</p>
  <section data-competition-panel="2">
  <p>Choose an age group. Each region combines its boys and girls teams into one side, in one round-robin group. The top four qualify for 1st/2nd and 3rd/4th playoffs.</p>
  <p>Starting formats use each selected team's configured size: half as many doubles per gender, and mixed doubles for every boy and girl. Playoffs also start with singles for every player. Edit either phase before creating the group.</p>
  <div class="alert alert-info">Scoring: <strong>To be decided.</strong> You can create and schedule matches now. Confirm scoring before publishing ties, entering results or qualifying teams.</div>
  </section>
  <form id="competition-form">
    @csrf
    <section data-competition-panel="2">
    <label class="form-label" for="competition-group">Age group</label>
    <select class="form-select mb-3" id="competition-group" required><option value="">Choose an age group</option>@foreach($groups as $index => $group)<option value="{{ $index }}">{{ $group['label'] }}</option>@endforeach</select>
    <div id="competition-team-summary" class="mb-3" aria-live="polite"></div>
    </section>
    <section data-competition-panel="3" hidden>
      <h2 class="h5">Competition format</h2>
      <label class="form-check border rounded p-3"><input type="radio" class="form-check-input" checked name="competition_format" value="round_robin_playoffs"><span class="form-check-label">Round robin with placement playoffs</span></label>
      <p class="mt-3">Every regional side plays each other once. After every group encounter is complete and standings are confirmed, the top four qualify for 1st/2nd and 3rd/4th playoffs.</p>
    </section>
    <section data-competition-panel="4" hidden>
    <h2 class="h5">Match setup</h2>
    <p data-competition-selected-summary class="fw-semibold"></p>
    <p>Review the matches for both phases. Saving creates the group encounters and keeps the playoff format for qualification later. You will continue to the programme and schedule.</p>
    <p><strong>Scoring: to be decided.</strong> After saving, use “Confirm scoring before publication” in the setup guide to choose the scoring rules. Programme planning can continue first.</p>
    <div class="alert alert-secondary">Pairing positions: Boy 1 = 1, Girl 1 = 2, Boy 2 = 3, Girl 2 = 4, and so on. Players may play in more than one match. Each mixed pair must contain one boy and one girl.</div>
    @foreach(['round_robin' => 'Round-robin matches per regional encounter', 'playoff' => 'Playoff matches per regional encounter'] as $phase => $label)
      <details class="card mb-3"><summary class="card-header"><h2 class="h5 d-inline">{{ $label }}</h2> <span data-count="{{ $phase }}"></span></summary><div class="card-body">
        <div class="match-rows" data-phase="{{ $phase }}"></div>
        <button class="btn btn-outline-secondary mt-3" type="button" data-add="{{ $phase }}">Add match</button>
      </div></details>
    @endforeach
    </section>
    <div id="competition-message" role="status" aria-live="polite" class="mb-3"></div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-secondary" data-competition-back hidden>Back</button>
      <button type="button" class="btn btn-primary" data-competition-next>Continue</button>
      <button class="btn btn-primary" id="competition-submit" disabled hidden>Save matches &amp; continue to programme</button>
    </div>
  </form>
  <script type="application/json" id="competition-groups">{!! json_encode($groups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
@endsection
@section('page-script')
<script src="{{ asset('js/team-placement-competition.js') }}?v={{ filemtime(public_path('js/team-placement-competition.js')) }}"></script>
@endsection
