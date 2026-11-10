@php
  $move = $pathway['move'] ?? null;
  $moveOptions = $pathway['moveOptions'] ?? [];
  $ballLabels = \App\Services\Performance\BallRatingModel::LABELS;
  $canRecordMove = $moveOptions && auth()->user()?->hasRole('super-user');
@endphp
@if($move || $canRecordMove)
  <section class="pathway-next-step mt-3" id="pathway-ball-move" aria-labelledby="pathway-ball-move-heading">
    <h3 class="h6" id="pathway-ball-move-heading">Ball-level move</h3>
    @if(session('pathway_move_status'))<p class="small" role="status">{{ session('pathway_move_status') }}</p>@endif
    @if($move)
      <p class="mb-2"><strong>{{ $ballLabels[$move['from']] }} → {{ $ballLabels[$move['to']] }}</strong> · Started {{ $move['started_on'] }}</p>
      <p class="small mb-2">Suggested starting group <strong>10–20</strong> for early matches. This is placement guidance.</p>
      <p class="small mb-2">
        {{ $ballLabels[$move['to']] }} CTR
        <strong>{{ $move['score'] === null ? 'Pending' : number_format($move['score'], 1) }}</strong>
        @if($move['local']) · Within-group only @endif
        @if($move['score'] === null) · Awaiting a saved rating based on eligible matches from the new level. @endif
      </p>
      <p class="small mb-2"><strong>{{ $move['count'] ?? 'Unavailable' }}</strong> eligible published singles matches in the saved rating window, from {{ $move['started_on'] }} inclusive.</p>
      @if($move['matches']->isNotEmpty())
        <h4 class="small fw-semibold">First recorded matches in this window</h4>
        <ol class="small ps-3 mb-2">
          @foreach($move['matches'] as $match)
            <li>{{ $match['date'] }} · {{ $match['event'] }} · {{ $match['won'] ? 'Win' : 'Loss' }}</li>
          @endforeach
        </ol>
      @endif
    @endif
    @if($canRecordMove)
      <form class="d-flex flex-wrap align-items-end gap-2" method="post" action="{{ route('my.tennis.pathway.move', $ratingPlayer) }}">
        @csrf
        <label class="small">Move to the next ball level
          <select class="form-select form-select-sm" name="move_from_stage" required>
            @foreach($moveOptions as $from => $to)
              <option value="{{ $from }}" @selected(old('move_from_stage') === $from)>{{ $ballLabels[$from] }} → {{ $ballLabels[$to] }}</option>
            @endforeach
          </select>
        </label>
        <label class="small">Start date
          <input class="form-control form-control-sm" type="date" name="move_started_on" value="{{ old('move_started_on', now('Africa/Johannesburg')->toDateString()) }}" max="{{ now('Africa/Johannesburg')->toDateString() }}" required>
        </label>
        <button class="btn btn-sm btn-primary" type="submit">Record move</button>
      </form>
    @endif
    @foreach(['move_from_stage', 'move_started_on'] as $field)
      @error($field)<p class="small text-danger mt-2 mb-0" role="alert">{{ $message }}</p>@enderror
    @endforeach
  </section>
@endif
