<section class="card plus-preview" data-rating-preview aria-labelledby="rating-preview-title">
  <style>
    .plus-preview { overflow:hidden;border:1px solid #dbe7e4; }
    .plus-preview .plus-hero { display:grid;grid-template-columns:1.1fr 1fr;gap:2rem;padding:clamp(1.25rem,3vw,2.5rem);background:linear-gradient(120deg,#f5f9f6,#e9f4ef);color:#183c45;align-items:center; }
    .plus-preview .plus-eyebrow { display:inline-block;color:#14675c;font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:1rem; }
    .plus-preview h2 { color:#183c45;font-size:clamp(1.65rem,2.6vw,2.6rem);line-height:1.15;max-width:20ch;margin-bottom:1rem; }
    .plus-preview .plus-intro { color:#536873;line-height:1.65;max-width:49ch;margin-bottom:1.25rem; }
    .plus-preview .plus-demo { background:#fff;color:#183c45;border:1px solid #dbe7e4;border-radius:18px;padding:1.25rem;box-shadow:0 8px 24px #183c4508;min-width:0; }
    .plus-preview .plus-demo-label { font-size:.72rem;color:#536c73;margin:0 0 1rem; }
    .plus-preview .plus-demo-top { display:flex;justify-content:space-between;gap:1rem;align-items:center; }
    .plus-preview .plus-score { font-size:2.75rem;line-height:1.1;font-weight:750;letter-spacing:-.04em; }
    .plus-preview .plus-change { background:#ecf6de;color:#37541e;border-radius:20px;padding:.4rem .7rem;font-size:.75rem;font-weight:600; }
    .plus-preview .plus-chart { width:100%;height:auto;display:block;margin:.5rem 0; }
    .plus-preview .plus-chart-labels { display:flex;justify-content:space-between;font-size:.75rem;color:#536c73; }
    .plus-preview .plus-body { padding:clamp(1.25rem,3vw,2.5rem); }
    .plus-preview .plus-features { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.5rem;margin-bottom:1.75rem; }
    .plus-preview .plus-feature-number { color:#16786b;font-size:.75rem;font-weight:700;letter-spacing:.1em;display:block;margin-bottom:.6rem; }
    .plus-preview h3 { font-size:1.05rem;color:#183c45;margin-bottom:.5rem; }
    .plus-preview .plus-features p { font-size:.875rem;line-height:1.6;margin:0;color:#536873; }
    .plus-preview .plus-offer { background:#f3f7f5;border:1px solid #dde8e2;border-radius:14px;padding:1.25rem;display:flex;justify-content:space-between;align-items:center;gap:1.5rem; }
    .plus-preview .plus-price { color:#183c45;font-size:1.8rem;font-weight:700;line-height:1.3; }
    .plus-preview .plus-price small { font-size:.85rem;font-weight:400;color:#536873; }
    .plus-preview .plus-cta { background:#146f63;color:#fff;border:1px solid #146f63;min-height:48px;padding:.8rem 1.25rem;font-weight:700;white-space:normal; }
    .plus-preview .plus-cta:hover,.plus-preview .plus-cta:focus-visible { background:#10594f;color:#fff;border-color:#10594f; }
    .plus-preview .plus-terms { margin-top:1rem;color:#536873;font-size:.8rem;line-height:1.6; }
    .plus-preview summary { cursor:pointer;min-height:44px;display:list-item;padding:.65rem 0;color:#245e59;font-weight:600; }
    @media(max-width:767.98px) { .plus-preview .plus-hero { grid-template-columns:1fr;gap:1.5rem; }.plus-preview .plus-features { grid-template-columns:1fr;gap:1.25rem; }.plus-preview .plus-offer { align-items:stretch;flex-direction:column;gap:1rem; }.plus-preview .plus-cta { width:100%; } }
  </style>
  <div class="plus-hero">
    <div>
      <span class="plus-eyebrow">Cape Tennis · Player Plus</span>
      <h2 id="rating-preview-title">Understand their game.<br>Follow their progress.</h2>
      <p class="plus-intro">How strong is {{ $ratingPlayer->full_name }}'s singles game? How is it changing? Bring their rating, progress and the results behind it into one clear view.</p>
      <a class="btn plus-cta" href="{{ route('player-plus.player', $ratingPlayer) }}">Explore Player Plus <span aria-hidden="true">→</span></a>
      <p class="small mt-2 mb-0">Essential tournament information remains free.</p>
    </div>
    <div class="plus-demo">
      <p class="plus-demo-label">Illustration only · not this player's rating or results</p>
      <div class="plus-demo-top">
        <div><span class="small">Example CTR</span><div class="plus-score">54.2</div></div>
        <span class="plus-change">Example progress +5.6</span>
      </div>
      <svg class="plus-chart" viewBox="0 0 420 130" role="img" aria-label="Illustrative rating trend from 48.6 to 51.3 to 54.2. Actual ratings can rise or fall.">
        <path d="M12 30H408M12 70H408M12 110H408" fill="none" stroke="#e3ece8" />
        <path d="M12 105L80 91L145 97L210 64L275 70L340 43L408 20L408 120H12Z" fill="#eaf5e9" />
        <path d="M12 105L80 91L145 97L210 64L275 70L340 43L408 20" fill="none" stroke="#187b6b" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
        <g fill="#187b6b" stroke="#fff" stroke-width="3"><circle cx="12" cy="105" r="5"/><circle cx="210" cy="64" r="5"/><circle cx="408" cy="20" r="6"/></g>
      </svg>
      <div class="plus-chart-labels"><span>48.6 · Earlier</span><span>51.3</span><span>54.2 · Latest</span></div>
      <p class="small mt-3 mb-0">12 months of perspective. Every change has context.</p>
    </div>
  </div>
  <div class="plus-body">
    <div class="plus-features">
      <div><span class="plus-feature-number">01 / UNDERSTAND</span><h3>More than a win or a loss</h3><p>See their saved Cape Tennis Rating (CTR), an estimate of singles playing strength based on eligible results.</p></div>
      <div><span class="plus-feature-number">02 / FOLLOW</span><h3>See the longer game</h3><p>Explore 12 months of rating history. Follow changes over time instead of judging progress on one tournament.</p></div>
      <div><span class="plus-feature-number">03 / EXPLORE</span><h3>Know what’s behind the number</h3><p>Explore recorded singles results, opponents and confidence. See CTR comparisons and Top 10 lists among eligible published series participants.</p></div>
    </div>
    <div class="plus-offer">
      <div><div class="fw-semibold mb-1">A year of Player Plus for {{ $ratingPlayer->full_name }}</div><div class="plus-price">R{{ number_format(\App\Services\Performance\PlayerPlusService::priceCents() / 100, \App\Services\Performance\PlayerPlusService::priceCents() % 100 === 0 ? 0 : 2) }} <small>per player / year</small></div><p class="small mt-1 mb-0">One player. Shared with their linked parents or guardians.<br>Renew manually; no automatic renewal.</p></div>
      <a class="btn plus-cta" href="{{ route('player-plus.player', $ratingPlayer) }}">Explore Player Plus <span aria-hidden="true">→</span></a>
    </div>
    <div class="plus-terms">
      <p class="mb-1">Eligible results are needed for a rating; purchasing Plus does not guarantee one. Ratings can rise or fall and may be provisional or unavailable.</p>
      <details><summary>How membership works</summary><p>Optional membership covers 12 months for one named player. Review availability and membership details before checkout. Membership does not change a rating, ranking, selection or tournament entry.</p><p>Paying for a player does not link them to your account or give you access to their private information.</p><p class="mb-0">Essential tournament information remains free. Draw alerts are planned and are not included yet.</p></details>
    </div>
  </div>
</section>
