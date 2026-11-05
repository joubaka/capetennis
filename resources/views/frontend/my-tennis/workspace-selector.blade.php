@if($workspace['players']->isNotEmpty())
@once
<script defer src="{{ asset('assets/js/my-tennis-player-switch.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-player-switch.js')) }}"></script>
@endonce
<form method="get" action="{{ route('my.tennis') }}" class="ct-workspace-player-selector" data-workspace-player-selector>
  <label for="workspace-player" class="small d-block mb-1">{{ ($compactSelector ?? false) ? 'Switch player' : 'View player workspace' }}</label>
  <div class="d-flex gap-2">
    <select id="workspace-player" name="player" class="form-select" required>
      <option value="" disabled @selected(!$workspace['related'])>Choose a linked player</option>
      @foreach($workspace['players'] as $workspaceOption)<option value="{{ $workspaceOption->id }}" @selected($workspace['related'] && $workspace['player']->id === $workspaceOption->id)>{{ $workspaceOption->full_name }}</option>@endforeach
    </select>
    <button type="submit" class="btn btn-outline-light">{{ ($compactSelector ?? false) ? 'Open' : 'View' }}</button>
  </div>
  @unless($compactSelector ?? false)<small>Open the selected player’s dashboard.</small>@endunless
</form>
@endif
