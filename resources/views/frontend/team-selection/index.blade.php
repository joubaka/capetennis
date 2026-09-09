@extends('layouts/contentNavbarLayout')

@section('title', 'Team invitations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-1">Team invitations</h4><p class="text-muted mb-4">Your Platteland selection and registration status.</p>
  @forelse($invitations as $invitation)
    <div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h5 class="mb-1">{{ $invitation->selectionImport?->event?->name }}</h5><div class="text-muted">{{ $invitation->region?->region_name }} · {{ $invitation->team?->name }}</div></div><div class="d-flex align-items-center gap-2"><span class="badge bg-label-{{ $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED ? 'success' : 'primary' }}">{{ str_replace('_',' ',ucfirst($invitation->status)) }}</span><a class="btn btn-sm btn-primary" href="{{ route('team-selection.invitations.show', $invitation) }}">Open invitation</a></div></div></div>
  @empty
    <div class="alert alert-info">There are no active team invitations linked to your player profiles.</div>
  @endforelse
</div>
@endsection
