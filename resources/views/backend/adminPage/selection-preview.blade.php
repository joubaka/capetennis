@extends('layouts.backend')
@section('title', 'Preview selections - '.$event->name)
@section('content')
<style>.preview-review-above{background:#fff5ce!important}.preview-review-below{background:#eee7fa!important}.preview-note-button{background:#ff9f43;color:#211700;border:0}.preview-selected-team{border:3px solid #15966c!important}.preview-selected-team>.card-header{background:#e0f6ed;color:#0b6247}</style>
<div class="container-xxl py-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><h3>Preview selections</h3><p class="mb-0">{{ $event->name }} · Private selection audit</p></div><div class="d-flex flex-wrap gap-2">@can('event.manage', $event)<a class="btn btn-primary" href="{{ route('backend.selection-preview-share.index', ['event' => $event, 'age' => $age]) }}">Share preview</a><a class="btn btn-outline-primary" href="{{ route('backend.selection-region-mail.compose', $event) }}">Email regions</a>@if(!$finalization)<button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#preview-team-setup" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="preview-team-setup">Setup</button>@endif<a class="btn btn-outline-primary" href="{{ route('admin.events.teams', ['event' => $event, 'roster_tab' => 'result-rank', 'roster_result_category' => $activeGroupKey]) }}">View calculated results</a>@endcan</div></div>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @can('event.manage', $event)
  @if(!$finalization)
  <div @class(['collapse', 'show' => $errors->any()]) id="preview-team-setup"><form class="card card-body mb-3" method="POST" action="{{ route('backend.team-selection-size.store', $event) }}">
    @csrf @method('PUT')
    @if($age !== null)<input type="hidden" name="age" value="{{ $age }}"><input type="hidden" name="gender" value="{{ $gender }}">@endif
    <div class="row g-3"><label class="col-12 col-sm-4">Players per team<input class="form-control" type="number" name="team_size" min="1" max="12" value="{{ old('team_size', $teamSize) }}" required></label><label class="col-12 col-sm-4">Reserve places<input class="form-control" type="number" name="reserve_count" min="0" max="11" value="{{ old('reserve_count', $reserveCount) }}" required></label></div>
    <p class="mt-2 mb-2">The first {{ $teamSize }} selected players are the main team; the next {{ $reserveCount }} are reserves. Main and reserve places can total up to 12. This setting applies to every age group in this event and keeps all existing selections.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div><button class="btn btn-primary" type="submit">Save event setup</button></div>
  </form></div>
  @endif
  @endcan
  @if($finalization)
  @can('event.manage', $event)
  <button type="button" class="btn btn-outline-warning mb-3" data-bs-toggle="collapse" data-bs-target="#unlock-saved-teams" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="unlock-saved-teams">Unlock all saved teams</button>
  <div @class(['collapse', 'show' => $errors->any()]) id="unlock-saved-teams"><form method="POST" action="{{ route('backend.selection-finalization.reopen', $event) }}" class="card card-body mb-3">
    @csrf
    <input type="hidden" name="finalization_id" value="{{ $finalization->id }}">
    @if($age)<input type="hidden" name="age" value="{{ $age }}"><input type="hidden" name="gender" value="{{ $gender }}">@endif
    <p>Reopen all {{ count($finalization->groups) }} saved teams for editing. Current selections and reserve places are kept, and the finalized snapshot remains in history.</p>
    <label class="form-label">Reason for unlocking<textarea name="reason" class="form-control" rows="2" maxlength="1000" required>{{ old('reason') }}</textarea></label>
    <label class="mb-3"><input type="checkbox" name="confirm_unlock" value="1" required> I want to unlock all saved teams.</label>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div><button type="submit" class="btn btn-warning">Confirm unlock all teams</button></div>
  </form></div>
  @endcan
  <div class="alert alert-success" role="status"><strong>Finalized ranking · Private</strong><p class="mb-0">All {{ count($finalization->groups) }} saved teams were finalized by {{ $finalization->actor->name }} on {{ $finalization->finalized_at->format('d M Y H:i') }}. Draft order and selections are locked. Main places: {{ $teamSize }}; reserves: {{ $reserveCount }}. Discussion remains available. Publication and use in a next event require their own reviewed workflow.</p></div>
@else
  @can('event.manage', $event)
    @if($draftVersions)
      <form method="POST" action="{{ route('backend.selection-finalization.store', $event) }}" class="card card-body mb-3" data-finalize-teams data-confirm="Finalize all {{ count($draftVersions) }} saved teams for this event? This freezes the reviewed ranking, selections and reserve places. It does not publish or send invitations.">
        @csrf
        <input type="hidden" name="team_size" value="{{ $teamSize }}"><input type="hidden" name="reserve_count" value="{{ $reserveCount }}">
        @if($age)<input type="hidden" name="age" value="{{ $age }}"><input type="hidden" name="gender" value="{{ $gender }}">@endif
        <details class="mb-2"><summary>{{ count($draftVersions) }} saved teams</summary><ul>@foreach($draftVersions as $key => $version)<li>u/{{ str_replace('-', ' ', $key) }} · Draft version {{ $version }}<input type="hidden" name="versions[{{ $key }}]" value="{{ $version }}"></li>@endforeach</ul></details>
        <p>Finalize all saved age/gender groups together. This freezes the full ranking, selected players, notes and main/reserve places. After unlocking and making changes, press this button again to finalize. Empty teams or selections exceeding the configured places must be corrected first.</p><div><button class="btn btn-success" type="submit">Finalize all saved teams</button></div>
      </form>
    @endif
  @endcan
@endif
  <p>The selected team shows the saved selection order and reserve places. The source-team cards show current rosters for comparison. Green names match players in the saved selection. Roster changes can explain differences between the two lists.</p>
  <p>Player notes and discussion are available to managers and people with shared preview access. Discussion leaves the saved selection order intact.</p>
  <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Choose age group and gender">
    @foreach($navigationGroups as $option)<a @class(['btn', 'btn-primary' => $activeGroupKey === $option['key'], 'btn-outline-primary' => $activeGroupKey !== $option['key']]) href="{{ route('backend.selection-preview.show', ['event' => $event, 'age' => $option['age'], 'gender' => $option['gender']]) }}" @if($activeGroupKey === $option['key']) aria-current="page" @endif>u/{{ $option['age'] }} {{ ucfirst($option['gender']) }}</a>@endforeach
  </nav>
  @forelse($groups as $group)
    <section class="mb-4" aria-label="{{ $group['name'] }}"><h4>{{ $group['name'] }}</h4>
      @if($group['draft'])
        <p>{{ $finalization ? 'Locked ranking · Saved from draft version ' : 'Latest saved draft · Version ' }}{{ $group['draft']->version }} · {{ $group['draft']->updated_at?->format('d M Y H:i') }}</p>
      @else
        <p>{{ $finalization ? 'No locked ranking was saved for this group.' : 'No saved selection ranking is available for this group.' }}</p>
      @endif
      @if($group['over_capacity']->isNotEmpty())<div class="alert alert-warning" role="status">This draft exceeds the configured {{ $teamSize }} main and {{ $reserveCount }} reserve places. Additional selections were kept: {{ $group['over_capacity']->pluck('name')->implode(', ') }}. Review them in Result Ranks.</div>@endif
      @if($group['missing']->isNotEmpty())<div class="alert alert-warning">Saved selections no longer found in these current roster positions: {{ $group['missing']->pluck('name')->implode(', ') }}. They remain visible in the saved selected team; review roster or identity changes in Result Ranks.</div>@endif
      <div class="d-flex gap-3 overflow-auto pb-3" tabindex="0" aria-label="{{ $group['name'] }} source teams and selected team">
        <article class="card preview-selected-team flex-shrink-0" style="width: 280px"><div class="card-header"><h5 class="mb-1">Selected team</h5><span>{{ $group['selected']->count() }} selected · {{ $finalization ? 'Locked selection order' : 'Saved draft order' }}</span></div><ol class="list-group list-group-flush" style="list-style: none" aria-label="{{ $group['name'] }} selected team">
          @forelse($group['selected'] as $player)<li @class(['list-group-item d-flex gap-2', 'preview-review-above' => ($player['review']['kind'] ?? null) === 'above', 'preview-review-below' => ($player['review']['kind'] ?? null) === 'below']) @if($player['review']) title="{{ implode(' ', $player['review']['messages']) }}" @endif><span class="text-muted">{{ $loop->iteration }}.</span><div><strong class="text-success">{{ $player['name'] }}</strong> <span class="text-muted">({{ $player['origin_label'] }})</span>@if($player['reserve']) <span class="badge bg-danger">Reserve</span>@endif @if($player['note']) @include('backend.adminPage._partials.selection-note-button', ['playerKey' => (string) $player['id'], 'playerName' => $player['name'], 'groupKey' => $group['key']]) @endif</div></li>@empty<li class="list-group-item text-muted">No saved selections.</li>@endforelse
        </ol></article>
        @foreach($group['teams'] as $team)
          <article class="card flex-shrink-0" style="width: 280px"><div class="card-header"><h5 class="mb-1">{{ $team['name'] }}</h5><span>{{ $team['region'] }}</span></div><ol class="list-group list-group-flush" style="list-style: none" aria-label="{{ $team['name'] }} roster">
            @foreach($team['rows'] as $row)<li @class(['list-group-item d-flex gap-2', 'preview-review-above' => $row['players']->contains(fn ($player) => ($player['review']['kind'] ?? null) === 'above'), 'preview-review-below' => $row['players']->contains(fn ($player) => ($player['review']['kind'] ?? null) === 'below')])><span class="text-muted">{{ $row['rank'] }}.</span><div class="flex-grow-1">
              @forelse($row['players'] as $player)<div><strong @if($player['review']) title="{{ implode(' ', $player['review']['messages']) }}" @endif @if($player['selected']) class="text-success" aria-label="{{ $player['name'] }} selected" @endif>{{ $player['name'] }}</strong>@if($player['reserve']) <span class="badge bg-danger">Reserve</span>@endif @if($player['note']) @include('backend.adminPage._partials.selection-note-button', ['playerKey' => $player['key'], 'playerName' => $player['name'], 'groupKey' => $group['key']]) @endif<span class="text-muted d-block">{{ $player['team'] }}</span></div>@empty<span class="text-muted">—</span>@endforelse
            </div></li>@endforeach
          </ol></article>
        @endforeach
      </div>
      @can('event.manage', $event)
        @if($group['extended_selection']->isNotEmpty())
          <details class="card p-3 mt-2">
            <summary class="fw-semibold">View 1–20 selection list</summary>
            <p class="mt-2 mb-2">The locked main team and reserves keep their saved order. Additional candidates follow the calculated order captured at selection time, up to 20 players.</p>
            <div class="table-responsive">
              <table class="table table-sm mb-0" aria-label="{{ $group['name'] }} 1–20 selection list">
                <thead><tr><th scope="col">Place</th><th scope="col">Player</th><th scope="col">Selection status</th><th scope="col">Saved calculated position</th></tr></thead>
                <tbody>
                  @foreach($group['extended_selection'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><th scope="row">{{ $row['name'] }}</th><td>{{ $row['list_status'] }}</td><td>{{ $row['position'] ?? '—' }}</td></tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </details>
        @endif
        @if($group['draft']?->snapshot)
          <details class="card p-3 mt-2">
            <summary class="fw-semibold">Full saved ranking</summary>
            <p class="mt-2 mb-2">Saved order is the reviewed order recorded for this group. Calculated positions and points come from the tournament results and settings at the time the ranking was saved. View calculated results to see the calculation using current results and settings.</p>
            <div class="table-responsive">
              <table class="table table-sm mb-0" aria-label="{{ $group['name'] }} full saved ranking">
                <thead><tr><th scope="col">Saved order</th><th scope="col">Player</th><th scope="col">Calculated position</th><th scope="col">Points</th></tr></thead>
                <tbody>
                  @foreach(collect($group['draft']->snapshot)->sortBy(fn ($row) => $row['draft_position'] ?? $row['position'] ?? PHP_INT_MAX) as $row)
                    <tr><td>{{ $row['draft_position'] ?? $row['position'] ?? '—' }}</td><th scope="row">{{ $row['name'] }}</th><td>{{ $row['position'] ?? '—' }}</td><td>{{ $row['points'] ?? '—' }}</td></tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </details>
        @endif
      @endcan
    </section>
  @empty<p>No saved selection or source-team rosters are available for this group.</p>@endforelse
</div>
<div class="modal fade" id="selection-note-modal" tabindex="-1" aria-labelledby="selection-note-title" data-note-url="{{ route('backend.selection-preview-comments.show', $event) }}"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="selection-note-title" class="modal-title">Player note</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><h6>Saved draft note</h6><p data-preview-original-note style="white-space: pre-wrap"></p><h6>Discussion</h6><div data-preview-discussion></div><button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-preview-more hidden>Earlier comments</button><form data-preview-comment-form class="mt-3"><label class="form-label w-100">Add your comment<textarea class="form-control" name="body" maxlength="2000" rows="3" required></textarea></label><button class="btn btn-primary" type="submit">Add comment</button></form><p data-preview-note-status class="mt-2" role="status" aria-live="polite"></p></div></div></div></div>
<script src="{{ asset('js/selection-preview-discussion.js') }}?v={{ filemtime(public_path('js/selection-preview-discussion.js')) }}" defer></script>
@endsection
