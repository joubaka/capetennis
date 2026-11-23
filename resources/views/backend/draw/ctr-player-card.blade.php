<li class="ctr-player-card" data-ctr-player data-entry-id="{{ $row['entry_id'] }}" data-category-id="{{ $row['category_id'] }}" data-component="{{ $row['component'] ?? '' }}" data-score="{{ $row['score'] ?? '' }}" data-order="{{ $row['order'] ?? $row['entry_id'] }}" draggable="true">
  <div class="ctr-player-title"><span class="ctr-drag-handle" aria-hidden="true">⠿</span><strong>{{ $row['name'] }}</strong></div>
  <div class="ctr-player-rating">
    @if($row['score'] !== null)
      <span class="ctr-score">CTR {{ number_format($row['score'], 1) }}</span><span>{{ $row['confidence'] }}</span>
      @if($row['historical_connection'])<span>Historical connection</span>@endif
    @else
      <span class="ctr-unrated">{{ $row['rating_label'] ?? 'CTR unavailable' }}</span>
    @endif
    @if($row['band'] ?? null)<span>{{ $row['band'] }}</span>@endif
  </div>
  @if($row['issue'])<p class="ctr-player-issue">{{ $row['issue'] }}</p>@endif
  <div class="ctr-player-move">
    <label class="visually-hidden" for="ctr-entry-{{ $row['entry_id'] }}">Move {{ $row['name'] }} to group</label>
    <select id="ctr-entry-{{ $row['entry_id'] }}" name="placements[{{ $row['entry_id'] }}]" class="form-select form-select-sm" data-placement data-saved-group="{{ $row['group'] }}">
      @foreach($groupNumbers as $groupNumber)
        <option value="{{ $groupNumber }}" @selected($groupNumber === $displayGroup)>{{ $groupNumber ? 'Group '.$groupNumber : 'Needs placement' }}</option>
      @endforeach
    </select>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-player aria-label="Move {{ $row['name'] }} to selected group">Move</button>
  </div>
</li>
