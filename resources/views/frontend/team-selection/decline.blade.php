@extends('layouts/contentNavbarLayout')
@section('title', 'Decline invitation')
@section('content')
<div class="container py-4"><div class="row justify-content-center"><div class="col-lg-6">
  <div class="card"><div class="card-body">
    @if($invitation->status === \App\Models\TeamSelectionInvitation::DECLINED)
      <h4>Invitation declined</h4>
      <p class="mb-0">Your decline for {{ $invitation->player?->full_name }} at {{ $invitation->selectionImport?->event?->name }} has been recorded.</p>
    @elseif(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
      <h4>Decline invitation?</h4>
      <p>{{ $invitation->player?->full_name }} · {{ $invitation->selectionImport?->event?->name }}</p>
      <p>Confirm that you are unavailable. Your place will be released and a reserve may be invited.</p>
      <form method="POST" action="{{ route('team-selection.invitations.decline', $invitation) }}">@csrf
        <label for="decline-reason" class="form-label">Reason (optional)</label>
        <textarea id="decline-reason" name="reason" maxlength="1000" rows="3" class="form-control mb-3">{{ old('reason') }}</textarea>
        @error('reason')<div class="text-danger mb-3">{{ $message }}</div>@enderror
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger">Confirm decline</button><a class="btn btn-outline-secondary" href="{{ route('events.show', $invitation->event_id) }}">Keep invitation</a></div>
      </form>
    @else
      <h4>Invitation unavailable</h4><p class="mb-0">This invitation is {{ str_replace('_', ' ', $invitation->status) }}.</p>
    @endif
  </div></div>
</div></div></div>
@endsection
