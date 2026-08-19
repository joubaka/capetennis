@extends('layouts/layoutMaster')
@section('title', 'My Disciplinary Cases')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y"><h4>My disciplinary cases</h4><p class="text-muted">Private notices, responses, decisions and appeals for players linked to your account.</p><div class="card"><div class="list-group list-group-flush">@forelse($cases as $case)<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('disciplinary.my-cases.show',$case) }}"><div><strong>{{ $case->case_number }}</strong><div>{{ $case->player->full_name }} · {{ $case->event->name }}</div><small class="text-muted">{{ $case->incident_at->format('d M Y') }}</small></div><span class="badge bg-label-warning">{{ str($case->status)->replace('_',' ')->title() }}</span></a>@empty<div class="p-5 text-center text-muted">No disciplinary notices.</div>@endforelse</div><div class="card-footer">{{ $cases->links() }}</div></div></div>
@endsection
