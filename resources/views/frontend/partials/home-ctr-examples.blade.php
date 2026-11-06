<div class="home-ctr-examples">
  <div class="home-ctr-example-title">The CTR badges</div>
  <div class="home-ctr-badges" role="group" aria-label="Example CTR badges">
    <div class="home-ctr-badge-preview">
      <x-ctr-badge :score="35.6" size="small" stage="red" title="Example Red Ball CTR badge; illustrative score only." />
      <span class="home-ctr-badge-label">Red Ball</span>
    </div>
    <div class="home-ctr-badge-preview">
      <x-ctr-badge :score="48.2" size="small" stage="orange" title="Example Orange Ball CTR badge; illustrative score only." />
      <span class="home-ctr-badge-label">Orange Ball</span>
    </div>
    <div class="home-ctr-badge-preview">
      <x-ctr-badge :score="62.4" size="small" :overall="true" title="Example Overall CTR badge; illustrative score only." />
      <span class="home-ctr-badge-label">Overall CTR</span>
    </div>
  </div>
  <div class="home-ctr-example-note">Example badges · illustrative scores</div>
</div>
