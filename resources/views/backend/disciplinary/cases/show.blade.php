@extends('layouts.backend')
@section('title', $case->case_number)
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-4"><div><h4 class="mb-1">{{ $case->case_number }}</h4><p class="text-muted mb-0">{{ $case->event->name }} · {{ $case->player->full_name }}</p></div><span class="badge bg-label-warning align-self-start fs-6">{{ str($case->status)->replace('_',' ')->title() }}</span></div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @php($disciplinaryEnabled = \App\Models\SiteSetting::disciplinarySystemEnabled())
  @unless($disciplinaryEnabled)<div class="alert alert-warning"><i class="ti ti-lock me-1"></i>The disciplinary case system is disabled. This case is read-only and remains available for audit.</div>@endunless

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Incident and charges</h5></div><div class="card-body">
        <dl class="row mb-0"><dt class="col-sm-3">Incident</dt><dd class="col-sm-9">{{ $case->incident_at->format('d M Y H:i') }} · {{ $case->incident_location ?: 'Location not supplied' }}</dd><dt class="col-sm-3">Reported by</dt><dd class="col-sm-9">{{ $case->reporter->name }}</dd><dt class="col-sm-3">Summary</dt><dd class="col-sm-9" style="white-space:pre-wrap">{{ $case->summary }}</dd></dl>
        @foreach($case->charges as $charge)<div class="border rounded p-3 mt-3"><strong>{{ $charge->rule_code ? $charge->rule_code.' · ' : '' }}{{ $charge->rule_title }}</strong><div class="text-muted mt-1">{{ $charge->allegation }}</div><span class="badge bg-label-secondary mt-2">{{ ucfirst($charge->finding) }}{{ $charge->finding === 'proven' ? ' · '.$charge->points.' points' : '' }}</span></div>@endforeach
      </div></div>

      <div class="card mb-4"><div class="card-header d-flex justify-content-between"><h5 class="mb-0">Evidence</h5><span class="badge bg-secondary">Private</span></div><div class="card-body">
        @forelse($case->evidence as $item)<div class="border-bottom pb-3 mb-3"><strong>{{ $item->title }}</strong><small class="text-muted d-block">{{ $item->submitter?->name }} · {{ $item->created_at->format('d M Y H:i') }}</small>@if($item->statement)<p class="mt-2 mb-0" style="white-space:pre-wrap">{{ $item->statement }}</p>@endif @if($item->file_path)<a href="{{ route('backend.disciplinary.cases.evidence.download', [$case, $item]) }}">Download {{ $item->original_name }}</a>@endif</div>@empty<p class="text-muted">No evidence added.</p>@endforelse
        @if($disciplinaryEnabled)<form method="POST" enctype="multipart/form-data" action="{{ route('backend.disciplinary.cases.evidence.store', $case) }}" class="row g-2">@csrf<div class="col-md-4"><input name="title" class="form-control" placeholder="Evidence title" required></div><div class="col-md-5"><input type="file" name="evidence_file" class="form-control" required></div><div class="col-md-3"><button class="btn btn-outline-primary w-100">Upload</button></div></form>@endif
      </div></div>

      @if($disciplinaryEnabled) @can('manage', $case)
      @if(in_array($case->status, ['submitted','triage']))
      <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Triage</h5></div><div class="card-body"><form method="POST" action="{{ route('backend.disciplinary.cases.triage', $case) }}">@csrf<textarea name="reason" class="form-control mb-3" rows="3" placeholder="Triage notes; mandatory when dismissing"></textarea><div class="d-flex gap-2"><button name="action" value="proceed" class="btn btn-primary">Issue notice and request response</button><button name="action" value="dismiss" class="btn btn-outline-danger">Dismiss with reason</button></div></form></div></div>
      @endif

      @if(in_array($case->status, ['awaiting_response','panel_review']))
      <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Appoint independent panel</h5></div><div class="card-body"><form method="POST" action="{{ route('backend.disciplinary.cases.panel', $case) }}">@csrf<div class="row g-3">@for($i=0;$i<3;$i++)<div class="col-md-8"><select name="members[{{ $i }}][user_id]" class="form-select" required><option value="">Select panel member</option>@foreach($panelCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }}</option>@endforeach</select></div><div class="col-md-4"><select name="members[{{ $i }}][role]" class="form-select"><option value="{{ $i===0 ? 'chair' : 'member' }}">{{ $i===0 ? 'Chair' : 'Member' }}</option></select></div>@endfor</div><button class="btn btn-outline-primary mt-3">Appoint panel</button></form></div></div>
      @endif
      @endcan @endif

      @if($disciplinaryEnabled) @can('decide', $case)
      @if(!$case->decisions->count())
      <div class="card border-primary mb-4"><div class="card-header"><h5 class="mb-0">Finalize panel decision</h5></div><div class="card-body"><form method="POST" action="{{ route('backend.disciplinary.cases.decision', $case) }}">@csrf<div class="row g-3"><div class="col-md-4"><label class="form-label">Outcome</label><select name="outcome" class="form-select"><option value="upheld">Upheld</option><option value="partially_upheld">Partially upheld</option><option value="dismissed">Not proven / dismissed</option></select></div><div class="col-md-4"><label class="form-label">Sanction</label><select name="sanctions[0][type]" class="form-select"><option value="warning">Formal warning</option><option value="points">Points</option><option value="event_disqualification">Event disqualification</option><option value="suspension">Suspension</option></select></div><div class="col-md-4"><label class="form-label">Scope</label><select name="sanctions[0][scope]" class="form-select"><option value="event">This event</option><option value="series">Series</option><option value="global">All Cape Tennis events</option></select></div><div class="col-md-6"><label class="form-label">Starts</label><input type="date" name="sanctions[0][starts_at]" value="{{ today()->format('Y-m-d') }}" class="form-control"></div><div class="col-md-6"><label class="form-label">Ends</label><input type="date" name="sanctions[0][ends_at]" class="form-control"></div><div class="col-12"><label class="form-label">Full reasons *</label><textarea name="reasons" class="form-control" rows="7" required minlength="20"></textarea></div><div class="col-12"><label class="form-label">Sanction details</label><textarea name="sanctions[0][details]" class="form-control" rows="3"></textarea></div></div><div class="alert alert-warning mt-3">Final decisions are immutable. Corrections must go through an appeal or formal reversal.</div><button class="btn btn-primary">Finalize and serve decision</button></form></div></div>
      @endif
      @endcan @endif

      @foreach($case->decisions as $decision)<div class="card border-success mb-4"><div class="card-header"><h5 class="mb-0">Decision: {{ str($decision->outcome)->replace('_',' ')->title() }}</h5></div><div class="card-body"><p style="white-space:pre-wrap">{{ $decision->reasons }}</p>@foreach($decision->sanctions as $sanction)<span class="badge bg-label-danger me-2">{{ str($sanction->type)->replace('_',' ')->title() }} · {{ ucfirst($sanction->scope) }} @if($sanction->ends_at)to {{ $sanction->ends_at->format('d M Y') }}@endif</span>@endforeach</div></div>@endforeach
    </div>

    <div class="col-lg-4">
      <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Panel</h5></div><div class="card-body">@forelse($case->assignments as $assignment)<div class="mb-3"><strong>{{ $assignment->user->name }}</strong> <span class="badge bg-label-primary">{{ ucfirst($assignment->role) }}</span>@if($assignment->recused_at)<span class="badge bg-danger">Recused</span>@endif @if($assignment->user_id === auth()->id() && !$assignment->recused_at)<form method="POST" action="{{ route('backend.disciplinary.cases.panel.conflict', [$case,$assignment]) }}" class="mt-2">@csrf<input type="hidden" name="conflict" value="1"><input name="notes" class="form-control form-control-sm mb-1" placeholder="Conflict reason"><button class="btn btn-sm btn-outline-danger">Declare conflict and recuse</button></form>@endif</div>@empty<p class="text-muted mb-0">No panel appointed.</p>@endforelse</div></div>
      <div class="card"><div class="card-header"><h5 class="mb-0">Audit timeline</h5></div><div class="card-body">@foreach($case->timeline->sortByDesc('created_at') as $entry)<div class="border-start ps-3 pb-3"><strong>{{ str($entry->action)->replace(['.','_'],' ')->title() }}</strong><small class="text-muted d-block">{{ $entry->created_at->format('d M Y H:i') }} · {{ $entry->actor?->name ?? 'System' }}</small>@if($entry->notes)<div class="small mt-1">{{ $entry->notes }}</div>@endif</div>@endforeach</div></div>
    </div>
  </div>
</div>
@endsection
