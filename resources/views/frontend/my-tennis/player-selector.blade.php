@if($players->isNotEmpty())
  <form method="get" action="{{ route('my.tennis') }}" class="ct-workspace-player-selector">
    @if($previewNonPlus ?? false)<input type="hidden" name="preview_non_plus" value="1">@endif
    <label for="my-tennis-player" class="small d-block mb-1">Player</label>
    <select id="my-tennis-player" name="player" class="form-select my-tennis-player-select">
      @if(($ratingPreviewPlayer ?? null) && !$selectedPlayer)<option value="{{ $ratingPreviewPlayer->id }}" selected>{{ $ratingPreviewPlayer->full_name }} · Admin preview</option>@endif
      @foreach($players as $playerOption)<option value="{{ $playerOption->id }}" @selected($selectedPlayer?->id === $playerOption->id)>{{ $playerOption->full_name }}</option>@endforeach
    </select>
  </form>
@endif
