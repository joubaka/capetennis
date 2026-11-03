<section class="ct-rating-snapshot" aria-label="Player Plus rating preview" data-locked-rating-snapshot>
  <h3><x-ctr-mark class="me-2" />Your rating snapshot</h3>
  <div aria-hidden="true" style="filter:blur(4px);pointer-events:none;user-select:none">
    <svg viewBox="0 0 280 65" style="width:100%;height:65px;display:block"><path d="M10 48L55 35L95 42L140 25L185 30L225 16L270 20" fill="none" stroke="#c4e4ff" stroke-width="3"/></svg>
  </div>
  <p>Illustration only · no private rating shown.</p>
  <a class="btn btn-sm mt-2" href="{{ ($ratingPlayer ?? null) ? route('my.tennis', ['player' => $ratingPlayer->id]).'#rating' : route('player-plus.index') }}" data-plus-preview-link>Explore Player Plus</a>
</section>
