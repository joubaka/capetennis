@extends('layouts.backend')
@section('title', 'Round robin and placement playoffs')
@section('content')
<div class="container-xxl" id="placement-competition" data-url="{{ route('team-draw.competition.store', $event) }}">
  <style>#placement-competition .btn,#placement-competition select,#placement-competition input{min-height:44px}#placement-competition .match-rows{display:grid;gap:1rem}#placement-competition .match-row{padding:1rem;border:1px solid #d8dfe5;border-radius:.5rem}#placement-competition .row>*{min-width:0}</style>
  <h1 class="h4">{{ $event->name }}: round robin and placement playoffs</h1>
  <p>Choose an age group. Each region combines its boys and girls teams into one side, in one round-robin group. The top four qualify for 1st/2nd and 3rd/4th playoffs.</p>
  <p>Starting formats use each selected team's configured size: half as many doubles per gender, and mixed doubles for every boy and girl. Playoffs also start with singles for every player. Edit either phase before creating the group.</p>
  <div class="alert alert-info">Scoring: <strong>To be decided.</strong> You can create and schedule matches now. Confirm scoring before publishing ties, entering results or qualifying teams.</div>
  <form id="competition-form">
    @csrf
    <label class="form-label" for="competition-group">Age group</label>
    <select class="form-select mb-3" id="competition-group" required><option value="">Choose an age group</option>@foreach($groups as $index => $group)<option value="{{ $index }}">{{ $group['label'] }}</option>@endforeach</select>
    <div id="competition-team-summary" class="mb-3" aria-live="polite"></div>
    <div class="alert alert-secondary">Pairing positions: Boy 1 = 1, Girl 1 = 2, Boy 2 = 3, Girl 2 = 4, and so on. Players may play in more than one match. Each mixed pair must contain one boy and one girl.</div>
    @foreach(['round_robin' => 'Round-robin matches per regional encounter', 'playoff' => 'Playoff matches per regional encounter'] as $phase => $label)
      <details class="card mb-3"><summary class="card-header"><h2 class="h5 d-inline">{{ $label }}</h2> <span data-count="{{ $phase }}"></span></summary><div class="card-body">
        <div class="match-rows" data-phase="{{ $phase }}"></div>
        <button class="btn btn-outline-secondary mt-3" type="button" data-add="{{ $phase }}">Add match</button>
      </div></details>
    @endforeach
    <div id="competition-message" role="status" aria-live="polite" class="mb-3"></div>
    <button class="btn btn-primary" id="competition-submit" disabled>Create this age-group competition</button>
  </form>
  <script type="application/json" id="competition-groups">{!! json_encode($groups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
@endsection
@section('page-script')
<script src="{{ asset('js/team-placement-competition.js') }}?v={{ filemtime(public_path('js/team-placement-competition.js')) }}"></script>
@endsection
