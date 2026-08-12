@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-user-search me-2 text-primary"></i>Duplicate Player Profiles</h4>
      <p class="text-muted mb-0">Candidates match on trimmed, case-insensitive first name and surname. A match is not proof that they are the same person.</p>
    </div>
    <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">Back to Super Admin</a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="alert alert-warning">
    <strong>Review before merging.</strong> Compare date of birth, gender, player email and linked account emails. Only a profile with no competition, team, financial, ranking, agreement, or disciplinary usage can be removed here.
  </div>

  @forelse($candidateGroups as $group)
    <div class="card mb-4 {{ $group->can_merge ? 'border-warning' : 'border-danger' }}">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>{{ $group->name }}</strong>
        <span class="badge {{ $group->can_merge ? 'bg-label-warning' : 'bg-label-danger' }}">
          {{ $group->can_merge ? 'Reviewable' : 'Both/all profiles in use' }}
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>ID</th><th>Identity details</th><th>Linked accounts</th><th>Usage</th><th>Status</th></tr></thead>
          <tbody>
          @foreach($group->players as $item)
            <tr>
              <td>#{{ $item['player']->id }}</td>
              <td>
                <div>DOB: {{ $item['player']->dateOfBirth ?: 'Not set' }}</div>
                <div>Gender: {{ $item['player']->gender ?: 'Not set' }}</div>
                <div>Player email: {{ $item['player']->email ?: 'Not set' }}</div>
              </td>
              <td>
                @forelse($item['owners'] as $owner)
                  <div>{{ $owner['name'] }} <span class="text-muted">({{ $owner['email'] }}, user #{{ $owner['id'] }})</span></div>
                @empty
                  <span class="text-muted">No linked user account</span>
                @endforelse
              </td>
              <td>
                @forelse($item['usage'] as $table => $count)
                  <span class="badge bg-label-secondary me-1 mb-1">{{ str_replace('_', ' ', $table) }}: {{ $count }}</span>
                @empty
                  <span class="text-muted">No usage found</span>
                @endforelse
              </td>
              <td><span class="badge {{ $item['is_empty'] ? 'bg-success' : 'bg-danger' }}">{{ $item['is_empty'] ? 'Empty / removable' : 'In use' }}</span></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      @if($group->can_merge && $group->players->count() === 2)
        @php
          $recommendedKeep = $group->players->firstWhere('is_empty', false) ?? $group->players->first();
          $recommendedKeepId = $recommendedKeep['player']->id;
        @endphp
        <div class="card-body border-top">
          <form method="POST" action="{{ route('superadmin.player-duplicates.merge') }}" class="row g-3 align-items-end" onsubmit="return confirm('Merge these profiles? The empty profile will be deleted and its linked users moved to the kept profile.');">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Keep profile</label>
              <select name="keep_player_id" class="form-select" required>
                @foreach($group->players as $item)<option value="{{ $item['player']->id }}" @selected($item['player']->id === $recommendedKeepId)>#{{ $item['player']->id }} — {{ $item['emails']->join(', ') ?: 'no email' }} {{ $item['is_empty'] ? '(empty)' : '(in use)' }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Remove empty profile</label>
              <select name="remove_player_id" class="form-select" required>
                @foreach($group->players->where('is_empty', true)->reject(fn ($item) => $item['player']->id === $recommendedKeepId) as $item)<option value="{{ $item['player']->id }}">#{{ $item['player']->id }} — {{ $item['emails']->join(', ') ?: 'no email' }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Type MERGE</label>
              <input name="confirmation" class="form-control" required autocomplete="off">
            </div>
            <div class="col-md-2"><button class="btn btn-warning w-100">Approve merge</button></div>
          </form>
        </div>
      @elseif($group->players->count() > 2)
        <div class="card-body border-top text-muted">More than two profiles share this name. Review them individually; automatic merge is disabled for this group.</div>
      @endif
    </div>
  @empty
    <div class="card"><div class="card-body text-center py-5"><h5>No duplicate name candidates found</h5><p class="text-muted mb-0">The scan found no repeated first-name and surname combinations.</p></div></div>
  @endforelse

  {{ $candidateGroups->links() }}
</div>
@endsection
