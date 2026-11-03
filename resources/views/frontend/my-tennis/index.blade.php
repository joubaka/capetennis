@extends('layouts/contentNavbarLayout')

@section('title', 'My Tennis')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div id="my-tennis-page" class="container-xxl flex-grow-1 container-p-y">
  <style>
    #my-tennis-page { min-width: 0; overflow-x: clip; }
    #my-tennis-page .row, #my-tennis-page .card, #my-tennis-page .card-body { min-width: 0; }
    #my-tennis-page .table-responsive { max-width: 100%; }
    #my-tennis-page .my-tennis-heading { padding: .5rem 0 1.25rem; gap: 1rem; }
    #my-tennis-page .my-tennis-heading h1 { color: #20384b; font-size: clamp(1.75rem, 4vw, 2.25rem); font-weight: 700; letter-spacing: -.04em; }
    #my-tennis-page .my-tennis-heading p { font-size: .9rem; }
    #my-tennis-page .my-tennis-heading form { width: min(100%, 18rem); }
    #my-tennis-page .my-tennis-reminder { border-color: #d4dfd9; color: #456356; background: #f2f6f2; }
    #my-tennis-tabs { flex-wrap: wrap; gap: .25rem; background: transparent; padding: 0; border-bottom: 1px solid #dce5e3; border-radius: 0; }
    #my-tennis-tabs .nav-item { flex: 0 1 auto; min-width: 0; }
    #my-tennis-tabs .nav-item:last-child { margin-left: auto; }
    #my-tennis-tabs .nav-link { white-space: normal; width: 100%; min-height: 44px; padding: .75rem 1rem; font-size: .85rem; color: #536873; border-radius: 8px 8px 0 0; border-bottom: 3px solid transparent; }
    #my-tennis-tabs .nav-link.active { background: #eef3fa; color: #004177; border-bottom-color: #004177; box-shadow: none; }
    #my-tennis-page #my-tennis-tab-content { padding: 0; background: transparent; border: 0; box-shadow: none; }
    #my-tennis-page .my-tennis-player-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1.5rem; background: #233d50; color: #fff; border-radius: 16px; margin-bottom: 1.75rem; }
    #my-tennis-page .my-tennis-player-avatar { display: grid; place-items: center; flex: 0 0 56px; height: 56px; border: 1px solid #72938b; border-radius: 16px; background: #3f605e; font-size: 1.2rem; font-weight: 600; }
    #my-tennis-page .my-tennis-player-details { flex: 1 1 12rem; min-width: 0; overflow-wrap: anywhere; }
    #my-tennis-page .my-tennis-overview-badge { min-width: 0; max-width: 100%; }
    #my-tennis-page .my-tennis-overview-badge > a { flex-wrap: wrap; }
    #my-tennis-page .my-tennis-player-details h2 { color: #fff; font-size: 1.3rem; font-weight: 600; margin: .2rem 0 .4rem; }
    #my-tennis-page .my-tennis-player-label { color: #c2d2d5; font-size: .7rem; letter-spacing: .1em; text-transform: uppercase; }
    #my-tennis-page .my-tennis-player-status { color: #d2e3d7; font-size: .8rem; }
    #my-tennis-page .my-tennis-upcoming-total { display: flex; flex-direction: column; padding-left: 1rem; border-left: 1px solid #536c78; }
    #my-tennis-page .my-tennis-upcoming-total strong { font-size: 1.75rem; line-height: 1.2; color: #fff; }
    #my-tennis-page .my-tennis-upcoming-total span { color: #c2d2d5; font-size: .75rem; }
    #my-tennis-page .my-tennis-section-heading { font-size: 1.1rem; font-weight: 600; color: #20384b; }
    #my-tennis-player + .select2-container { min-width: 11rem; max-width: 100%; }
    #my-tennis-player { min-height: 44px; }
    #my-tennis-player + .select2-container .select2-selection { min-height: 44px; display: flex; align-items: center; border: 1px solid #d4dfd9; border-radius: 8px; background: #fff; }
    #my-tennis-player + .select2-container .select2-selection__rendered { padding-left: .75rem; padding-right: 2rem; }
    #my-tennis-player + .select2-container .select2-selection__arrow { height: 42px; }
    #my-tennis-page .select2-dropdown { max-width: min(18rem, calc(100vw - 2rem)); }
    #my-tennis-player-search + .select2-container { max-width:100%; }
    #my-tennis-player-search + .select2-container .select2-selection { min-height:44px; display:flex; align-items:center; }
    #link-player-panel .select2-results__option { overflow-wrap:anywhere; }
    #my-tennis-page .my-tennis-match-date { display: flex; align-items: center; gap: .75rem; color: #3e5750; font-size: .9rem; }
    #my-tennis-page .my-tennis-date-badge { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 48px; height: 52px; flex: 0 0 48px; background: #e9efe7; border-radius: 10px; }
    #my-tennis-page .my-tennis-date-badge strong { font-size: 1.25rem; line-height: 1.2; }
    #my-tennis-page .my-tennis-date-badge small { font-size: .65rem; text-transform: uppercase; }
    #my-tennis-page .my-tennis-event-card { border: 1px solid #e1e7e3; border-radius: 14px; background: #fff; overflow: hidden; }
    #my-tennis-page .my-tennis-event-header { padding: 1rem 1.25rem; background: #f3f6f2; border-bottom: 1px solid #e3e9e1; }
    #my-tennis-page .my-tennis-event-header h3 { color: #627269; font-size: .8rem; line-height: 1.5; margin-bottom: .3rem; font-weight: 500; overflow-wrap: anywhere; }
    #my-tennis-page .my-tennis-event-venue { color: #2d493e; font-size: .95rem; font-weight: 600; overflow-wrap: anywhere; }
    #my-tennis-page .my-tennis-event-fixtures { padding: 0 1.25rem; }
    #my-tennis-page .my-tennis-fixture { display: grid; grid-template-columns: 4.5rem minmax(0, 1fr) auto; align-items: center; gap: 1rem; padding: 1rem 0; }
    #my-tennis-page .my-tennis-fixture + .my-tennis-fixture { border-top: 1px solid rgba(75, 70, 92, .12); }
    #my-tennis-page .my-tennis-fixture-time { display: flex; flex-direction: column; align-items: flex-start; gap: .4rem; }
    #my-tennis-page .my-tennis-fixture-time time { padding: .4rem .55rem; border-radius: 8px; background: #edf2f5; font-size: .95rem; font-weight: 700; color: #28485d; }
    #my-tennis-page .my-tennis-fixture-sides { min-width: 0; overflow-wrap: anywhere; font-size: .9rem; color: #2f4351; }
    #my-tennis-page .my-tennis-fixture-vs { line-height: 1.2; margin: .15rem 0; }
    #my-tennis-page .my-tennis-fixture-link { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; color: #456356; font-size: .8rem; text-decoration: underline; text-underline-offset: 3px; }
    #my-tennis-page .btn { min-height: 44px; }
    #my-tennis-page [data-rating-placeholder][hidden] { display: none !important; }
    @media (max-width: 575.98px) {
      #my-tennis-page .my-tennis-heading form { width: 100%; }
      #my-tennis-page .my-tennis-player-summary { padding: 1rem; gap: .75rem; }
      #my-tennis-page .my-tennis-player-avatar { flex-basis: 44px; height: 44px; border-radius: 12px; font-size: 1rem; }
      #my-tennis-page .my-tennis-player-details { flex-basis: calc(100% - 44px - .75rem); }
      #my-tennis-page .my-tennis-player-details h2 { font-size: 1.1rem; }
      #my-tennis-page .my-tennis-overview-badge { flex: 0 0 100%; }
      #my-tennis-page .my-tennis-upcoming-total { flex: 0 0 100%; flex-direction: row; align-items: baseline; gap: .5rem; padding: .75rem 0 0; border-left: 0; border-top: 1px solid #536c78; }
      #my-tennis-page .my-tennis-upcoming-total strong { font-size: 1.25rem; }
      #my-tennis-page .my-tennis-event-header { padding: .9rem; }
      #my-tennis-page .my-tennis-event-fixtures { padding: 0 .9rem; }
      #my-tennis-page .my-tennis-fixture { grid-template-columns: 3.75rem minmax(0, 1fr); gap: .5rem .65rem; }
      #my-tennis-page .my-tennis-fixture-time { align-self: start; }
      #my-tennis-page .my-tennis-fixture-link { grid-column: 2; justify-self: start; }
    }
    .my-tennis-manage-card .player-chip { align-items: center; display: flex; gap: .75rem; justify-content: space-between; }
    .my-tennis-manage-card .player-chip + .player-chip { border-top: 1px solid rgba(75, 70, 92, .12); padding-top: .75rem; }
    .my-tennis-manage-card .player-chip + .player-chip { margin-top: .75rem; }
    #my-tennis-page .my-tennis-heading { padding: 0; margin-bottom: 1rem !important; }
    #my-tennis-page .my-tennis-heading h1 { font-size: 1rem; letter-spacing: 0; margin: 0 !important; }
    #my-tennis-page .my-tennis-heading p { font-size: .8rem; }
    #my-tennis-page .my-tennis-profile-header { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1.25rem; margin-bottom: 1.25rem; background: #fff; border: 1px solid #e5ece9; border-radius: 16px; }
    #my-tennis-page .my-tennis-profile-header h2 { color: #20384b; font-size: clamp(1.4rem, 3vw, 1.8rem); margin: 0 0 .25rem; }
    #my-tennis-page .my-tennis-profile-header .my-tennis-player-avatar { background: #eef3fa; color: #004177; border: 0; border-radius: 50%; }
    #my-tennis-page .my-tennis-header-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    #my-tennis-page .my-tennis-plus-link { color: #fff; background: #004177; border: 1px solid #004177; font-weight: 600; gap: .5rem; }
    #my-tennis-page .my-tennis-plus-link:hover { color: #fff; background: #172e45; border-color: #172e45; }
    #my-tennis-page .my-tennis-plus-link:focus-visible { outline: 3px solid #b85b00; outline-offset: 3px; }
    #my-tennis-page .my-tennis-preview-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; margin-bottom: 1rem; font-size: .8rem; }
    #my-tennis-page .my-tennis-preview-toolbar summary { cursor: pointer; padding: .75rem 0; }
    #my-tennis-page .my-tennis-player-summary { padding: 1rem 1.25rem; background: #fff; color: #20384b; border: 1px solid #e5ece9; margin-bottom: 1.25rem; }
    #my-tennis-page .my-tennis-player-summary .text-white { color: #004177 !important; }
    #my-tennis-page .my-tennis-player-summary h2 { color: #20384b; font-size: 1.1rem; margin: 0 0 .25rem; }
    #my-tennis-page .my-tennis-upcoming-total { border-color: #dce5e3; }
    #my-tennis-page .my-tennis-upcoming-total strong { color: #20384b; }
    #my-tennis-page .my-tennis-upcoming-total span { color: #536873; }
    #my-tennis-page .my-tennis-plus-invitation { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1.5rem; padding: clamp(1.25rem, 3vw, 2rem); margin-bottom: 1.5rem; background: linear-gradient(115deg, #172e45, #004177); border: 1px solid #315b85; border-radius: 16px; box-shadow: 0 8px 24px #172e4514; color: #fff; }
    #my-tennis-page .my-tennis-plus-copy { flex: 1 1 24rem; min-width: 0; }
    #my-tennis-page .my-tennis-plus-eyebrow { display: inline-flex; align-items: center; gap: .4rem; margin-bottom: .75rem; color: #ff9f43; font-size: .75rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
    #my-tennis-page .my-tennis-plus-invitation h2 { color: #fff; font-size: clamp(1.3rem, 3vw, 1.75rem); line-height: 1.25; letter-spacing: -.025em; margin: 0 0 .6rem; }
    #my-tennis-page .my-tennis-plus-invitation p { margin: 0; color: #e0ebf5; max-width: 38rem; }
    #my-tennis-page .my-tennis-plus-benefits { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; list-style: none; padding: 0; margin: 1rem 0 0; font-size: .8rem; color: #fff; }
    #my-tennis-page .my-tennis-plus-benefits i { color: #ff9f43; margin-right: .3rem; }
    #my-tennis-page .my-tennis-plus-offer { flex: 0 1 16rem; }
    #my-tennis-page .my-tennis-plus-offer .my-tennis-plus-price { color: #fff; margin-bottom: .75rem; }
    #my-tennis-page .my-tennis-plus-price strong { font-size: 1.6rem; letter-spacing: -.03em; }
    #my-tennis-page .my-tennis-plus-offer .my-tennis-plus-link { width: 100%; background: #ff9f43; border-color: #ff9f43; color: #172e45; }
    #my-tennis-page .my-tennis-plus-offer .my-tennis-plus-link:hover { background: #ffb568; border-color: #ffb568; }
    #my-tennis-page .my-tennis-plus-offer .my-tennis-plus-terms { margin-top: .65rem; font-size: .75rem; line-height: 1.6; }
    @media (max-width: 575.98px) { #my-tennis-page .my-tennis-plus-offer { flex-basis: 100%; } }
    #my-tennis-page .my-tennis-development { border:1px solid #dbe4ef;border-radius:16px;background:linear-gradient(120deg,#f1f5fa,#fff);padding:1.5rem; }
    #my-tennis-page .my-tennis-development-heading { display:flex;align-items:center;gap:.85rem;margin-bottom:1rem; }
    #my-tennis-page .my-tennis-development-icon { display:grid;place-items:center;flex:0 0 48px;height:48px;border-radius:14px;background:#004177;color:#ff9f43;font-size:1.6rem; }
    #my-tennis-page .my-tennis-development-heading small { color:#004177;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:.7rem; }
    #my-tennis-page .my-tennis-development h2 { color:#172e45;font-size:1.15rem;margin:.2rem 0 0; }
    #my-tennis-page .my-tennis-development li { margin-bottom:.4rem; }
    #my-tennis-page .player-chip > div:first-child { min-width: 0; overflow-wrap: anywhere; }
    #my-tennis-page .player-actions { flex-shrink: 0; position: relative; }
    #my-tennis-page .player-actions summary { cursor: pointer; color: #536873; padding: .75rem; min-height: 44px; }
    #my-tennis-page .player-actions[open] .my-tennis-unlink { position: absolute; z-index: 5; right: 0; top: 100%; background: #fff; border: 1px solid #e5ece9; box-shadow: 0 4px 16px #20384b12; white-space: nowrap; }
    #my-tennis-page #my-tennis-bulk-unlink[hidden] { display: none !important; }
    @media (max-width: 575.98px) {
      #my-tennis-tabs .nav-item { flex: 1 1 45%; }
      #my-tennis-tabs .nav-item:last-child { margin-left: 0; }
      #my-tennis-tabs .nav-link { padding: .65rem .4rem; }
      #my-tennis-page .my-tennis-header-actions { width: 100%; }
      #my-tennis-page .my-tennis-header-actions .btn { flex: 1; }
      #my-tennis-page .my-tennis-profile-header { padding: 1rem; }
    }
  </style>
  <div class="my-tennis-heading d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
      <h1 class="mb-1">My Tennis</h1>
      <p class="text-muted mb-0">Your players, entries and published match information.</p>
    </div>
    @if($players->isNotEmpty())
      <form method="get" action="{{ route('my.tennis') }}" class="d-flex align-items-center gap-2">
        @if($previewNonPlus ?? false)<input type="hidden" name="preview_non_plus" value="1">@endif
        <label for="my-tennis-player" class="visually-hidden">Player</label>
        <select id="my-tennis-player" name="player" class="form-select my-tennis-player-select" onchange="this.form.submit()">
          @foreach($players as $playerOption)
            <option value="{{ $playerOption->id }}" @selected($selectedPlayer?->id === $playerOption->id)>{{ $playerOption->full_name }}</option>
          @endforeach
        </select>
      </form>
    @endif
  </div>

  @php
    $headerPlayer = $selectedPlayer ?? ($ratingPlayer ?? null);
  @endphp
  @if($headerPlayer)
    <header class="my-tennis-profile-header" data-player-profile-header>
      <span class="my-tennis-player-avatar" aria-hidden="true">{{ collect(preg_split('/\s+/u', trim($headerPlayer->full_name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span>
      <div class="my-tennis-player-details">
        <h2>{{ $headerPlayer->full_name }}</h2>
        @if($selectedPlayer)<p class="small text-muted mb-0">{{ $profile['message'] }}</p>@endif
      </div>
      <div class="my-tennis-header-actions">
        @if($selectedPlayer)
        <button class="btn my-tennis-reminder" type="button" data-open-match-reminder aria-controls="match-reminder"><i class="ti ti-bell me-1" aria-hidden="true"></i>Upcoming matches</button>
        @endif
        <a class="btn my-tennis-plus-link" href="{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? route('ctr.index') : route('player-plus.player', $headerPlayer) }}">Player Plus <span aria-hidden="true">↗</span></a>
      </div>
    </header>
  @else
    <p><a class="btn my-tennis-plus-link" href="{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? route('ctr.index') : route('player-plus.index') }}">Explore Player Plus <span aria-hidden="true">→</span></a></p>
  @endif

  <x-player-plus-coming-soon class="mb-3" />
  <x-player-plus-renewal-help />

  @if(($canPreviewNonPlus ?? false) && ($ratingPlayer ?? null))
    <div class="my-tennis-preview-toolbar" data-non-plus-preview-control>
      <a class="btn btn-sm btn-outline-secondary" role="switch" aria-label="Preview without Player Plus" aria-checked="{{ $previewNonPlus ? 'true' : 'false' }}" href="{{ route('my.tennis', ['player' => $ratingPlayer->id, 'preview_non_plus' => $previewNonPlus ? null : 1]) }}#rating">Preview without Player Plus · {{ $previewNonPlus ? 'On' : 'Off' }}</a>
      @if($previewNonPlus)
        <details><summary>About this preview</summary><p class="mb-0" role="status">Non-Plus preview is on. You are seeing the ratings preview and activation link shown to users without Plus. The player's membership is unchanged.</p></details>
      @endif
    </div>
  @endif

  <ul class="nav nav-pills nav-fill mb-4" id="my-tennis-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="my-tennis-overview-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-overview" type="button" role="tab" aria-controls="my-tennis-overview" aria-selected="true"><i class="ti ti-dashboard me-1" aria-hidden="true"></i>Overview</button></li>
    @if($ratingPlayer ?? null)
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-rating-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-rating" type="button" role="tab" aria-controls="my-tennis-rating" aria-selected="false">Player rating</button></li>
    @endif
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-history-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-history" type="button" role="tab" aria-controls="my-tennis-history" aria-selected="false"><i class="ti ti-clipboard-list me-1" aria-hidden="true"></i>Entries & history</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-manage-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-manage" type="button" role="tab" aria-controls="my-tennis-manage" aria-selected="false"><i class="ti ti-users me-1" aria-hidden="true"></i>Manage players</button></li>
  </ul>

  <div class="tab-content" id="my-tennis-tab-content">
  @if($ratingPlayer ?? null)
  <div class="tab-pane fade" id="my-tennis-rating" role="tabpanel" aria-labelledby="my-tennis-rating-tab" @if($canViewRating ?? false) data-rating-url="{{ route('my.tennis.rating', $ratingPlayer) }}" @endif>
    @if($canViewRating ?? false)
    <div class="card" data-rating-placeholder><div class="card-body"><p role="status">Select Player rating to view the saved last 12 months.</p></div></div>
    @else
      @include('frontend.my-tennis.rating-preview')
    @endif
  </div>
  @endif
  <div class="tab-pane fade show active" id="my-tennis-overview" role="tabpanel" aria-labelledby="my-tennis-overview-tab">
  @if(!$selectedPlayer)
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-5 text-center">
        <div class="avatar avatar-lg mx-auto mb-3"><span class="avatar-initial rounded bg-label-primary"><i class="ti ti-user-plus fs-3"></i></span></div>
        <h5 class="mb-2">Your tennis dashboard is ready</h5>
        <p class="text-muted mb-3">Link a player profile to see entries, matches, results and rankings here.</p>
        <button class="btn btn-primary" type="button" data-my-tennis-open-manage><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Link a player</button>
      </div>
    </div>
  @else
    @include('frontend.my-tennis.teamsheet-connection')
    <div class="my-tennis-player-summary">
      <div class="my-tennis-player-details"><h2 class="my-tennis-section-heading mb-1">Your tennis at a glance</h2><p class="small text-muted mb-0">Published matches and your player rating.</p></div>
      @include('frontend.my-tennis.overview-badge')
      <div class="my-tennis-upcoming-total"><strong>{{ $upcomingMatchPage->total() }}</strong><span>Upcoming matches</span></div>
    </div>
    @if(($canViewRating ?? false) && ($overviewPathway ?? null))
      <aside class="card card-body my-tennis-development mb-4" aria-labelledby="overview-development-heading" data-overview-development-comment>
        <div class="my-tennis-development-heading"><span class="my-tennis-development-icon" aria-hidden="true"><i class="ti ti-target-arrow"></i></span><div><small>Player Plus · Your next step</small><h2 id="overview-development-heading">Your next development target</h2></div></div>
        @if($overviewPathway['assessed'] && $overviewPathway['target'])
          <p class="mb-2">
            {{ $overviewPathway['ready'] ? 'Benchmark achieved:' : 'Aim for' }} <strong>{{ $overviewPathway['ctr_label'] }} {{ number_format($overviewPathway['target']['ctr'], 1) }}</strong>
            @if($overviewPathway['benchmark_division'])
              for the Wilson {{ $overviewPathway['benchmark_division'] }} benchmark,
            @endif
            with at least {{ $overviewPathway['target']['events'] }} published {{ $overviewPathway['current_stage_label'] }} events.
          </p>
        @endif
        <ul class="small ps-3 mb-2">@foreach($overviewPathway['next_steps'] as $step)<li>{{ $step }}</li>@endforeach</ul>
        <p class="small text-muted mb-2">These are development targets; each tournament has its own entry rules.</p>
        <a class="btn my-tennis-plus-link align-self-start" data-pathway-navigation href="{{ route('my.tennis.pathway', $selectedPlayer) }}">Explore my tennis pathway <span aria-hidden="true">→</span></a>
      </aside>
    @endif
    @if(($ratingPlayer ?? null) && !($canViewRating ?? false))
      <aside class="my-tennis-plus-invitation" aria-label="Explore Player Plus">
        <div class="my-tennis-plus-copy">
          <span class="my-tennis-plus-eyebrow"><i class="ti ti-sparkles" aria-hidden="true"></i> Player Plus</span>
          <h2>See the progress behind every match.</h2>
          <p>Get a clearer picture of their game with a Cape Tennis Rating, a year of progress and the results behind the number.</p>
          <ul class="my-tennis-plus-benefits">
            <li><i class="ti ti-check" aria-hidden="true"></i>Player rating</li>
            <li><i class="ti ti-check" aria-hidden="true"></i>12 months of progress</li>
            <li><i class="ti ti-check" aria-hidden="true"></i>Results that explain the rating</li>
          </ul>
        </div>
        <div class="my-tennis-plus-offer">
          <x-player-plus-pricing class="my-tennis-plus-price" />
          <a class="btn my-tennis-plus-link" href="{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? route('ctr.index') : route('player-plus.player', $ratingPlayer) }}">Explore Player Plus <span aria-hidden="true">→</span></a>
          <p class="my-tennis-plus-terms">Eligible results required for a rating.</p>
        </div>
      </aside>
    @endif
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
      <h2 class="my-tennis-section-heading mb-0">Upcoming scheduled matches</h2>
    </div>
    @php
      $matchDays = $upcomingMatches->groupBy(fn ($match) => \Carbon\Carbon::parse($match->scheduled_at)->toDateString());
    @endphp
    @forelse($matchDays as $date => $dayMatches)
      <section class="mb-4" aria-label="Matches on {{ \Carbon\Carbon::parse($date)->format('l, j F Y') }}">
        <h3 class="my-tennis-match-date mb-3"><span class="my-tennis-date-badge" aria-hidden="true"><strong>{{ \Carbon\Carbon::parse($date)->format('j') }}</strong><small>{{ \Carbon\Carbon::parse($date)->format('M') }}</small></span><span>{{ \Carbon\Carbon::parse($date)->format('l, j M Y') }}</span></h3>
        @foreach($dayMatches->groupBy(fn ($match) => $match->draw?->event_id.'|'.$match->venue_id) as $eventMatches)
          @php
            $firstMatch = $eventMatches->first();
          @endphp
          <div class="my-tennis-event-card mb-3">
              <div class="my-tennis-event-header">
                <h3>{{ $firstMatch->draw?->event?->name ?? 'Published draw' }}</h3>
                <div class="my-tennis-event-venue"><i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ $firstMatch->venue?->name ?? 'Venue to be confirmed' }}</div>
              </div>
            <div class="my-tennis-event-fixtures">
              @foreach($eventMatches as $match)
                @php
                  $isTeam = $match instanceof \App\Models\TeamFixture;
                  $home = $isTeam ? ($match->tie_display['home'] ?? 'TBD') : ($match->registration1?->display_name ?? 'TBD');
                  $away = $isTeam ? ($match->tie_display['away'] ?? 'TBD') : ($match->registration2?->display_name ?? 'TBD');
                  $shortHome = $isTeam ? ($match->tie_mobile_display['home'] ?? $home) : $home;
                  $shortAway = $isTeam ? ($match->tie_mobile_display['away'] ?? $away) : $away;
                @endphp
                <article class="my-tennis-fixture" aria-label="{{ $home }} versus {{ $away }}">
                  <div class="my-tennis-fixture-time">
                    <time datetime="{{ \Carbon\Carbon::parse($match->scheduled_at)->toIso8601String() }}">{{ \Carbon\Carbon::parse($match->scheduled_at)->format('H:i') }}</time>
                  </div>
                  <div class="my-tennis-fixture-sides">
                    @if($isTeam && empty($match->profile_match_players['home']) && empty($match->profile_match_players['away']))<div class="small text-muted mb-1">Team fixture - players to be confirmed</div>@endif
                    @unless($isTeam)<div class="small text-muted mb-1">{{ $match->draw?->drawName }}</div>@endunless
                    <div class="fw-semibold" title="{{ $home }}" aria-label="{{ $home }}">{{ $shortHome }}</div>
                    @if($isTeam)<div class="small text-muted" aria-label="Match players for {{ $home }}">{{ implode(' · ', $match->profile_match_players['home'] ?? []) ?: 'Players to be confirmed' }}</div>@endif
                    <div class="small text-muted my-tennis-fixture-vs" aria-hidden="true">vs</div>
                    <div class="fw-semibold" title="{{ $away }}" aria-label="{{ $away }}">{{ $shortAway }}</div>
                    @if($isTeam)<div class="small text-muted" aria-label="Match players for {{ $away }}">{{ implode(' · ', $match->profile_match_players['away'] ?? []) ?: 'Players to be confirmed' }}</div>@endif
                  </div>
                  <a class="my-tennis-fixture-link" href="{{ route($isTeam ? 'frontend.fixtures.show' : 'frontend.showDraw', $match->draw_id) }}">View fixtures<i class="ti ti-arrow-up-right ms-1" aria-hidden="true"></i></a>
                </article>
              @endforeach
            </div>
          </div>
        @endforeach
      </section>
    @empty
      <div class="card border-0 shadow-sm"><div class="card-body text-muted">No upcoming published match times found.</div></div>
    @endforelse
    @if($upcomingMatchPage->hasPages())
      <div class="mt-3">{{ $upcomingMatchPage->links() }}</div>
    @endif
  @endif
  </div>

  @if($selectedPlayer)
  <div class="tab-pane fade" id="my-tennis-history" role="tabpanel" aria-labelledby="my-tennis-history-tab">
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h5 class="mb-0">Entries</h5><i class="ti ti-clipboard-list text-primary fs-4" aria-hidden="true"></i></div>
          <p class="text-muted small mb-3">Only paid entries are confirmed and eligible for an event. An unpaid record is not an event entry until payment is completed.</p>
          <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Event</th><th>Category</th><th>Status</th><th>Payment</th></tr></thead><tbody>
          @forelse($entries as $entry)
            <tr><td>{{ $entry->categoryEvent?->event?->name ?? 'Event' }}</td><td>{{ $entry->categoryEvent?->category?->name ?? 'Category' }}</td><td>{{ $entry->status ?: 'Active' }}</td><td>{{ $entry->is_paid ? 'Paid' : 'Not paid — entry not confirmed' }}</td></tr>
          @empty
            <tr><td colspan="4" class="text-muted">No entries found for this player.</td></tr>
          @endforelse
          </tbody></table></div>
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h5>Recent published results</h5>
          @forelse($history['placements'] as $placement)<div class="border-bottom py-2"><div class="fw-semibold">{{ $placement->categoryEvent?->event?->name ?? 'Event' }}</div><div class="small text-muted">{{ $placement->categoryEvent?->category?->name ?? 'Category' }} · Place {{ $placement->position }}</div></div>@empty<p class="text-muted mb-0">No published results yet.</p>@endforelse
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h5>Current rankings</h5>
          @forelse($history['seriesRankings'] as $ranking)<div class="border-bottom py-2"><div class="fw-semibold">{{ $ranking->series?->name ?? 'Series' }}</div><div class="small text-muted">{{ $ranking->category?->name ?? 'Category' }} · #{{ $ranking->rank_position }} · {{ $ranking->total_points }} points</div></div>@empty<p class="text-muted mb-0">No published series ranking yet.</p>@endforelse
        </div></div>
      </div>
    </div>
  </div>
  @endif

  <div class="tab-pane fade" id="my-tennis-manage" role="tabpanel" aria-labelledby="my-tennis-manage-tab">
  <div class="card my-tennis-manage-card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h5 class="mb-1">Manage player profiles</h5>
          <p class="text-muted mb-0">Link another player or manage the profiles connected to your account.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button id="my-tennis-bulk-unlink" class="btn btn-outline-danger" type="button" hidden disabled><i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected</button>
          <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#link-player-panel" aria-expanded="false" aria-controls="link-player-panel"><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Link a player</button>
        </div>
      </div>
      <div class="mb-3" id="my-tennis-link-feedback" role="status" aria-live="polite"></div>
      <div class="collapse" id="link-player-panel">
        <div class="border rounded p-3 mb-3">
          <div class="alert alert-info d-flex gap-2 align-items-start mb-3"><i class="ti ti-users fs-4" aria-hidden="true"></i><div><strong>Shared player access</strong><br><span class="small">Search for and link the player you manage. A player may be linked to more than one account.</span></div></div>
          <label for="my-tennis-player-search" class="form-label">Find the player profile</label>
          <select id="my-tennis-player-search" class="form-select" aria-describedby="my-tennis-player-search-help my-tennis-player-search-status"><option></option></select>
          <p id="my-tennis-player-search-help" class="form-text mt-2">Type at least two characters of the player's name, then choose their profile. Selecting a profile does not link it.</p>
          <p id="my-tennis-player-search-status" class="small text-muted" role="status" aria-live="polite"></p>
          <button id="my-tennis-player-link-button" class="btn btn-primary" type="button" disabled>Link selected player</button>
          <p class="form-text mb-0 mt-2">Linking does not remove the player from any other account.</p>
        </div>
      </div>
      <div id="my-tennis-linked-players">
        @forelse($linkedPlayerPage->items() as $playerOption)
          <div class="player-chip" data-player-row="{{ $playerOption->id }}">
            <div>
              <input class="form-check-input my-tennis-player-check me-2" type="checkbox" value="{{ $playerOption->id }}" aria-label="Select {{ $playerOption->full_name }} for bulk unlink">
              <div class="fw-semibold">{{ $playerOption->full_name }}</div>
              @if(in_array((int) $playerOption->id, $linkedPlayerIds, true))
                <small class="text-muted">Linked to this account</small>
              @else
                <small class="text-muted">Legacy account link</small>
              @endif
            </div>
            <details class="player-actions"><summary aria-label="Actions for {{ $playerOption->full_name }}">Actions</summary>
            <button class="btn btn-sm text-danger my-tennis-unlink" type="button" data-player-id="{{ $playerOption->id }}" data-player-name="{{ $playerOption->full_name }}">
              <i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink
            </button>
            </details>
          </div>
        @empty
          <p class="text-muted mb-0">No linked players yet. Search above to link a player profile.</p>
        @endforelse
      </div>
      @if($linkedPlayerPage->hasMorePages())
        <button id="my-tennis-load-more" class="btn btn-outline-secondary w-100 mt-3" type="button" data-next-page="2">Load more players</button>
      @endif
    </div>
  </div>
  </div>


</div>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/player-rating-history-refresh.js') }}"></script>
<script src="{{ asset('assets/js/player-pathway.js') }}?v={{ filemtime(public_path('assets/js/player-pathway.js')) }}"></script>
<script src="{{ asset('assets/js/my-tennis-player-search.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-player-search.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('my-tennis-rating-tab');
  const panel = document.getElementById('my-tennis-rating');
  if (!button || !panel) return;
  document.querySelector('[data-overview-rating-link]')?.addEventListener('click', event => {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    bootstrap.Tab.getOrCreateInstance(button).show();
  });
  if (!panel.dataset.ratingUrl) {
    if (window.location.hash === '#rating') bootstrap.Tab.getOrCreateInstance(button).show();
    return;
  }
  let loaded = false, loading = false, refreshPending = false, pendingUrl = null, lastUrl = panel.dataset.ratingUrl;
  let loadingHandle = null;
  let focusId = null;
  const syncAgeFilter = () => {
    const level = panel.querySelector('[name="rating_level"]:not([type="hidden"])');
    const age = panel.querySelector('[data-rating-age-filter]');
    if (age && level) age.hidden = level.value !== 'overall';
  };
  const refreshUI = new window.PlayerRatingHistoryRefreshUI(panel, () => {
    if (loading) { refreshPending = true; return; }
    return load(lastUrl);
  }, { csrf: document.querySelector('meta[name="csrf-token"]')?.content || '' });
  new window.PlayerPathwayUI(panel, () => {
    if (loading) { refreshPending = true; return; }
    return load(lastUrl);
  }, { csrf: document.querySelector('meta[name="csrf-token"]')?.content || '' });
  const load = async (url = null) => {
    if (loading) {
      if (url) { lastUrl = url; pendingUrl = url; }
      return;
    }
    if (loaded && !url) return;
    lastUrl = url || lastUrl;
    const requestUrl = lastUrl;
    loading = true;
    if (!loadingHandle) {
      focusId = panel.contains(document.activeElement) ? document.activeElement.id : button.id;
      loadingHandle = window.CapeLoading.show({title: 'Loading ratings', message: 'Please wait while we load your saved rating history.'});
    }
    panel.querySelectorAll('[data-rating-placeholder], [data-rating-retry]').forEach(element => { element.hidden = true; });
    panel.setAttribute('aria-busy', 'true');
    let status = panel.querySelector('[data-rating-load-status]');
    if (!status) { status = document.createElement('p'); status.dataset.ratingLoadStatus = ''; status.setAttribute('role', 'status'); status.className = 'small text-muted d-flex align-items-center gap-2'; panel.prepend(status); }
    status.textContent = '';
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm flex-shrink-0';
    spinner.setAttribute('aria-hidden', 'true');
    status.append(spinner, 'Loading saved rating history…');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(requestUrl, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', signal: controller.signal });
      if (!response.ok || response.redirected) throw new Error('Unavailable');
      const html = await response.text();
      if (pendingUrl) return;
      const activeSection = panel.querySelector('[data-rating-section].active')?.dataset.ratingSection || 'progress';
      panel.innerHTML = html;
      syncAgeFilter();
      const sectionButton = panel.querySelector('[data-rating-section="' + activeSection + '"]');
      if (sectionButton) bootstrap.Tab.getOrCreateInstance(sectionButton).show();
      loaded = true;
      refreshUI.sync();
    } catch (error) {
      if (pendingUrl) return;
      status.textContent = 'Rating history could not be loaded. ';
      const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-sm btn-outline-primary'; retry.dataset.ratingRetry = ''; retry.textContent = 'Try again'; status.append(retry);
    } finally {
      clearTimeout(timeout);
      loading = false;
      panel.removeAttribute('aria-busy');
      if (pendingUrl) { const nextUrl = pendingUrl; pendingUrl = null; refreshPending = false; load(nextUrl); }
      else if (refreshPending) { refreshPending = false; load(lastUrl); }
      else {
        loadingHandle?.close();
        loadingHandle = null;
        const focusTarget = (focusId && document.getElementById(focusId)) || panel.querySelector('[data-rating-list-focus]') || button;
        focusTarget.focus({ preventScroll: true });
      }
    }
  };
  button.addEventListener('shown.bs.tab', () => load());
  panel.addEventListener('click', event => {
    const link = event.target.closest('[data-rating-link]');
    if (link && (event.button === undefined || event.button === 0) && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) { event.preventDefault(); load(link.getAttribute('href')); }
    else if (event.target.closest('[data-rating-retry]')) load(lastUrl);
  });
  const filterUrl = (form, changedControl = null) => {
    const url = new URL(form.action);
    const params = new URLSearchParams(new FormData(form));
    // A second form may still contain hidden values from the previous response.
    // Keep the latest choices while updating and after a failed request.
    const latest = new URL(lastUrl, form.action).searchParams;
    latest.forEach((value, key) => {
      const preserve = form.elements.namedItem(key)?.type === 'hidden'
        || (loading && changedControl && key !== changedControl.name);
      if (params.has(key) && preserve) params.set(key, value);
    });
    if (!params.has('rating_page') || ['rating_age', 'rating_gender', 'rating_level', 'rating_per_page'].includes(changedControl?.name)) params.set('rating_page', '1');
    url.search = params.toString();
    return url.toString();
  };
  panel.addEventListener('change', event => {
    const form = event.target.closest('[data-rating-filters]');
    if (form) {
      syncAgeFilter();
      if (loading && event.target.id) focusId = event.target.id;
      load(filterUrl(form, event.target));
    }
  });
  panel.addEventListener('submit', event => {
    const form = event.target.closest('[data-rating-filters]');
    if (form) { event.preventDefault(); load(filterUrl(form)); }
  });
  if (window.location.hash === '#rating') bootstrap.Tab.getOrCreateInstance(button).show();
});
</script>
<script>
(() => {
  const playerSelect = document.getElementById('my-tennis-player');
  if (playerSelect && window.jQuery?.fn?.select2) {
    window.jQuery(playerSelect).select2({ width: '100%', minimumResultsForSearch: 0, placeholder: 'Choose a player' });
  }
  document.querySelector('[data-my-tennis-open-manage]')?.addEventListener('click', () => {
    document.getElementById('my-tennis-manage-tab')?.click();
    window.setTimeout(() => document.querySelector('[data-bs-target="#link-player-panel"]')?.click(), 150);
  });
  const searchInput = document.getElementById('my-tennis-player-search');
  const linkButton = document.getElementById('my-tennis-player-link-button');
  const feedback = document.getElementById('my-tennis-link-feedback');
  const linkedPlayers = document.getElementById('my-tennis-linked-players');
  const bulkButton = document.getElementById('my-tennis-bulk-unlink');
  const loadMoreButton = document.getElementById('my-tennis-load-more');
  if (!searchInput || !linkButton || !feedback) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const searchUrl = {{ Illuminate\Support\Js::from(route('player.search', [], false)) }};
  const linkUrl = {{ Illuminate\Support\Js::from(route('backend.user.players.store', $accountUser, false)) }};
  const unlinkUrl = {{ Illuminate\Support\Js::from(route('backend.user.players.destroy', [$accountUser, '__PLAYER__'], false)) }};
  const bulkUnlinkUrl = {{ Illuminate\Support\Js::from(route('backend.user.players.bulk-destroy', $accountUser, false)) }};
  const playersUrl = {{ Illuminate\Support\Js::from(route('my.tennis.players', [], false)) }};
  const linkedIds = new Set({{ Illuminate\Support\Js::from(array_map('intval', $linkedPlayerIds)) }});

  const showFeedback = (message, type = 'info') => {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} py-2 mb-0`;
    alert.textContent = message;
    feedback.replaceChildren(alert);
  };

  const selectedIds = () => [...document.querySelectorAll('.my-tennis-player-check:checked')].map(input => Number(input.value));
  const refreshBulkButton = () => {
    const count = selectedIds().length;
    bulkButton.disabled = count === 0;
    bulkButton.hidden = count === 0;
    bulkButton.innerHTML = `<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected${count ? ` (${count})` : ''}`;
  };

  document.addEventListener('change', event => {
    if (event.target.matches('.my-tennis-player-check')) refreshBulkButton();
  });

  const appendPlayerRows = players => {
    players.forEach(player => {
      const row = document.createElement('div');
      row.className = 'player-chip';
      row.dataset.playerRow = player.id;
      const details = document.createElement('div');
      const checkbox = document.createElement('input');
      checkbox.className = 'form-check-input my-tennis-player-check me-2';
      checkbox.type = 'checkbox'; checkbox.value = player.id;
      checkbox.setAttribute('aria-label', `Select ${player.name} for bulk unlink`);
      const name = document.createElement('div'); name.className = 'fw-semibold'; name.textContent = player.name;
      const source = document.createElement('small'); source.className = 'text-muted'; source.textContent = player.linked ? 'Linked to this account' : 'Legacy account link';
      details.append(checkbox, name, source);
      const unlink = document.createElement('button');
      unlink.className = 'btn btn-sm text-danger my-tennis-unlink'; unlink.type = 'button'; unlink.dataset.playerId = player.id; unlink.dataset.playerName = player.name;
      unlink.innerHTML = '<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink';
      const actions = document.createElement('details'); actions.className = 'player-actions';
      const summary = document.createElement('summary'); summary.textContent = 'Actions'; summary.setAttribute('aria-label', `Actions for ${player.name}`);
      actions.append(summary, unlink);
      row.append(details, actions); linkedPlayers.append(row);
    });
  };

  const linkPlayer = async (player, button) => {
    button.disabled = true;
    try {
      await window.MyTennisPlayerSearch.link({ url: linkUrl, csrf, playerId: player.id });
      showFeedback(`${player.name} ${player.surname || ''} is now linked to your account.`, 'success');
      window.location.reload();
    } catch (error) { button.disabled = false; showFeedback(error.message, 'danger'); }
  };

  const unlinkPlayer = async button => {
    const name = button.dataset.playerName;
    if (!window.confirm(`Unlink ${name} from your account? This will not delete the player or their history.`)) return;
    button.disabled = true;
    try {
      const response = await fetch(unlinkUrl.replace('__PLAYER__', button.dataset.playerId), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to unlink player');
      window.location.reload();
    } catch (error) { button.disabled = false; showFeedback(error.message, 'danger'); }
  };

  document.addEventListener('click', event => {
    const button = event.target.closest('.my-tennis-unlink');
    if (button) unlinkPlayer(button);
  });

  bulkButton.addEventListener('click', async () => {
    const ids = selectedIds();
    if (!ids.length || !window.confirm(`Unlink ${ids.length} selected player${ids.length === 1 ? '' : 's'}? Their records and history will be kept.`)) return;
    bulkButton.disabled = true;
    try {
      const response = await fetch(bulkUnlinkUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ player_ids: ids }) });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to unlink selected players');
      window.location.reload();
    } catch (error) { showFeedback(error.message, 'danger'); refreshBulkButton(); }
  });

  loadMoreButton?.addEventListener('click', async () => {
    const page = Number(loadMoreButton.dataset.nextPage || 2);
    loadMoreButton.disabled = true;
    try {
      const response = await fetch(`${playersUrl}?page=${page}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Unable to load more players');
      const payload = await response.json(); appendPlayerRows(payload.data || []);
      if (page >= Number(payload.meta?.last_page || page)) loadMoreButton.remove();
      else { loadMoreButton.dataset.nextPage = page + 1; loadMoreButton.disabled = false; }
    } catch (error) { showFeedback(error.message, 'danger'); loadMoreButton.disabled = false; }
  });

  new window.MyTennisPlayerSearch({ select: searchInput, button: linkButton,
    status: document.getElementById('my-tennis-player-search-status'), url: searchUrl, linkedIds,
    onLink: player => linkPlayer({ id: player.id, name: player.text }, linkButton) });
})();
</script>
@endsection
