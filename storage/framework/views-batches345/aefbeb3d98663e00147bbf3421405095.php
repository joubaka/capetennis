

<?php $__env->startSection('title', 'My Tennis'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
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
    #my-tennis-tabs { flex-wrap: wrap; gap: .35rem; background: #e9eeeb; padding: .35rem; border-radius: 12px; }
    #my-tennis-tabs .nav-item { flex: 1 1 8rem; min-width: 0; }
    #my-tennis-tabs .nav-link { white-space: normal; width: 100%; min-height: 44px; padding: .65rem .5rem; font-size: .85rem; color: #576861; border-radius: 8px; }
    #my-tennis-tabs .nav-link.active { background: #233d50; color: #fff; box-shadow: 0 2px 5px rgba(32, 56, 75, .12); }
    #my-tennis-page #my-tennis-tab-content { padding: 0; background: transparent; border: 0; box-shadow: none; }
    #my-tennis-page .my-tennis-player-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1.5rem; background: #233d50; color: #fff; border-radius: 16px; margin-bottom: 1.75rem; }
    #my-tennis-page .my-tennis-player-avatar { display: grid; place-items: center; flex: 0 0 56px; height: 56px; border: 1px solid #72938b; border-radius: 16px; background: #3f605e; font-size: 1.2rem; font-weight: 600; }
    #my-tennis-page .my-tennis-player-details { flex: 1; min-width: 0; overflow-wrap: anywhere; }
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
      #my-tennis-page .my-tennis-player-details h2 { font-size: 1.1rem; }
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
  </style>
  <div class="my-tennis-heading d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
      <h1 class="mb-1">My Tennis</h1>
      <p class="text-muted mb-0">Your players, entries and published match information.</p>
    </div>
    <button class="btn my-tennis-reminder" type="button" data-open-match-reminder aria-controls="match-reminder"><i class="ti ti-bell me-1" aria-hidden="true"></i>Upcoming matches</button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($players->isNotEmpty()): ?>
      <form method="get" action="<?php echo e(route('my.tennis')); ?>" class="d-flex align-items-center gap-2">
        <label for="my-tennis-player" class="visually-hidden">Player</label>
        <select id="my-tennis-player" name="player" class="form-select my-tennis-player-select" onchange="this.form.submit()">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($playerOption->id); ?>" <?php if($selectedPlayer?->id === $playerOption->id): echo 'selected'; endif; ?>><?php echo e($playerOption->full_name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <ul class="nav nav-pills nav-fill mb-4" id="my-tennis-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="my-tennis-overview-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-overview" type="button" role="tab" aria-controls="my-tennis-overview" aria-selected="true"><i class="ti ti-dashboard me-1" aria-hidden="true"></i>Overview</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-history-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-history" type="button" role="tab" aria-controls="my-tennis-history" aria-selected="false"><i class="ti ti-clipboard-list me-1" aria-hidden="true"></i>Entries & history</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-manage-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-manage" type="button" role="tab" aria-controls="my-tennis-manage" aria-selected="false"><i class="ti ti-users me-1" aria-hidden="true"></i>Manage players</button></li>
  </ul>

  <div class="tab-content" id="my-tennis-tab-content">
  <div class="tab-pane fade show active" id="my-tennis-overview" role="tabpanel" aria-labelledby="my-tennis-overview-tab">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$selectedPlayer): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-5 text-center">
        <div class="avatar avatar-lg mx-auto mb-3"><span class="avatar-initial rounded bg-label-primary"><i class="ti ti-user-plus fs-3"></i></span></div>
        <h5 class="mb-2">Your tennis dashboard is ready</h5>
        <p class="text-muted mb-3">Link a player profile to see entries, matches, results and rankings here.</p>
        <button class="btn btn-primary" type="button" data-my-tennis-open-manage><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Link a player</button>
      </div>
    </div>
  <?php else: ?>
    <div class="my-tennis-player-summary">
      <span class="my-tennis-player-avatar" aria-hidden="true"><?php echo e(collect(preg_split('/\s+/u', trim($selectedPlayer->full_name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('')); ?></span>
      <div class="my-tennis-player-details">
        <span class="my-tennis-player-label">Player</span>
        <h2><?php echo e($selectedPlayer->full_name); ?></h2>
        <span class="my-tennis-player-status"><?php echo e($profile['message']); ?></span>
      </div>
      <div class="my-tennis-upcoming-total"><strong><?php echo e($upcomingMatchPage->total()); ?></strong><span>Upcoming matches</span></div>
    </div>
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
      <h2 class="my-tennis-section-heading mb-0">Upcoming scheduled matches</h2>
    </div>
    <?php
      $matchDays = $upcomingMatches->groupBy(fn ($match) => \Carbon\Carbon::parse($match->scheduled_at)->toDateString());
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $matchDays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $date => $dayMatches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <section class="mb-4" aria-label="Matches on <?php echo e(\Carbon\Carbon::parse($date)->format('l, j F Y')); ?>">
        <h3 class="my-tennis-match-date mb-3"><span class="my-tennis-date-badge" aria-hidden="true"><strong><?php echo e(\Carbon\Carbon::parse($date)->format('j')); ?></strong><small><?php echo e(\Carbon\Carbon::parse($date)->format('M')); ?></small></span><span><?php echo e(\Carbon\Carbon::parse($date)->format('l, j M Y')); ?></span></h3>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dayMatches->groupBy(fn ($match) => $match->draw?->event_id.'|'.$match->venue_id); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventMatches): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $firstMatch = $eventMatches->first();
          ?>
          <div class="my-tennis-event-card mb-3">
              <div class="my-tennis-event-header">
                <h3><?php echo e($firstMatch->draw?->event?->name ?? 'Published draw'); ?></h3>
                <div class="my-tennis-event-venue"><i class="ti ti-map-pin me-1" aria-hidden="true"></i><?php echo e($firstMatch->venue?->name ?? 'Venue to be confirmed'); ?></div>
              </div>
            <div class="my-tennis-event-fixtures">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventMatches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $isTeam = $match instanceof \App\Models\TeamFixture;
                  $home = $isTeam ? ($match->tie_display['home'] ?? 'TBD') : ($match->registration1?->display_name ?? 'TBD');
                  $away = $isTeam ? ($match->tie_display['away'] ?? 'TBD') : ($match->registration2?->display_name ?? 'TBD');
                  $shortHome = $isTeam ? ($match->tie_mobile_display['home'] ?? $home) : $home;
                  $shortAway = $isTeam ? ($match->tie_mobile_display['away'] ?? $away) : $away;
                ?>
                <article class="my-tennis-fixture" aria-label="<?php echo e($home); ?> versus <?php echo e($away); ?>">
                  <div class="my-tennis-fixture-time">
                    <time datetime="<?php echo e(\Carbon\Carbon::parse($match->scheduled_at)->toIso8601String()); ?>"><?php echo e(\Carbon\Carbon::parse($match->scheduled_at)->format('H:i')); ?></time>
                  </div>
                  <div class="my-tennis-fixture-sides">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTeam && empty($match->profile_match_players['home']) && empty($match->profile_match_players['away'])): ?><div class="small text-muted mb-1">Team fixture - players to be confirmed</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isTeam)): ?><div class="small text-muted mb-1"><?php echo e($match->draw?->drawName); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="fw-semibold" title="<?php echo e($home); ?>" aria-label="<?php echo e($home); ?>"><?php echo e($shortHome); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTeam): ?><div class="small text-muted" aria-label="Match players for <?php echo e($home); ?>"><?php echo e(implode(' · ', $match->profile_match_players['home'] ?? []) ?: 'Players to be confirmed'); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="small text-muted my-tennis-fixture-vs" aria-hidden="true">vs</div>
                    <div class="fw-semibold" title="<?php echo e($away); ?>" aria-label="<?php echo e($away); ?>"><?php echo e($shortAway); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTeam): ?><div class="small text-muted" aria-label="Match players for <?php echo e($away); ?>"><?php echo e(implode(' · ', $match->profile_match_players['away'] ?? []) ?: 'Players to be confirmed'); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                  <a class="my-tennis-fixture-link" href="<?php echo e(route($isTeam ? 'frontend.fixtures.show' : 'frontend.showDraw', $match->draw_id)); ?>">View fixtures<i class="ti ti-arrow-up-right ms-1" aria-hidden="true"></i></a>
                </article>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="card border-0 shadow-sm"><div class="card-body text-muted">No upcoming published match times found.</div></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($upcomingMatchPage->hasPages()): ?>
      <div class="mt-3"><?php echo e($upcomingMatchPage->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedPlayer): ?>
  <div class="tab-pane fade" id="my-tennis-history" role="tabpanel" aria-labelledby="my-tennis-history-tab">
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h5 class="mb-0">Entries</h5><i class="ti ti-clipboard-list text-primary fs-4" aria-hidden="true"></i></div>
          <p class="text-muted small mb-3">Only paid entries are confirmed and eligible for an event. An unpaid record is not an event entry until payment is completed.</p>
          <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Event</th><th>Category</th><th>Status</th><th>Payment</th></tr></thead><tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr><td><?php echo e($entry->categoryEvent?->event?->name ?? 'Event'); ?></td><td><?php echo e($entry->categoryEvent?->category?->name ?? 'Category'); ?></td><td><?php echo e($entry->status ?: 'Active'); ?></td><td><?php echo e($entry->is_paid ? 'Paid' : 'Not paid — entry not confirmed'); ?></td></tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="4" class="text-muted">No entries found for this player.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody></table></div>
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h5>Recent published results</h5>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $history['placements']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $placement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="border-bottom py-2"><div class="fw-semibold"><?php echo e($placement->categoryEvent?->event?->name ?? 'Event'); ?></div><div class="small text-muted"><?php echo e($placement->categoryEvent?->category?->name ?? 'Category'); ?> · Place <?php echo e($placement->position); ?></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-muted mb-0">No published results yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h5>Current rankings</h5>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $history['seriesRankings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ranking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="border-bottom py-2"><div class="fw-semibold"><?php echo e($ranking->series?->name ?? 'Series'); ?></div><div class="small text-muted"><?php echo e($ranking->category?->name ?? 'Category'); ?> · #<?php echo e($ranking->rank_position); ?> · <?php echo e($ranking->total_points); ?> points</div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-muted mb-0">No published series ranking yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div></div>
      </div>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="tab-pane fade" id="my-tennis-manage" role="tabpanel" aria-labelledby="my-tennis-manage-tab">
  <div class="card my-tennis-manage-card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h5 class="mb-1">Manage player profiles</h5>
          <p class="text-muted mb-0">Link another player or manage the profiles connected to your account.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button id="my-tennis-bulk-unlink" class="btn btn-outline-danger" type="button" disabled><i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected</button>
          <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#link-player-panel" aria-expanded="false" aria-controls="link-player-panel"><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Link a player</button>
        </div>
      </div>
      <div class="mb-3" id="my-tennis-link-feedback" role="status" aria-live="polite"></div>
      <div class="collapse" id="link-player-panel">
        <div class="border rounded p-3 mb-3">
          <div class="alert alert-info d-flex gap-2 align-items-start mb-3"><i class="ti ti-users fs-4" aria-hidden="true"></i><div><strong>Shared player access</strong><br><span class="small">Search for and link the player you manage. A player may be linked to more than one account.</span></div></div>
          <label for="my-tennis-player-search" class="form-label">Find the player profile</label>
          <div class="input-group">
            <input id="my-tennis-player-search" class="form-control" type="search" minlength="2" maxlength="100" placeholder="Search by name or email">
            <button id="my-tennis-player-search-button" class="btn btn-outline-primary" type="button">Search</button>
          </div>
          <div id="my-tennis-player-results" class="list-group mt-3"></div>
          <p class="form-text mb-0 mt-2">Linking does not remove the player from any other account.</p>
        </div>
      </div>
      <div id="my-tennis-linked-players">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $linkedPlayerPage->items(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="player-chip" data-player-row="<?php echo e($playerOption->id); ?>">
            <div>
              <input class="form-check-input my-tennis-player-check me-2" type="checkbox" value="<?php echo e($playerOption->id); ?>" aria-label="Select <?php echo e($playerOption->full_name); ?> for bulk unlink">
              <div class="fw-semibold"><?php echo e($playerOption->full_name); ?></div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array((int) $playerOption->id, $linkedPlayerIds, true)): ?>
                <small class="text-muted">Linked to this account</small>
              <?php else: ?>
                <small class="text-muted">Legacy account link</small>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <button class="btn btn-sm btn-outline-danger my-tennis-unlink" type="button" data-player-id="<?php echo e($playerOption->id); ?>" data-player-name="<?php echo e($playerOption->full_name); ?>">
              <i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink
            </button>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <p class="text-muted mb-0">No linked players yet. Search above to link a player profile.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedPlayerPage->hasMorePages()): ?>
        <button id="my-tennis-load-more" class="btn btn-outline-secondary w-100 mt-3" type="button" data-next-page="2">Load more players</button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
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
  const searchButton = document.getElementById('my-tennis-player-search-button');
  const results = document.getElementById('my-tennis-player-results');
  const feedback = document.getElementById('my-tennis-link-feedback');
  const linkedPlayers = document.getElementById('my-tennis-linked-players');
  const bulkButton = document.getElementById('my-tennis-bulk-unlink');
  const loadMoreButton = document.getElementById('my-tennis-load-more');
  if (!searchInput || !searchButton || !results || !feedback) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const searchUrl = <?php echo json_encode(route('player.search'), 15, 512) ?>;
  const linkUrl = <?php echo json_encode(route('backend.user.players.store', $accountUser), 512) ?>;
  const unlinkUrl = <?php echo json_encode(route('backend.user.players.destroy', [$accountUser, '__PLAYER__'])) ?>;
  const bulkUnlinkUrl = <?php echo json_encode(route('backend.user.players.bulk-destroy', $accountUser), 512) ?>;
  const playersUrl = <?php echo json_encode(route('my.tennis.players'), 15, 512) ?>;
  const linkedIds = new Set(<?php echo json_encode(array_map('intval', $linkedPlayerIds), 512) ?>);

  const showFeedback = (message, type = 'info') => {
    feedback.innerHTML = `<div class="alert alert-${type} py-2 mb-0">${message}</div>`;
  };

  const selectedIds = () => [...document.querySelectorAll('.my-tennis-player-check:checked')].map(input => Number(input.value));
  const refreshBulkButton = () => {
    const count = selectedIds().length;
    bulkButton.disabled = count === 0;
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
      unlink.className = 'btn btn-sm btn-outline-danger my-tennis-unlink'; unlink.type = 'button'; unlink.dataset.playerId = player.id; unlink.dataset.playerName = player.name;
      unlink.innerHTML = '<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink';
      row.append(details, unlink); linkedPlayers.append(row);
    });
  };

  const renderResults = players => {
    results.replaceChildren();
    if (!players.length) {
      results.innerHTML = '<div class="text-muted small">No matching player profiles found.</div>';
      return;
    }
    players.forEach(player => {
      const row = document.createElement('div');
      row.className = 'list-group-item d-flex justify-content-between align-items-center gap-2';
      const label = document.createElement('span');
      label.textContent = `${player.name || ''} ${player.surname || ''}`.trim();
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-sm btn-primary';
      button.textContent = linkedIds.has(Number(player.id)) ? 'Linked' : 'Link';
      button.disabled = linkedIds.has(Number(player.id));
      button.addEventListener('click', () => linkPlayer(player, button));
      row.append(label, button);
      results.append(row);
    });
  };

  const search = async () => {
    const query = searchInput.value.trim();
    if (query.length < 2) return showFeedback('Enter at least two characters to search.', 'warning');
    searchButton.disabled = true;
    try {
      const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Search failed');
      renderResults(await response.json());
    } catch (error) {
      showFeedback('Player search is currently unavailable. Please try again.', 'danger');
    } finally { searchButton.disabled = false; }
  };

  const linkPlayer = async (player, button) => {
    button.disabled = true;
    try {
      const response = await fetch(linkUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ player_id: player.id }) });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to link player');
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

  searchButton.addEventListener('click', search);
  searchInput.addEventListener('keydown', event => { if (event.key === 'Enter') search(); });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\my-tennis\index.blade.php ENDPATH**/ ?>