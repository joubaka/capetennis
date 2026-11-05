@extends('layouts/layoutMaster')
@section('title', 'CTR Ratings · Cape Tennis Rating')
@section('page-style')
<style>
.ctr-intro{font-family:'Public Sans',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:1180px;margin:1.5rem auto 3rem;color:#172e45}
.ctr-intro *{box-sizing:border-box}
.ctr-intro h1,.ctr-intro h2,.ctr-intro h3{color:inherit}
.ctr-intro .ctr-eyebrow{font-size:.72rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin:0 0 1rem}
.ctr-intro-hero{display:grid;grid-template-columns:1.05fr 1fr;gap:2.5rem;align-items:center;background:#172e45;border-radius:24px;padding:clamp(1.5rem,4vw,3.25rem);color:#fff;overflow:hidden}
.ctr-intro .ctr-intro-hero h1{font-size:clamp(2.5rem,4.2vw,3.8rem);line-height:1.04;letter-spacing:-.055em;color:#fff!important;max-width:10ch;margin:0 0 1.25rem}
.ctr-intro .ctr-intro-hero h1{display:flex;align-items:flex-start;gap:.65rem;max-width:100%}
.ctr-intro-hero h1 .ctr-mark{margin-top:.15em;width:.85em!important;height:.85em!important}
.ctr-intro-hero h1 span{min-width:0;max-width:10ch}
.ctr-intro-hero .ctr-eyebrow{color:#b8d8f5}
.ctr-hero-description{font-size:1.05rem;line-height:1.75;color:#e0ebf5;max-width:34rem;margin-bottom:1.6rem}
.ctr-intro-actions{display:flex;flex-wrap:wrap;gap:.75rem}
.ctr-intro-actions a{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:48px;white-space:normal;line-height:1.4;padding:.8rem 1.15rem;border-radius:9px;font-weight:600}
.ctr-intro .ctr-primary{background:#004177;color:#ffffff;border:1px solid #004177}
.ctr-intro .ctr-primary:hover{background:#00345f;color:#ffffff}
.ctr-intro-hero .ctr-primary{background:#b8d8f5;border-color:#b8d8f5;color:#172e45}
.ctr-intro-hero .ctr-primary:hover{background:#d8eaff;border-color:#d8eaff;color:#172e45}
.ctr-intro .ctr-secondary{border:1px solid #89a9c9;color:#fff;background:transparent}
.ctr-intro .ctr-secondary:hover{background:#ffffff12;color:#fff}
.ctr-hero-note{font-size:.78rem;color:#c6d8ea;margin:1rem 0 0;line-height:1.6}
.ctr-illustration{position:relative;min-width:0;background:#f5f8fc;border-radius:18px;padding:1.25rem;color:#172e45;transform:rotate(2deg);box-shadow:0 20px 35px #172e4526}
.ctr-illustration-header{display:flex;justify-content:space-between;gap:.6rem;align-items:center;font-size:.78rem;font-weight:600;margin-bottom:1rem}
.ctr-illustration-label{font-size:.63rem;letter-spacing:.08em;text-transform:uppercase;background:#eaf1f8;padding:.35rem .5rem;border-radius:5px;color:#536873}
.ctr-court{width:100%;height:auto;display:block;border-radius:10px}
.ctr-illustration-footer{display:flex;gap:.75rem;align-items:center;margin-top:1rem;font-size:.8rem;line-height:1.5}
.ctr-illustration-footer i{background:#eaf1f8;color:#004177;border-radius:8px;padding:.6rem;font-size:1.2rem}
.ctr-illustration-footer strong{display:block}
.ctr-illustration-footer span{color:#536873;font-size:.72rem}
.ctr-section-intro{display:flex;justify-content:space-between;align-items:end;gap:1rem;margin:2.5rem 0 1.1rem}
.ctr-section-intro h2{font-size:1.7rem;letter-spacing:-.04em;margin:0}
.ctr-section-intro p{color:#536873;font-size:.9rem;margin:0;max-width:25rem}
.ctr-intro-features{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.ctr-intro-feature{border:1px solid #dce5ef;background:#fff;border-radius:14px;min-width:0;align-self:start;overflow:hidden}
.ctr-feature-summary{display:block;padding:1.5rem;cursor:pointer;list-style:none}
.ctr-feature-summary::-webkit-details-marker{display:none}
.ctr-feature-summary:hover{background:#f5f8fc}
.ctr-feature-summary:focus-visible{outline:3px solid #ff9f43;outline-offset:-4px;border-radius:13px}
.ctr-feature-toggle{align-items:center;color:#004177;display:flex;font-size:.78rem;font-weight:700;gap:.5rem;margin-top:1rem}
.ctr-feature-chevron{border-bottom:2px solid currentColor;border-right:2px solid currentColor;height:.45rem;margin-left:auto;transform:rotate(45deg);width:.45rem}
.ctr-feature-less{display:none}
.ctr-intro-feature[open]{border-color:#9db8d5;box-shadow:0 4px 16px #172e450a}
.ctr-intro-feature[open] .ctr-feature-chevron{transform:rotate(225deg)}
.ctr-intro-feature[open] .ctr-feature-more{display:none}
.ctr-intro-feature[open] .ctr-feature-less{display:inline}
.ctr-feature-details{border-top:1px solid #dce5ef;margin:0 1.5rem;padding:1rem 0 1.5rem}
.ctr-feature-details p+p{margin-top:.75rem}
.ctr-feature-icon{width:42px;height:42px;display:grid;place-items:center;background:#eaf1f8;border-radius:10px;color:#004177;font-size:1.3rem;margin-bottom:1.15rem}
.ctr-intro-feature h3{font-size:1.05rem;letter-spacing:-.02em;margin-bottom:.6rem}
.ctr-intro-feature p{font-size:.875rem;line-height:1.75;color:#536873;margin:0}
.ctr-offer{display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:center;border:1px solid #dce5ef;border-radius:20px;background:#eef3fa;padding:clamp(1.5rem,3vw,2.25rem);margin-top:1.5rem}
.ctr-offer h2{font-size:2rem;line-height:1.15;letter-spacing:-.045em;margin-bottom:.85rem}
.ctr-offer-copy{font-size:.9rem;line-height:1.8;color:#536873;margin:0}
.ctr-offer .ctr-eyebrow{color:#004177}
.ctr-offer-pricing{background:#fff;border:1px solid #dce5ef;border-radius:14px;padding:1.4rem}
.ctr-offer-pricing [data-player-plus-pricing]{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem 1rem!important}
.ctr-offer-pricing [data-player-plus-pricing]>div{display:flex;flex-direction:column;gap:.1rem;min-width:0}
.ctr-offer-pricing [data-player-plus-pricing]>p{grid-column:1/-1}
.ctr-offer-pricing [data-player-plus-coming-soon]{grid-column:1/-1;justify-self:start}
.ctr-offer-pricing [data-player-plus-pricing] strong{font-size:1.85rem;letter-spacing:-.04em;color:#172e45}
.ctr-offer-pricing [data-player-plus-pricing] .small{font-size:.8rem;line-height:1.7;color:#536873}
.ctr-offer-pricing [data-player-plus-pricing]>p{margin-top:.6rem!important}
.ctr-offer-pricing .ctr-intro-actions{margin-top:1.1rem}
.ctr-offer-pricing .ctr-primary{background:#004177;color:#fff;border-color:#004177;flex:1}
.ctr-offer-pricing .ctr-primary:hover{background:#00345f}
.ctr-offer-links{display:flex;flex-wrap:wrap;gap:.4rem 1.2rem;margin-top:.9rem;font-size:.8rem}
.ctr-offer-links a{color:#004177;min-height:44px;display:inline-flex;align-items:center}
.ctr-disclosures{display:flex;gap:2rem;justify-content:space-between;margin-top:1.3rem;padding:.2rem .3rem;font-size:.78rem;line-height:1.8;color:#536873}
.ctr-disclosures p{margin:0;max-width:40rem}
.ctr-disclosures a{color:#004177}
.ctr-admin{border-top:1px solid #dce5ef;margin-top:1.25rem;padding-top:1rem;font-size:.8rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap;color:#536873}
.ctr-admin p{margin:0}
.ctr-admin a{color:#004177;min-height:44px;display:inline-flex;align-items:center}
.ctr-intro a:focus-visible{outline:3px solid #ff9f43;outline-offset:4px}
@media(max-width:991.98px){.ctr-intro-hero{gap:1.5rem}
.ctr-illustration{transform:none}
.ctr-offer{gap:1.25rem}
.ctr-feature-summary{padding:1.2rem}
.ctr-feature-details{margin:0 1.2rem;padding-bottom:1.2rem}}@media(max-width:767.98px){.ctr-intro{margin:1rem auto 2rem}
.ctr-intro-hero,.ctr-offer{grid-template-columns:1fr}
.ctr-intro .ctr-intro-hero h1{max-width:12ch}
.ctr-illustration{max-width:430px;width:100%;justify-self:center}
.ctr-section-intro{display:block;margin-top:2rem}
.ctr-section-intro p{margin-top:.6rem}
.ctr-intro-features{grid-template-columns:1fr}
.ctr-feature-summary{display:grid;grid-template-columns:42px minmax(0,1fr);column-gap:1rem}
.ctr-feature-icon{grid-row:1/4;margin:0}
.ctr-feature-summary p,.ctr-feature-toggle{grid-column:2}
.ctr-intro-feature h3{margin:.15rem 0 .4rem}
.ctr-offer h2{font-size:1.7rem}
.ctr-disclosures{flex-direction:column;gap:.75rem}
.ctr-intro-actions a{flex:1 1 auto}
.ctr-offer-links{gap:.2rem 1rem}}@media(max-width:575.98px){.ctr-intro-hero{padding:1.25rem;gap:1.25rem;border-radius:18px}
.ctr-intro .ctr-intro-hero h1{font-size:clamp(1.85rem,5.5vw,2.1rem);line-height:1.1;max-width:100%;gap:.5rem;margin-bottom:.85rem}
.ctr-intro-hero h1 span{max-width:13ch}
.ctr-intro-hero .ctr-eyebrow{font-size:.65rem;letter-spacing:.1em;margin-bottom:.65rem}
.ctr-hero-description{font-size:.9rem;line-height:1.6;margin-bottom:1.1rem}
.ctr-hero-note{margin-top:.75rem}}@media(max-width:399.98px){.ctr-offer-pricing{padding:1rem}
.ctr-offer-pricing [data-player-plus-pricing]{gap:.5rem!important}
.ctr-offer-pricing [data-player-plus-pricing] strong{font-size:1.35rem}}@media(prefers-reduced-motion:no-preference){.ctr-intro-actions a{transition:background .15s}}
</style>
@endsection
@section('content')
<main class="ctr-intro" data-ctr-intro>
  @php($plusSalesOpen = \App\Services\Performance\PlayerPlusService::salesEnabled())
  <x-player-plus-stepper :current="1" />
  <section class="ctr-intro-hero" aria-labelledby="ctr-title">
    <div>
      <p class="ctr-eyebrow">Cape Tennis · Player Plus</p>
      <x-player-plus-coming-soon class="mb-3" />
      <h1 id="ctr-title"><x-ctr-mark size="heading" /><span>Discover CTR Ratings</span></h1>
      <p class="ctr-hero-description">What should I play next? How am I progressing? Player Plus helps primary-school players, beginners and their families understand playing strength and explore the journey from Red Ball through Orange, Green and Yellow Ball to TSA events.</p>
      <div class="ctr-intro-actions">
        <a class="btn ctr-primary" href="{{ $plusSalesOpen ? route('player-plus.index') : '#ctr-offer-title' }}">{{ $plusSalesOpen ? 'Explore Player Plus' : 'Discover Player Plus' }} <i class="ti ti-arrow-up-right" aria-hidden="true"></i></a>
        <a class="btn ctr-secondary" href="{{ route('my.tennis', ['tab' => 'rating']) }}">Preview in My Tennis</a>
      </div>
      <p class="ctr-hero-note">For one player and their linked parents or guardians.<br>Essential tournament information stays free.</p>
    </div>
    <div class="ctr-illustration" role="img" aria-label="Illustration of a tennis court and progress line. No actual player data.">
      <div class="ctr-illustration-header"><span><i class="ti ti-ball-tennis" aria-hidden="true"></i> A view of their game</span><span class="ctr-illustration-label">Illustration</span></div>
      <svg class="ctr-court" viewBox="0 0 440 290" fill="none" aria-hidden="true">
        <rect width="440" height="290" fill="#004177"/><path d="M51 30H389V260H51V30ZM87 30V260M353 30V260M87 87H353M87 203H353M220 87V203M51 145H389" stroke="#b8d8f5" stroke-width="2"/>
        <path d="M26 145H414" stroke="#e0ebf5" stroke-width="2" stroke-dasharray="4 5"/>
        <rect x="113" y="69" width="235" height="156" rx="13" fill="#f5f8fc" stroke="#dce5ef"/>
        <path d="M137 193H325M137 160H325M137 127H325" stroke="#dce5ef"/>
        <path d="M138 183C161 183 161 153 185 160S217 170 239 141S269 153 287 123S309 127 324 109" stroke="#c6dff5" stroke-width="19" stroke-linecap="round"/>
        <path d="M138 183C161 183 161 153 185 160S217 170 239 141S269 153 287 123S309 127 324 109" stroke="#004177" stroke-width="3" stroke-linecap="round"/>
        <circle cx="324" cy="109" r="6" fill="#f5f8fc" stroke="#004177" stroke-width="3"/><circle cx="379" cy="237" r="17" fill="#d8eaff"/><path d="M367 225Q386 231 391 249M367 249Q374 232 391 225" stroke="#315b85" stroke-width="1.5"/>
      </svg>
      <div class="ctr-illustration-footer"><i class="ti ti-chart-line" aria-hidden="true"></i><div><strong>Every result adds context.</strong><span>Illustrative graphic · No actual player data</span></div></div>
    </div>
  </section>

  <div class="ctr-section-intro"><h2>More context. A clearer next step.</h2><p>Useful insight for players and families, built on eligible recorded singles results.</p></div>
  <div class="ctr-intro-features">
    <details class="ctr-intro-feature">
      <summary class="ctr-feature-summary"><span class="ctr-feature-icon"><i class="ti ti-route" aria-hidden="true"></i></span><h3>What is my next tournament?</h3><p>Explore event types for your ball level, from beginner and school events to Wilson and TSA options. Use development guidance to plan with your coach, then check event dates and entry rules.</p><span class="ctr-feature-toggle"><span class="ctr-feature-more">More details</span><span class="ctr-feature-less">Show less</span><span class="ctr-feature-chevron" aria-hidden="true"></span></span></summary>
      <div class="ctr-feature-details"><p>Start with your current rated ball level in My Tennis. The pathway brings together your saved ball-level rating and eligible published singles participation, so you and your coach can discuss the next event types to explore.</p><p>Progression benchmarks need suitable, comparable evidence. They are guidance for that conversation, not automatic promotion or entry approval. Always check the event’s age, ball-level, nomination and entry requirements before registering.</p></div>
    </details>
    <details class="ctr-intro-feature">
      <summary class="ctr-feature-summary"><span class="ctr-feature-icon"><i class="ti ti-chart-dots" aria-hidden="true"></i></span><h3>How am I progressing?</h3><p>Follow 12 months of saved rating history and the recorded singles results behind changes in playing strength, where evidence is available.</p><span class="ctr-feature-toggle"><span class="ctr-feature-more">More details</span><span class="ctr-feature-less">Show less</span><span class="ctr-feature-chevron" aria-hidden="true"></span></span></summary>
      <div class="ctr-feature-details"><p>Your history follows the ball level of your latest saved rating across the available monthly snapshots. Select a month to review its estimate and the eligible evidence behind it. Months without enough evidence may have no rating.</p><p>Read the curve alongside confidence, result dates and comparison-group context. A change in opponents or comparison group can affect an estimate; a higher number alone does not prove improvement, and ratings from different ball levels are not interchangeable.</p></div>
    </details>
    <details class="ctr-intro-feature">
      <summary class="ctr-feature-summary"><span class="ctr-feature-icon"><x-ctr-mark /></span><h3>What is my rating?</h3><p>Understand your saved Cape Tennis Rating, its evidence and confidence. See your age-group Top 10 and rating position where available.</p><span class="ctr-feature-toggle"><span class="ctr-feature-more">More details</span><span class="ctr-feature-less">Show less</span><span class="ctr-feature-chevron" aria-hidden="true"></span></span></summary>
      <div class="ctr-feature-details"><p>CTR is an estimate of playing strength from eligible recorded evidence. Check the saved update date and confidence label together with the number: sparse or older evidence needs more care, and confidence is not a guarantee of accuracy.</p><p>A provisional within-group estimate is not calibrated against other groups. Missing or non-comparable evidence may leave a rating or position unavailable. Buying Player Plus does not create results, guarantee a rating, or change tournament rankings or selection.</p></div>
    </details>
    <details class="ctr-intro-feature">
      <summary class="ctr-feature-summary"><span class="ctr-feature-icon"><i class="ti ti-trophy" aria-hidden="true"></i></span><h3>How strong is tomorrow’s opponent?</h3><p>Search by name and select one eligible published player to see their available rating and confidence. Compare playing strength where ratings share a comparable scale.</p><span class="ctr-feature-toggle"><span class="ctr-feature-more">More details</span><span class="ctr-feature-less">Show less</span><span class="ctr-feature-chevron" aria-hidden="true"></span></span></summary>
      <div class="ctr-feature-details"><p>Use the eligible-player lookup to select the person you mean and review their available published rating and confidence. It gives context for preparation; it does not predict a match result or reveal their private rating history.</p><p>Compare only ratings on the same comparable scale. A local or disconnected estimate cannot be compared directly with a player in another group, and some players may have no available rating. Opponent lookup does not link that player to your account or grant access to private records.</p></div>
    </details>
  </div>

  <section class="ctr-offer" aria-labelledby="ctr-offer-title">
    <div><p class="ctr-eyebrow">Optional Player Plus</p><h2 id="ctr-offer-title">Their tennis journey.<br>Your choice of plan.</h2><p class="ctr-offer-copy">One named player. Shared with their linked parents or guardians. Choose monthly or annual access and keep the focus on their progress.</p></div>
    <div class="ctr-offer-pricing">
      <x-player-plus-pricing />
      <div class="ctr-intro-actions">
        @if($plusSalesOpen)
          <a class="btn ctr-primary" href="{{ route('player-plus.index') }}">Choose a player <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
        @else
          <button class="btn ctr-primary" type="button" disabled>{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? 'Coming Soon' : 'Purchases paused' }}</button>
        @endif
      </div>
      <div class="ctr-offer-links"><a href="{{ route('rankings.index') }}">View series rankings</a><a href="{{ route('player-plus.terms') }}">Purchase details &amp; payment support</a></div>
    </div>
  </section>
  <div class="ctr-disclosures"><p>Eligible results are needed; buying Plus does not guarantee a rating. Ratings may be provisional or unavailable. Only players on the shared rating scale can be compared. Membership does not change rankings or selection.</p><p>Private history requires a linked account and active membership. Paying does not link a player to your account. Draw alerts are planned and not included yet.</p></div>
  @if($isSuperUser)<aside class="ctr-admin"><p>Your CTR access is active.</p><a href="{{ route('backend.player-performance.ratings') }}">Open CTR ratings <i class="ti ti-arrow-up-right ms-1" aria-hidden="true"></i></a></aside>@endif
</main>
@endsection
