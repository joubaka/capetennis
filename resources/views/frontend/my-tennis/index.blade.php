@extends(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout')

@section('title', 'My Tennis')

@section('content')
<div id="my-tennis-page" data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-player-workspace">
  <style>
    #my-tennis-page { min-width: 0; overflow-x: clip; }
    #my-tennis-page [data-rating-hero] .ct-workspace-header-main,
    #my-tennis-page [data-rating-hero] .ct-workspace-header-navigation {display:none}
    #my-tennis-page .ct-workspace-player-selector{width:min(100%,18rem)}
    #my-tennis-page .row, #my-tennis-page .card, #my-tennis-page .card-body { min-width: 0; }
    #my-tennis-page .table-responsive { max-width: 100%; }
    #my-tennis-page .my-tennis-heading { padding: .5rem 0 1.25rem; gap: 1rem; }
    #my-tennis-page .my-tennis-heading h1 { color: #20384b; font-size: clamp(1.75rem, 4vw, 2.25rem); font-weight: 700; letter-spacing: -.04em; }
    #my-tennis-page .my-tennis-heading p { font-size: .9rem; }
    #my-tennis-page .my-tennis-heading form { width: min(100%, 18rem); }
    #my-tennis-page .my-tennis-reminder { border-color: #d4dfd9; color: #456356; background: #f2f6f2; }
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
    #my-tennis-page .my-tennis-preview-toolbar { margin-bottom: 1rem; font-size: .8rem; }
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
      #my-tennis-page .my-tennis-header-actions { width: 100%; }
      #my-tennis-page .my-tennis-header-actions .btn { flex: 1; }
      #my-tennis-page .my-tennis-profile-header { padding: 1rem; }
    }
  </style>
  @php
    $workspaceTab = $workspaceTab ?? 'profile';
  @endphp
  @include('frontend.my-tennis.profile-header')

  @if(($canPreviewNonPlus ?? false) && ($ratingPlayer ?? null))
    <details class="my-tennis-preview-toolbar" data-non-plus-preview-control @if($previewNonPlus) open @endif>
      <summary>Admin preview</summary>
      <a class="btn btn-sm btn-outline-secondary" role="switch" aria-label="Preview without Player Plus" aria-checked="{{ $previewNonPlus ? 'true' : 'false' }}" href="{{ route('my.tennis', ['player' => $ratingPlayer->id, 'tab' => 'profile', 'preview_non_plus' => $previewNonPlus ? null : 1]) }}">Preview without Player Plus · {{ $previewNonPlus ? 'On' : 'Off' }}</a>
      @if($previewNonPlus)
        <p class="mb-0 mt-2" role="status">Viewing as a player without Player Plus.</p>
      @endif
    </details>
  @endif

  @if($workspaceTab === 'profile')
    @include('frontend.my-tennis.profile-details')
  @elseif($workspaceTab === 'rating' && ($ratingPlayer ?? null))
  <section id="my-tennis-rating" aria-label="Player rating" @if($canViewRating ?? false) data-rating-url="{{ route('my.tennis.rating', $ratingPlayer) }}" @endif>
    @if($canViewRating ?? false)
      @include('frontend.my-tennis.rating', ['embeddedRating' => true])
    @else
      @include('frontend.my-tennis.rating-preview')
    @endif
  </section>
  @elseif($workspaceTab === 'activity')
  <div id="my-tennis-overview">
  @if(!$selectedPlayer)
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-5 text-center">
        <div class="avatar avatar-lg mx-auto mb-3"><span class="avatar-initial rounded bg-label-primary"><i class="ti ti-user-plus fs-3"></i></span></div>
        <h5 class="mb-2">Your tennis dashboard is ready</h5>
        <p class="text-muted mb-3">A player relationship is needed to see their dashboard. Existing-player access and additional guardians are reviewed by Cape Tennis support.</p>
        <a class="btn btn-primary" href="{{ route('my.tennis', ['tab' => 'manage']) }}"><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Manage player access</a>
      </div>
    </div>
  @else
    @if(($playerCount ?? 0) > \App\Services\MyTennisService::PLAYER_LIMIT)<p class="small text-muted">Use Manage players to open a linked profile that is missing from the player selector.</p>@endif
    @if($matchReadLimited ?? false)<p class="small text-muted" role="status">Showing part of the schedule. Check the event programme for all matches.</p>@endif
    <div class="my-tennis-player-summary">
      <div class="my-tennis-player-details">
        <h2 class="my-tennis-section-heading mb-0">Upcoming matches</h2>
        <p class="small text-muted mb-0">{{ $upcomingMatchPage->total() }} scheduled</p>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <button class="btn btn-outline-primary" type="button" data-open-match-reminder aria-controls="match-reminder">Match reminders</button>
        @if(($canViewRating ?? false) && $upcomingMatchPage->total() > 0)
          <a class="btn btn-outline-primary" data-my-tennis-link href="{{ route('my.tennis.record', ['player' => $selectedPlayer, 'section' => 'opponents']) }}">Prepare for a matchup</a>
        @endif
      </div>
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
  <div id="my-tennis-history">
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Entries</h2><i class="ti ti-clipboard-list text-primary fs-4" aria-hidden="true"></i></div>
          <p class="text-muted small mb-3">Your entry is confirmed once payment is complete.</p>
          <div class="table-responsive" role="region" aria-label="Entries table" tabindex="0"><table class="table align-middle mb-0"><thead><tr><th scope="col">Event</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Payment</th></tr></thead><tbody>
          @forelse($entries as $entry)
            <tr><td>{{ $entry->categoryEvent?->event?->name ?? 'Event' }}</td><td>{{ $entry->categoryEvent?->category?->name ?? 'Category' }}</td><td>{{ $entry->status ?: 'Active' }}</td><td>{{ $entry->is_paid ? 'Paid' : 'Unpaid' }}</td></tr>
          @empty
            <tr><td colspan="4" class="text-muted">No entries found for this player.</td></tr>
          @endforelse
          </tbody></table></div>
          @if($entries instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="mt-3">{{ $entries->fragment('my-tennis-history')->links() }}</div>@endif
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h2 class="h5">Recent published results</h2>
          @forelse($history['placements'] as $placement)<div class="border-bottom py-2"><div class="fw-semibold">{{ $placement->categoryEvent?->event?->name ?? 'Event' }}</div><div class="small text-muted">{{ $placement->categoryEvent?->category?->name ?? 'Category' }} · Place {{ $placement->position }}</div></div>@empty<p class="text-muted mb-0">No published results yet.</p>@endforelse
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h2 class="h5">Current rankings</h2>
          @forelse($history['seriesRankings'] as $ranking)<div class="border-bottom py-2"><div class="fw-semibold">{{ $ranking->series?->name ?? 'Series' }}</div><div class="small text-muted">{{ $ranking->category?->name ?? 'Category' }} · #{{ $ranking->rank_position }} · {{ $ranking->total_points }} points</div></div>@empty<p class="text-muted mb-0">No published series ranking yet.</p>@endforelse
        </div></div>
      </div>
      @if(($canViewRating ?? false) && ($overviewPathway ?? null))
        <div class="col-12">
          <aside class="card card-body my-tennis-development" aria-labelledby="overview-development-heading" data-overview-development-comment>
            <div class="my-tennis-development-heading"><span class="my-tennis-development-icon" aria-hidden="true"><i class="ti ti-target-arrow"></i></span><div><h2 id="overview-development-heading">Your next development target</h2></div></div>
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
            <p class="small text-muted mb-2">Each tournament has its own entry rules.</p>
            <a class="btn my-tennis-plus-link align-self-start" data-my-tennis-link href="{{ route('my.tennis.pathway', $selectedPlayer) }}">See tournament options <span aria-hidden="true">→</span></a>
          </aside>
        </div>
      @endif
    </div>
  </div>
  @endif

  @elseif($workspaceTab === 'manage')
  <script type="application/json" data-my-tennis-manage-config>{!! json_encode([
    'searchUrl' => route('player.search', [], false),
    'linkUrl' => route('backend.user.players.store', $accountUser, false),
    'unlinkUrl' => route('backend.user.players.destroy', [$accountUser, '__PLAYER__'], false),
    'bulkUnlinkUrl' => route('backend.user.players.bulk-destroy', $accountUser, false),
    'playersUrl' => route('my.tennis.players', [], false),
    'profileUrl' => route('my.tennis', [], false),
    'linkedIds' => array_map('intval', $linkedPlayerIds),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
  <div id="my-tennis-manage">
  <div class="card my-tennis-manage-card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h5 class="mb-1">Manage player profiles</h5>
          <p class="text-muted mb-0">Link another player or manage the profiles connected to your account.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button id="my-tennis-bulk-unlink" class="btn btn-outline-danger" type="button" hidden disabled><i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected</button>
          <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#link-player-panel" aria-expanded="false" aria-controls="link-player-panel"><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Player access</button>
        </div>
      </div>
      <div class="mb-3" id="my-tennis-link-feedback" role="status" aria-live="polite"></div>
      <div class="collapse" id="link-player-panel">
        <div class="border rounded p-3 mb-3">
          @if($accountUser->hasRole('super-user'))
            <p class="alert alert-info">Link a player to your own account and open their Profile. Adding the link sends no notifications and is recorded in the audit log. While linked, your account may receive future player communications. You can unlink the player here when you’re done.</p>
          @else
            <div class="alert alert-info d-flex gap-2 align-items-start mb-3"><i class="ti ti-users fs-4" aria-hidden="true"></i><div><strong>Shared player access</strong><br><span class="small">Existing family links remain available. To add an existing player or another guardian, contact Cape Tennis support for relationship review. Finding or paying for a player does not grant private access.</span></div></div>
          @endif
          <label for="my-tennis-player-search" class="form-label">Find the player profile</label>
          <select id="my-tennis-player-search" class="form-select" aria-describedby="my-tennis-player-search-help my-tennis-player-search-status"><option></option></select>
          <p id="my-tennis-player-search-help" class="form-text mt-2">Type at least two characters of the player's name, then choose their profile. Selecting a profile does not link it.</p>
          <p id="my-tennis-player-search-status" class="small text-muted" role="status" aria-live="polite"></p>
          @if($accountUser->hasRole('super-user'))
            <button id="my-tennis-player-link-button" class="btn btn-primary" type="button" data-admin-profile-link disabled>Link and open profile</button>
          @else
            <button id="my-tennis-player-link-button" class="btn btn-primary" type="button" disabled>Restore existing access</button>
            <p class="form-text mb-0 mt-2">This can restore an existing relationship. New relationships require staff review.</p>
          @endif
        </div>
      </div>
      <div id="my-tennis-linked-players">
        @forelse($linkedPlayerPage->items() as $playerOption)
          <div class="player-chip" data-player-row="{{ $playerOption->id }}">
            <div>
              <input class="form-check-input my-tennis-player-check me-2" type="checkbox" value="{{ $playerOption->id }}" aria-label="Select {{ $playerOption->full_name }} for bulk unlink">
              <a class="fw-semibold" href="{{ route('my.tennis', ['player' => $playerOption->id]) }}">{{ $playerOption->full_name }}</a>
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
          <p class="text-muted mb-0">No linked players yet. Contact support to review access to an existing player, or create your own new profile from My Profile.</p>
        @endforelse
      </div>
      @if($linkedPlayerPage->hasMorePages())
        <button id="my-tennis-load-more" class="btn btn-outline-secondary w-100 mt-3" type="button" data-next-page="2">Load more players</button>
      @endif
    </div>
  </div>
  </div>
  @endif
  <x-player-plus-renewal-help />
</div>
@endsection

@include('frontend.my-tennis.workspace-assets')
