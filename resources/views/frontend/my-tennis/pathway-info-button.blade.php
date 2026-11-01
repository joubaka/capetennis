@if(isset($ratingPlayer))
  <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-pathway-info="{{ route('my.tennis.pathway.info', ['player' => $ratingPlayer->id, 'stage' => $infoStage]) }}" aria-expanded="false" aria-controls="pathway-info-{{ $ratingPlayer->id }}-{{ $infoStage }}">More info<span class="visually-hidden"> · {{ $infoLabel }}</span></button>
  <div id="pathway-info-{{ $ratingPlayer->id }}-{{ $infoStage }}" class="mt-3 small" data-pathway-info-content hidden role="region" aria-label="{{ $infoLabel }} details" aria-live="polite"></div>
@endif
