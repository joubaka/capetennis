@extends('layouts.backend')
@section('title', 'Interprovincial Trials nominations and invitations')
@section('content')
<div class="container-xl">
  @include('backend.event.partials.header', ['event' => $event])

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Nominations &amp; invitations</h2><p class="text-muted mb-0">Nominate existing Cape Tennis players per category, then prepare an exact invitation snapshot.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.events.overview', $event) }}">Back to event overview</a>
  </div>
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">1. Find an existing player</h5></div><div class="card-body">
    <form method="GET" action="{{ route('backend.interprovincial-trials.invitations.index', $event) }}" class="row g-2">
      <div class="col-md-9"><label class="form-label" for="player-search">Player name or surname</label><input class="form-control" id="player-search" name="player_search" value="{{ $search }}" minlength="2" required></div>
      <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">Search players</button></div>
    </form>
    @if($search !== '' && mb_strlen($search) < 2)<p class="text-danger mt-2 mb-0">Enter at least two characters.</p>@endif
    @if(mb_strlen($search) >= 2)
      <div class="table-responsive mt-3"><table class="table align-middle mb-0"><thead><tr><th>Player</th><th>Profile reference</th><th>Nominate in category</th></tr></thead><tbody>
      @forelse($players as $player)
        <tr><td>{{ $player->name }} {{ $player->surname }}</td><td>Player #{{ $player->id }}</td><td><div class="d-flex flex-wrap gap-2">
          @foreach($event->categoryEvents as $categoryEvent)
            @php($alreadyNominated = $categoryEvent->nominations->contains('player_id', $player->id))
            <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.store', [$event, $categoryEvent]) }}">@csrf<input type="hidden" name="player_id" value="{{ $player->id }}"><button class="btn btn-sm {{ $alreadyNominated ? 'btn-secondary' : 'btn-outline-primary' }}" {{ $alreadyNominated ? 'disabled' : '' }}>{{ $categoryEvent->category?->name ?? 'Category' }}{{ $alreadyNominated ? ' — nominated' : '' }}</button></form>
          @endforeach
        </div></td></tr>
      @empty<tr><td colspan="3" class="text-muted text-center py-4">No existing players matched this search.</td></tr>@endforelse
      </tbody></table></div>
    @endif
  </div></div>

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">2. Review nominations by category</h5></div><div class="card-body"><div class="row g-3">
    @forelse($event->categoryEvents as $categoryEvent)
      <div class="col-lg-6"><div class="border rounded p-3 h-100"><h6>{{ $categoryEvent->category?->name ?? 'Category' }} <span class="badge bg-label-primary">{{ $categoryEvent->nominations->count() }}</span></h6><div class="list-group list-group-flush">
      @forelse($categoryEvent->nominations as $nomination)
        <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2"><span>{{ $nomination->player?->name }} {{ $nomination->player?->surname }}</span><form method="POST" action="{{ route('backend.interprovincial-trials.nominations.destroy', [$event, $categoryEvent, $nomination]) }}" onsubmit="return confirm('Remove this nomination?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></div>
      @empty<div class="text-muted">No players nominated yet.</div>@endforelse
      </div></div></div>
    @empty<div class="col-12"><div class="alert alert-warning mb-0">Add event categories before nominating players.</div></div>@endforelse
  </div></div></div>

  <div class="card"><div class="card-header"><h5 class="mb-0">3. Prepare and review invitations</h5></div><div class="card-body"><form method="POST" action="{{ route('backend.interprovincial-trials.invitations.prepare', $event) }}">@csrf<button class="btn btn-primary">Prepare invitations from current nominations</button></form></div></div>

  @if($batch)
  <div class="card mt-4"><div class="card-header"><strong>Invitation batch #{{ $batch->id }}</strong> <span class="badge bg-label-info">{{ ucfirst($batch->status) }}</span></div><div class="table-responsive"><table class="table"><thead><tr><th>Player</th><th>Category</th><th>Recipient</th><th>Readiness</th></tr></thead><tbody>
  @foreach($batch->invitations as $invitation)<tr><td>{{ $invitation->player->name }} {{ $invitation->player->surname }}</td><td>{{ $invitation->categoryEvent->category->name }}</td><td>{{ $invitation->recipient_email ?: 'No linked account email' }}</td><td>{{ $invitation->recipient_email ? 'Ready' : 'Blocked' }} @if($invitation->status === 'failed')<form class="d-inline" method="POST" action="{{ route('backend.interprovincial-trials.invitations.retry', [$event,$batch,$invitation]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry email</button></form>@endif</td></tr>@endforeach
  </tbody></table></div><div class="card-body d-flex gap-2">
  @if($batch->status === 'draft')<form method="POST" action="{{ route('backend.interprovincial-trials.batches.review', [$event,$batch]) }}">@csrf<input type="hidden" name="snapshot_hash" value="{{ $batch->snapshot_hash }}"><button class="btn btn-success">I reviewed these exact recipients</button></form>@endif
  @if($batch->status === 'reviewed')<form method="POST" action="{{ route('backend.interprovincial-trials.batches.send', [$event,$batch]) }}">@csrf<button class="btn btn-primary">Queue invitations</button></form>@endif
  </div></div>
  @endif
</div>
@endsection
