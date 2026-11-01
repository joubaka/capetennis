<section class="card" data-rating-preview aria-labelledby="rating-preview-title">
  <div class="card-body">
    <span class="badge bg-label-primary mb-2">Player Plus preview</span>
    <h2 class="h4" id="rating-preview-title">See the story behind {{ $ratingPlayer->full_name }}'s progress</h2>
    <p>Unlock their saved Cape Tennis Rating (CTR), follow changes over 12 months and explore the singles results behind the estimate.</p>
    <div class="border rounded p-3 mb-3 bg-light">
      <p class="small fw-semibold mb-3">Illustration only · not this player's rating or results</p>
      <div class="row g-3">
        <div class="col-12 col-sm-4"><span class="small text-muted">Example CTR</span><p class="h3 mb-0">54.2</p></div>
        <div class="col-12 col-sm-4"><span class="small text-muted">Example progress</span><p class="mb-0">48.6 → 51.3 → 54.2</p><small>Track changes over time</small></div>
        <div class="col-12 col-sm-4"><span class="small text-muted">Behind the rating</span><p class="mb-0">Match results, opponents and confidence</p></div>
      </div>
    </div>
    <x-player-plus-benefits class="mb-3" />
    <a class="btn btn-primary" style="white-space:normal" href="{{ route('player-plus.player', $ratingPlayer) }}">Activate Player Plus for {{ $ratingPlayer->full_name }}</a>
    <p class="small text-muted mt-2 mb-0">Review availability and membership details before checkout. Eligible results are needed for a rating; purchasing Plus does not guarantee one.</p>
  </div>
</section>
