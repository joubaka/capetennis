@extends('layouts/layoutMaster')
@section('title', 'Interprovincial Trials invitations')
@section('content')
<h4>{{ $event->name }} — invitations</h4>
@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<div class="card"><div class="card-body">
  <p>Nominate existing Cape Tennis player profiles in each event category, then prepare an exact invitation snapshot.</p>
  <a class="btn btn-outline-secondary me-2" href="{{ route('eventAdmin.show', $event) }}">Load or edit nominated players</a>
  <form method="POST" action="{{ route('backend.interprovincial-trials.invitations.prepare', $event) }}">@csrf<button class="btn btn-primary">Prepare invitations</button></form>
</div></div>
@if($batch)
<div class="card mt-3"><div class="card-header"><strong>Batch #{{ $batch->id }}</strong> <span class="badge bg-label-info">{{ ucfirst($batch->status) }}</span></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Player</th><th>Category</th><th>Recipient</th><th>Readiness</th></tr></thead><tbody>
@foreach($batch->invitations as $invitation)<tr><td>{{ $invitation->player->name }} {{ $invitation->player->surname }}</td><td>{{ $invitation->categoryEvent->category->name }}</td><td>{{ $invitation->recipient_email ?: 'No linked account email' }}</td><td>{{ $invitation->recipient_email ? 'Ready' : 'Blocked' }} @if($invitation->status === 'failed')<form class="d-inline" method="POST" action="{{ route('backend.interprovincial-trials.invitations.retry', [$event,$batch,$invitation]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry email</button></form>@endif</td></tr>@endforeach
</tbody></table></div><div class="card-body d-flex gap-2">
@if($batch->status === 'draft')<form method="POST" action="{{ route('backend.interprovincial-trials.batches.review', [$event,$batch]) }}">@csrf<input type="hidden" name="snapshot_hash" value="{{ $batch->snapshot_hash }}"><button class="btn btn-success">I reviewed these exact recipients</button></form>@endif
@if($batch->status === 'reviewed')<form method="POST" action="{{ route('backend.interprovincial-trials.batches.send', [$event,$batch]) }}">@csrf<button class="btn btn-primary">Queue invitations</button></form>@endif
</div></div>
@endif
@endsection
