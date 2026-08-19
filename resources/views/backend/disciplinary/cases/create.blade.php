@extends('layouts/layoutMaster')
@section('title', 'Report Disciplinary Incident')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="mb-4"><h4 class="mb-1"><i class="ti ti-alert-triangle me-2"></i>Report incident</h4><p class="text-muted mb-0">{{ $event->name }} · this creates an allegation for triage, not an automatic finding.</p></div>
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form method="POST" action="{{ route('backend.events.disciplinary.store', $event) }}" class="card">@csrf
    <div class="card-body"><div class="row g-4">
      <div class="col-md-6"><label class="form-label">Player *</label><select name="player_id" class="form-select" required><option value="">Select an entered player</option>@foreach($players as $player)<option value="{{ $player->id }}" @selected(old('player_id') == $player->id)>{{ $player->full_name }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Offence type</label><select name="violation_type_id" class="form-select"><option value="">Other rule</option>@foreach($violationTypes as $type)<option value="{{ $type->id }}" @selected(old('violation_type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label">Category</label><select name="category_event_id" class="form-select"><option value="">Not specified</option>@foreach($categories as $category)<option value="{{ $category->pivot->id }}">{{ $category->name }}</option>@endforeach</select></div>
      <div class="col-md-8"><label class="form-label">Fixture</label><select name="fixture_id" class="form-select"><option value="">Not match-specific</option>@foreach($fixtures as $fixture)<option value="{{ $fixture->id }}">#{{ $fixture->id }} · {{ $fixture->registration1?->displayName() }} vs {{ $fixture->registration2?->displayName() }}</option>@endforeach</select><small class="text-muted">When selected, the server verifies that the player is in this match.</small></div>
      <div class="col-md-4"><label class="form-label">Incident date and time *</label><input type="datetime-local" name="incident_at" value="{{ old('incident_at', now()->format('Y-m-d\TH:i')) }}" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Severity *</label><select name="severity" class="form-select" required>@foreach(['standard','serious','urgent'] as $severity)<option value="{{ $severity }}">{{ ucfirst($severity) }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label">Court / location</label><input name="incident_location" value="{{ old('incident_location') }}" class="form-control" maxlength="255"></div>
      <div class="col-md-4"><label class="form-label">Rule code</label><input name="rule_code" value="{{ old('rule_code') }}" class="form-control"></div>
      <div class="col-md-8"><label class="form-label">Other rule title</label><input name="rule_title" value="{{ old('rule_title') }}" class="form-control"><small class="text-muted">Required only if no configured offence type is selected.</small></div>
      <div class="col-12"><label class="form-label">Factual incident summary *</label><textarea name="summary" rows="5" class="form-control" required>{{ old('summary') }}</textarea></div>
      <div class="col-12"><label class="form-label">Official statement / witness detail</label><textarea name="statement" rows="5" class="form-control">{{ old('statement') }}</textarea></div>
    </div></div>
    <div class="card-footer d-flex justify-content-between"><a href="{{ route('backend.events.disciplinary.index', $event) }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary">Submit for triage</button></div>
  </form>
</div>
@endsection
