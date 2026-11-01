@extends('layouts/layoutMaster')

@section('title', 'CTR Rankings · Cape Tennis Rating')

@section('page-style')
<style>
  .ctr-intro { margin: 1rem auto; max-width: 68rem; }
  .ctr-intro-hero { background: linear-gradient(135deg, #172e45, #0e5360); border-radius: 1rem; color: #fff; padding: clamp(1.4rem, 4vw, 3rem); }
  .ctr-intro .ctr-intro-hero h1 { color: #fff !important; }
  .ctr-intro-hero p { color: #e2f2f1; max-width: 42rem; }
  .ctr-intro-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
  .ctr-intro-actions a { align-items: center; display: inline-flex; justify-content: center; min-height: 44px; white-space: normal; }
  .ctr-intro-features { display: grid; gap: 1rem; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-block: 1.5rem; }
  .ctr-intro-feature { background: #fff; border: 1px solid #dbe4eb; border-radius: .75rem; padding: 1.25rem; }
  @media (max-width: 767.98px) { .ctr-intro-features { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
<main class="ctr-intro" data-ctr-intro>
  <section class="ctr-intro-hero" aria-labelledby="ctr-title">
    <p class="fw-semibold mb-2">Cape Tennis Rating</p>
    <h1 id="ctr-title">Discover CTR Rankings</h1>
    <p class="mb-4">A new way to follow playing strength through recorded singles results. Explore CTR alongside the series rankings you already follow.</p>
    <div class="ctr-intro-actions">
      @if($isSuperUser)
        <a class="btn btn-warning" href="{{ route('backend.player-performance.ratings') }}">Open CTR ratings</a>
      @endif
      <a class="btn btn-light" href="{{ route('rankings.index') }}">View series rankings <i class="ti ti-arrow-right ms-2" aria-hidden="true"></i></a>
    </div>
  </section>

  <div class="ctr-intro-features">
    <section class="ctr-intro-feature"><h2 class="h5">Follow playing strength</h2><p class="mb-0">CTR uses eligible recorded singles results and connected opponents to estimate playing strength.</p></section>
    <section class="ctr-intro-feature"><h2 class="h5">Explore by age and gender</h2><p class="mb-0">Birth-year groups run from 1 January to 31 December, so birthdays in the same year stay together.</p></section>
    <section class="ctr-intro-feature"><h2 class="h5">Discover the Top 10</h2><p class="mb-0">See leading players by age group and gender, using comparable CTR ratings within each series.</p></section>
  </div>

  <section class="card"><div class="card-body">
    <h2 class="h4">CTR and Player Plus</h2>
    @if($isSuperUser)<p>Your CTR access is active. Choose a published series to see available CTR ratings and Top 10 lists.</p>@endif
    <p>Player Plus helps players and families understand playing strength and follow progress. Open the Player rating tab in My Tennis for a preview, or choose a player to review membership and activation.</p>
    <x-player-plus-benefits class="mb-3" />
    <div class="ctr-intro-actions mb-3">
      <a class="btn btn-primary" href="{{ route('player-plus.index') }}">Explore Player Plus</a>
      <a class="btn btn-outline-primary" href="{{ route('my.tennis') }}#rating">Preview in My Tennis</a>
    </div>
    <p>Everyone can browse published series rankings. Private player rating history requires an active membership and a linked account.</p>
    <p class="small text-muted mb-0">CTR is provisional and separate from official ranking points or selection decisions. Only players connected to the shared rating scale can be compared in a Top 10.</p>
  </div></section>
</main>
@endsection
