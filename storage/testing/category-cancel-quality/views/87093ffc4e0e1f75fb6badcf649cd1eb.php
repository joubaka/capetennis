<?php
  $ratingFragment = ($embeddedRating ?? false) || request()->ajax();
  $activeRatingSection = match (request()->query('rating_section')) {
    'history' => 'progress',
    'method' => 'understand',
    default => 'compare',
  };
  $ratingSections = ['compare' => 'Rating overview', 'progress' => 'Rating history', 'understand' => 'How ratings work'];
  $canBrowseRatings = $ratingTopTen['can_browse'] ?? false;
  $ratingBrowseFilters = $canBrowseRatings ? ($ratingTopTen['filters'] ?? []) : [];
  $ratingSectionFilters = request()->only(['month', 'scope', 'scale', 'page']);
  if ($canBrowseRatings) {
    $ratingSectionFilters += $ratingBrowseFilters + request()->only(['rating_page']);
  }
  $ratingSectionUrl = fn ($section) => route('my.tennis.rating', ['player' => $ratingPlayer->id, 'rating_section' => $section] + $ratingSectionFilters);
?>
<section class="card ctr-history ct-player-workspace" aria-labelledby="player-rating-heading"><div class="card-body">
  <style>
    .ctr-history { border:1px solid #dbe4ef;border-radius:18px;overflow:hidden; }
    .ctr-history > .card-body { padding:clamp(1rem,3vw,2rem); }
    .ctr-history .ct-workspace-header .badge { background:#f4f6f8;color:#172e45;border:1px solid #dce5ef; }
    .ctr-history h3.h5 { color:#172e45;font-weight:600; }
    .ctr-history a:focus-visible,.ctr-history button:focus-visible,.ctr-history select:focus-visible,.ctr-history summary:focus-visible { outline:3px solid #b85b00;outline-offset:3px; }
    [data-match-progress-chart] button:focus-visible { outline:3px solid #b85b00;outline-offset:3px; }
    .ctr-history .btn-primary { background:#004177;border-color:#004177;color:#fff; }
    .ctr-history .btn-primary:hover { background:#172e45;border-color:#172e45; }
    .ctr-history .btn-outline-primary { color:#004177;border-color:#a5bcd5; }
    .ctr-history .btn-outline-primary:hover { background:#eef3fa;color:#172e45;border-color:#004177; }
    .ctr-history .ctr-section-tabs { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-rows:1fr;gap:.5rem; }
    .ctr-history .ctr-section-tabs .nav-link { min-height:44px;text-align:center;overflow-wrap:anywhere;white-space:normal;color:#47645c;background:#f1f5fa;border:1px solid #dbe4ef;border-radius:10px;font-weight:600; }
    .ctr-history .ctr-section-tabs .nav-link:hover { background:#e4edf8; }
    .ctr-history .ctr-section-tabs .nav-link.active { background:#004177;border-color:#004177;color:#fff;box-shadow:0 3px 8px #172e4518; }
    .ctr-history .ctr-section-content { padding:0;background:transparent;box-shadow:none; }
    .ctr-history .ctr-section-content > .tab-pane:not(.active) { display:none; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot {
      padding:clamp(1rem,2vw,1.5rem);
      background:#172e45;
      border-color:#172e45;
      color:#fff;
    }
    .ctr-history .ctr-overview-snapshot h3 { color:#fff;font-size:1rem;margin:0 0 .5rem; }
    .ctr-history .ctr-overview-snapshot p { margin:.35rem 0 0;color:#e0ebf5;font-size:.875rem; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-trend { width:100%;max-width:42rem;margin:1rem 0 .5rem; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-trend svg { height:auto; }
    .ctr-history .ctr-overview-snapshot figcaption { color:#e0ebf5;font-size:.8125rem; }
    .ctr-history .ctr-rating-status { color:#52677b;margin:0;padding:.5rem .75rem;background:#f1f5fa;border-radius:8px; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot:has([data-rating-mini-trend]) { display:block; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-score { flex-direction:row;flex-wrap:wrap;align-items:center; }
    .ctr-history .ctr-match-panel { border:1px solid #dbe4ef;border-radius:14px;overflow:hidden;background:#fff;margin-top:1.5rem; }
    .ctr-history .ctr-match-heading { background:#172e45;color:#fff;padding:1.1rem clamp(1rem,2vw,1.5rem); }
    .ctr-history .ctr-match-heading h3.h5 { color:#fff;font-size:1.15rem;margin:0 0 .35rem; }
    .ctr-history .ctr-match-heading p { margin:0;color:#e0ebf5;font-size:.8125rem;line-height:1.6; }
    .ctr-history .ctr-match-body { padding:clamp(1rem,2vw,1.5rem); }
    .ctr-history .ctr-match-body .text-muted { color:#52677b !important; }
    .ctr-history .ctr-match-scroll-cue { display:none; }
    .ctr-history .ctr-match-body > p { max-width:75ch;line-height:1.6; }
    .ctr-history .ctr-match-empty-note { padding:.8rem 1rem;background:#f1f5fa;border-radius:10px;color:#233d58;font-size:.875rem; }
    .ctr-history .ctr-match-list { display:grid;gap:.65rem;margin:1rem 0; }
    .ctr-history .ctr-match-row { display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr) auto;gap:.6rem 1.25rem;align-items:center;padding:1rem;border:1px solid #e0e8f1;border-radius:10px;background:#fbfcfe;overflow-wrap:anywhere; }
    .ctr-history .ctr-match-row h4 { font-size:.9rem;color:#172e45;margin:0 0 .25rem;font-weight:600; }
    .ctr-history .ctr-match-row p { margin:0;color:#52677b;font-size:.8125rem;line-height:1.5; }
    .ctr-history .ctr-match-row button { min-height:44px;white-space:normal;font-size:.8125rem; }
    @media(max-width:767.98px) { .ctr-history .ctr-match-row { grid-template-columns:1fr;gap:.5rem; }.ctr-history .ctr-match-row button { justify-self:start; } }
    @media(max-width:650px) { .ctr-history .ctr-match-scroll-cue { display:block; } }
    @media(min-width:768px) {
      .ctr-history .ctr-overview-snapshot .ct-rating-snapshot:has([data-rating-mini-trend]) {
        display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:.5rem 1.5rem;align-items:start;
      }
      .ctr-history .ctr-overview-snapshot .ct-rating-snapshot > :is(h3,p) { grid-column:1 / -1; }
      .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-trend { margin:0; }
    }
    @media(max-width:767.98px) { .ctr-history .ctr-section-tabs .nav-link { padding:.4rem .25rem;font-size:.8rem; } }
    .ctr-history .ctr-heading { display:flex;flex-wrap:wrap;justify-content:space-between;gap:.75rem;align-items:center; }
    .ctr-history .ctr-stats { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:1.25rem 0; }
    .ctr-history .ctr-stat { border:1px solid #dce6e2;border-radius:14px;padding:1.25rem;min-width:0; }
    .ctr-history .ctr-stats .ctr-stat { background:#f7f9fc;border-top:3px solid #004177; }
    .ctr-history .ctr-stats .ctr-stat:nth-child(2) { background:#fff6ed;border-top-color:#b85b00; }
    .ctr-history .ctr-stat h3 { font-size:.8rem;color:#5e716c;margin:0 0 .6rem; }
    .ctr-history .ctr-stat-value { color:#233d50;font-size:1.6rem;line-height:1.3;font-weight:700; }
    .ctr-history .ctr-filters { display:grid;grid-template-columns:1.5fr 1fr;gap:1rem;align-items:center;background:#f1f5fa;border:1px solid #dbe4ef;padding:1rem;border-radius:12px; }
    .ctr-history .ctr-filters label { font-size:.8rem;display:block;margin-bottom:.3rem; }
    .ctr-history .ctr-result { border-top:1px solid #e0e7e3;padding:1rem 0;overflow-wrap:anywhere; }
    .ctr-history .ctr-result-top { display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem;align-items:center; }
    .ctr-history .ctr-result h4 { font-size:1rem;margin:.5rem 0; }
    .ctr-history .ctr-legend { display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;color:#5e716c;font-size:.8rem; }
    .ctr-history .ctr-line-key { display:inline-block;vertical-align:middle;width:24px;border-top:3px solid #004177;margin-right:.4rem; }
    .ctr-history .ctr-line-key-dashed { border-top-style:dashed; }
    .ctr-history [data-history-refresh]:disabled::before { content:'';display:inline-block;width:1rem;height:1rem;margin-right:.5rem;vertical-align:middle;border:.15em solid currentColor;border-right-color:transparent;border-radius:50%;animation:ctr-update-spin .75s linear infinite; }
    @keyframes ctr-update-spin { to { transform:rotate(360deg); } }
    @media(prefers-reduced-motion:reduce) { .ctr-history [data-history-refresh]:disabled::before { animation:none; } }
    .ctr-history .ctr-chart-scroll { overflow-x:auto;max-width:100%;border:1px solid #dbe4ef;border-radius:14px;background:linear-gradient(#f7f9fc,#fff);padding:.75rem; }
    .ctr-history .ctr-history-chart { container-type:inline-size; }
    .ctr-history .ctr-chart-hint { display:none; }
    @container (width < calc(660px + 1.5rem + 2px)) { .ctr-history .ctr-chart-hint { display:block; } }
    .ctr-history .ctr-method { border-top:1px solid #e0e7e3;margin-top:1.5rem;padding-top:1rem; }
    @media(max-width:991.98px) { .ctr-history .ctr-stats { grid-template-columns:1fr;gap:.65rem; } }
    @media(max-width:767.98px) { .ctr-history .ctr-filters { grid-template-columns:1fr; } }
  </style>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment): ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h2 id="player-rating-heading" class="h5 mb-0">Player rating</h2>
      <a class="btn btn-sm btn-outline-primary" data-my-tennis-link href="<?php echo e(route('my.tennis.event-history', $ratingPlayer)); ?>">Past-event ratings</a>
    </div>
    <template data-profile-rating-update>
      <?php echo $__env->make('frontend.my-tennis.profile-rating-badge', [
        'badgeSnapshot' => \App\Services\Performance\PlayerRatingHistoryDisplay::snapshot($ratingHistory),
        'badgeUrl' => route('my.tennis', ['player' => $ratingPlayer->id, 'tab' => 'rating']),
      ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </template>
  <?php else: ?>
  <?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['compact' => true,'title' => 'Player rating','subtitle' => $ratingPlayer->full_name,'heading' => 'h2','headingId' => 'player-rating-heading','dataRatingHero' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['compact' => true,'title' => 'Player rating','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratingPlayer->full_name),'heading' => 'h2','heading-id' => 'player-rating-heading','data-rating-hero' => true]); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->hasRole('super-user')): ?><span class="badge mt-3">Super Admin preview</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
     <?php $__env->slot('summary', null, []); ?> 
      <?php echo $__env->make('frontend.my-tennis.rating-snapshot', ['compactUnavailable' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
     <?php $__env->endSlot(); ?>
     <?php $__env->slot('navigation', null, []); ?> 
    <a href="<?php echo e(route('my.tennis', ['player' => $ratingPlayer->id])); ?>">Back to My Tennis profile</a>
    <?php echo $__env->make('frontend.my-tennis.player-navigation', ['navigationPlayer' => $ratingPlayer, 'embeddedDashboard' => true, 'compactNavigation' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
     <?php $__env->endSlot(); ?>
   <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3480b572702ecfa053ab08ce698dcaca)): ?>
<?php $attributes = $__attributesOriginal3480b572702ecfa053ab08ce698dcaca; ?>
<?php unset($__attributesOriginal3480b572702ecfa053ab08ce698dcaca); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3480b572702ecfa053ab08ce698dcaca)): ?>
<?php $component = $__componentOriginal3480b572702ecfa053ab08ce698dcaca; ?>
<?php unset($__componentOriginal3480b572702ecfa053ab08ce698dcaca); ?>
<?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment): ?>
    <div class="nav nav-pills ctr-section-tabs my-3" role="tablist" aria-label="Explore your rating">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ratingSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button class="nav-link <?php echo e($section === $activeRatingSection ? 'active' : ''); ?>" id="ctr-tab-<?php echo e($section); ?>" data-rating-section="<?php echo e($section); ?>" data-bs-toggle="pill" data-bs-target="#ctr-panel-<?php echo e($section); ?>" type="button" role="tab" aria-controls="ctr-panel-<?php echo e($section); ?>" aria-selected="<?php echo e($section === $activeRatingSection ? 'true' : 'false'); ?>"><?php echo e($label); ?></button>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <noscript><p><a href="<?php echo e($ratingSectionUrl('history')); ?>">Open rating history</a></p></noscript>
  <?php else: ?>
    <nav class="nav nav-pills ctr-section-tabs my-3" aria-label="Explore your rating">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['compare' => null, 'progress' => 'history', 'understand' => 'method']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section => $sectionQuery): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a class="nav-link <?php echo e($section === $activeRatingSection ? 'active' : ''); ?>" id="ctr-tab-<?php echo e($section); ?>" href="<?php echo e($ratingSectionUrl($sectionQuery)); ?>" <?php if($section === $activeRatingSection): ?> aria-current="page" <?php endif; ?>><?php echo e($ratingSections[$section]); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </nav>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="tab-content ctr-section-content">
  <div class="tab-pane <?php echo e($activeRatingSection === 'compare' ? 'active show' : ''); ?>" id="ctr-panel-compare" role="tabpanel" aria-labelledby="ctr-tab-compare" tabindex="0">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment): ?>
      <div class="ctr-overview-snapshot">
        <?php echo $__env->make('frontend.my-tennis.rating-snapshot', ['expandedTrend' => true, 'compactUnavailable' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->make('frontend.my-tennis.rating-top-ten', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($ratingTopTen['visible'] ?? false)): ?><p>Rating comparisons are not available for this account.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <section class="ctr-match-panel" aria-labelledby="ctr-match-progress-heading" data-match-progress>
      <div class="ctr-match-heading">
        <h3 class="h5" id="ctr-match-progress-heading">Reconstructed CTR before/after match</h3>
        <p><strong>Reconstructed using scheduled match order.</strong> CTR estimates, not archived ratings or verified match completion times.</p>
      </div>
      <div class="ctr-match-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!isset($matchProgress) || $matchProgress['reason'] !== null): ?>
        <p class="small text-muted mb-0" role="status"><?php echo e($matchProgress['reason'] ?? 'Before/after reconstruction is awaiting a saved update.'); ?></p>
      <?php else: ?>
        <p class="small text-muted">Latest five eligible results · reconstruction updated through <?php echo e($matchProgress['as_of']); ?>. Each pair uses eligible results scheduled strictly earlier, then adds that match. Other matches at the same time and later results are excluded from both estimates.</p>
        <p class="small text-muted">Change means after minus before. These are not fixed points awarded for a win or loss, and changes must not be assumed to sum to a monthly movement.</p>
        <?php
          $matchProgressRows = array_reverse($matchProgress['rows']);
          $hasMatchProgressValues = collect($matchProgressRows)->contains(fn ($row) => $row['delta'] !== null);
        ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($matchProgressRows)): ?>
          <figure class="mb-3" data-match-progress-chart>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasMatchProgressValues): ?>
            <p class="ctr-match-scroll-cue small text-muted">Swipe or scroll across to see every match. Use “How calculated” for exact values and evidence.</p>
            <div class="ctr-chart-scroll" tabindex="0" aria-label="Before and after match chart; scroll horizontally on a narrow screen">
              <svg viewBox="0 0 620 420" width="100%" style="min-width:600px;max-width:60rem;display:block" role="group" aria-describedby="ctr-match-grid-description" aria-label="Reconstructed using scheduled match order: CTR before and after the latest five eligible matches, oldest to newest, on a fixed 0 to 100 scale">
                <title>Reconstructed using scheduled match order</title>
                <desc id="ctr-match-grid-description"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matchProgressRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $progress): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($progress['date']); ?> vs <?php echo e($progress['opponent']); ?>: <?php echo e($progress['won'] ? 'Won' : 'Lost'); ?> <?php echo e($progress['score']); ?>. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress['delta'] !== null): ?>Before <?php echo e(number_format($progress['before_score'], 1)); ?>, after <?php echo e(number_format($progress['after_score'], 1)); ?>, change <?php echo e(sprintf('%+.1f', $progress['delta'])); ?> CTR. <?php else: ?> Reconstruction unavailable: <?php echo e($progress['reason']); ?>. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></desc>
                <defs><marker id="ctr-progress-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="context-stroke" /></marker></defs>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [100 => 20, 50 => 110, 0 => 200]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick => $tickY): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <line x1="40" y1="<?php echo e($tickY); ?>" x2="600" y2="<?php echo e($tickY); ?>" stroke="#dbe4ef" /><text x="32" y="<?php echo e($tickY + 4); ?>" text-anchor="end" font-size="12" fill="#52677b"><?php echo e($tick); ?></text>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matchProgressRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chartIndex => $progress): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php
                    $beforeX = 60 + $chartIndex * 110;
                    $afterX = $beforeX + 60;
                  ?>
                  <g data-match-explanation-target="ctr-match-explanation-<?php echo e($chartIndex); ?>" style="cursor:pointer">
                  <rect x="<?php echo e($beforeX - 20); ?>" y="0" width="100" height="410" fill="transparent" />
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress['delta'] !== null): ?>
                    <?php
                      $beforeY = 200 - 1.8 * $progress['before_score'];
                      $afterY = 200 - 1.8 * $progress['after_score'];
                      $slopeColor = $progress['delta'] > 0 ? '#087f5b' : ($progress['delta'] < 0 ? '#c2410c' : '#004177');
                      $changeBackground = $progress['delta'] > 0 ? '#e7f5ed' : ($progress['delta'] < 0 ? '#fff0e5' : '#eef4fb');
                    ?>
                    <g role="img" data-match-progress-segment data-before="<?php echo e($progress['before_score']); ?>" data-after="<?php echo e($progress['after_score']); ?>" aria-label="<?php echo e($progress['date']); ?> vs <?php echo e($progress['opponent']); ?>, <?php echo e($progress['won'] ? 'won' : 'lost'); ?> <?php echo e($progress['score']); ?>: reconstructed before <?php echo e(number_format($progress['before_score'], 1)); ?>, after <?php echo e(number_format($progress['after_score'], 1)); ?>, change <?php echo e(sprintf('%+.1f', $progress['delta'])); ?>">
                      <title><?php echo e($progress['opponent']); ?> · reconstructed scheduled-order pair</title>
                      <line x1="<?php echo e($beforeX); ?>" y1="<?php echo e($beforeY); ?>" x2="<?php echo e($afterX); ?>" y2="<?php echo e($afterY); ?>" stroke="<?php echo e($slopeColor); ?>" stroke-width="4" marker-end="url(#ctr-progress-arrow)" />
                      <circle cx="<?php echo e($beforeX); ?>" cy="<?php echo e($beforeY); ?>" r="4" fill="<?php echo e($slopeColor); ?>" /><circle cx="<?php echo e($afterX); ?>" cy="<?php echo e($afterY); ?>" r="4" fill="<?php echo e($slopeColor); ?>" />
                      <text x="<?php echo e($beforeX); ?>" y="<?php echo e(max(12, $beforeY - 10)); ?>" text-anchor="middle" font-size="12" fill="#172e45"><?php echo e(number_format($progress['before_score'], 1)); ?></text>
                      <text x="<?php echo e($afterX); ?>" y="<?php echo e(max(12, $afterY - 10)); ?>" text-anchor="middle" font-size="12" fill="#172e45"><?php echo e(number_format($progress['after_score'], 1)); ?></text>
                      <rect x="<?php echo e($beforeX - 9); ?>" y="248" width="78" height="24" rx="10" fill="<?php echo e($changeBackground); ?>" aria-hidden="true" />
                      <text x="<?php echo e($beforeX + 30); ?>" y="265" text-anchor="middle" font-size="15" font-weight="700" fill="<?php echo e($slopeColor); ?>"><title><?php echo e(sprintf('%+.1f CTR', $progress['delta'])); ?><?php echo e((float) $progress['delta'] === 0.0 ? ' · No measurable change at this precision.' : ''); ?></title><?php echo e($progress['delta'] > 0 ? '↑' : ($progress['delta'] < 0 ? '↓' : '→')); ?> <?php echo e(sprintf('%+.1f', $progress['delta'])); ?></text>
                    </g>
                  <?php else: ?>
                    <text x="<?php echo e($beforeX + 30); ?>" y="110" text-anchor="middle" font-size="11" fill="#52677b"><title>Reconstruction unavailable: <?php echo e($progress['reason']); ?></title>Unavailable</text>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <text x="<?php echo e($beforeX); ?>" y="222" text-anchor="middle" font-size="11" fill="#172e45">Before</text><text x="<?php echo e($afterX); ?>" y="222" text-anchor="middle" font-size="11" fill="#172e45">After</text>
                  <text x="<?php echo e($beforeX + 30); ?>" y="242" text-anchor="middle" font-size="12" fill="#172e45"><?php echo e(\Carbon\CarbonImmutable::parse($progress['date'])->format('d M')); ?></text>
                  <foreignObject x="<?php echo e($beforeX - 20); ?>" y="282" width="100" height="130">
                    <div xmlns="http://www.w3.org/1999/xhtml" style="font:12px/16px sans-serif;color:#172e45;text-align:center;overflow-wrap:anywhere">
                      <div data-match-chart-opponent title="<?php echo e($progress['opponent']); ?>" style="font-weight:600;max-height:48px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical"><?php echo e($progress['opponent']); ?></div>
                      <div data-match-chart-result style="margin-top:4px"><?php echo e($progress['won'] ? 'Won' : 'Lost'); ?> · <?php echo e($progress['score']); ?></div>
                      <button type="button" class="btn btn-link p-0 mt-1" style="font-size:12px" data-match-explanation-target="ctr-match-explanation-<?php echo e($chartIndex); ?>" aria-controls="ctr-match-explanation-<?php echo e($chartIndex); ?>" aria-expanded="false" aria-label="How calculated: <?php echo e($progress['date']); ?> vs <?php echo e($progress['opponent']); ?>">How calculated</button>
                    </div>
                  </foreignObject>
                  </g>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </svg>
            </div>
            <figcaption class="small text-muted mt-1">Oldest to newest · fixed 0–100 CTR scale. Each arrow is one reconstructed before/after pair. Gaps between matches are not connected; changes between cutoffs are not attributed to a match.</figcaption>
            <?php else: ?>
              <p class="ctr-match-empty-note" role="status">Your match results are saved. Before/after CTR is unavailable for these matches; the reason for each is shown below.</p>
              <div class="ctr-match-list" data-match-progress-unavailable>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matchProgressRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chartIndex => $progress): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <article class="ctr-match-row">
                    <div><h4><?php echo e($progress['opponent']); ?></h4><p><?php echo e(\Carbon\CarbonImmutable::parse($progress['date'])->format('j M Y')); ?> · <?php echo e($progress['won'] ? 'Won' : 'Lost'); ?> <?php echo e($progress['score']); ?></p></div>
                    <p><?php echo e($progress['reason'] ?? 'A comparable before/after pair is unavailable.'); ?></p>
                    <button type="button" class="btn btn-outline-primary" data-match-explanation-target="ctr-match-explanation-<?php echo e($chartIndex); ?>" aria-controls="ctr-match-explanation-<?php echo e($chartIndex); ?>" aria-expanded="false" aria-label="How calculated: <?php echo e($progress['date']); ?> vs <?php echo e($progress['opponent']); ?>">View explanation</button>
                  </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matchProgressRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chartIndex => $progress): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <section id="ctr-match-explanation-<?php echo e($chartIndex); ?>" data-match-explanation hidden tabindex="-1" class="border rounded p-3 mt-3" aria-label="Match calculation explanation">
                <h6><?php echo e($progress['date']); ?> · <?php echo e($progress['opponent']); ?> · <?php echo e($progress['won'] ? 'Won' : 'Lost'); ?> <?php echo e($progress['score']); ?></h6>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-match-explanation-close>Close explanation</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress['delta'] !== null && isset($progress['explanation'])): ?>
                  <?php
                    $counts = $progress['explanation'];
                  ?>
                  <p>Reconstructed using scheduled match order at <?php echo e($progress['time']); ?> SAST. These are retrospective estimates, not archived ratings or verified completion times.</p>
                  <dl class="row small mb-2">
                    <dt class="col-7">Eligible network matches</dt><dd class="col-5"><?php echo e($counts['network_before']); ?> before → <?php echo e($counts['network_after']); ?> after</dd>
                    <dt class="col-7">Your eligible matches</dt><dd class="col-5"><?php echo e($counts['own_before']); ?> before → <?php echo e($counts['own_after']); ?> after</dd>
                    <dt class="col-7">Your distinct opponents</dt><dd class="col-5"><?php echo e($counts['opponents_before']); ?> before → <?php echo e($counts['opponents_after']); ?> after</dd>
                  </dl>
                  <p>Before: fit the canonical CTR model to eligible earlier evidence. After: add this match and refit on the same <?php echo e($progress['date']); ?> rating date. Other matches at the same time and later matches are excluded from both fits. Both fits must converge on the same comparison scale.</p>
                  <p>The model uses score margins, opponent strength, recency and per-event evidence weights. The refit recalculates event budgets; this is not a fixed points-per-match formula, and changes are not additive.</p>
                  <p class="mb-0"><strong><?php echo e(number_format($progress['after_score'], 1)); ?> after − <?php echo e(number_format($progress['before_score'], 1)); ?> before = <?php echo e(sprintf('%+.1f', $progress['delta'])); ?> CTR</strong></p>
                  <div class="table-responsive mt-3">
                    <table class="table table-sm small" style="min-width:740px">
                      <caption>Your match evidence used in this comparison. Opponent CTR values are reconstructed on this comparison's before/after cutoff, not at each listed result's date or today. Availability also follows current public comparison rules. Unavailable means permission or a stable comparable pair could not be established. Wider network matches are summarized by counts above. Scroll horizontally on a narrow screen.</caption>
                      <thead><tr><th scope="col">Date</th><th scope="col">Opponent</th><th scope="col">Your result</th><th scope="col">Opponent CTR before → after</th><th scope="col">Before inclusion</th><th scope="col">After inclusion</th></tr></thead>
                      <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $progress['used_matches'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $used): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <tr class="<?php echo e(!$used['before'] ? 'table-info' : ''); ?>">
                            <td class="text-nowrap"><?php echo e($used['date']); ?></td><td style="min-width:150px;overflow-wrap:anywhere"><?php echo e($used['opponent']); ?></td><td class="text-nowrap"><?php echo e($used['won'] ? 'Won' : 'Lost'); ?> <?php echo e($used['score']); ?><?php echo e(!$used['before'] ? ' · This match' : ''); ?></td>
                            <td class="text-nowrap"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($used['opponent_before'], $used['opponent_after'])): ?><?php echo e(number_format($used['opponent_before'], 1)); ?> → <?php echo e(number_format($used['opponent_after'], 1)); ?><?php else: ?> Unavailable <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <td class="text-nowrap"><?php echo e($used['before'] ? 'Included' : 'Not included'); ?></td><td class="text-nowrap"><?php echo e($used['after'] ? 'Included' : 'Not included'); ?></td>
                          </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($progress['hidden_matches'] ?? 0) > 0): ?><p class="small text-muted"><?php echo e($progress['hidden_matches']); ?> own match details are not shown because metadata is unavailable, access is restricted, or the display limit was reached. The evidence counts above include them.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                  <p class="mb-0"><?php echo e($progress['reason'] ?? 'Saved calculation details are awaiting the next nightly update.'); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </figure>
        <?php else: ?>
          <p class="small text-muted">No eligible included matches with available details.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($matchProgress['restricted']): ?><p class="small text-muted mt-2">Some match details are restricted and are not shown here.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="small text-muted mt-2 mb-0">A shared player scheduled in two matches at the same time, uncertain match dates or a changed comparison scale can make a pair unavailable. Current verified identity, publication and rating rules are used retrospectively.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="tab-pane <?php echo e($activeRatingSection === 'progress' ? 'active show' : ''); ?>" id="ctr-panel-progress" role="tabpanel" aria-labelledby="ctr-tab-progress" tabindex="0">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingHistory['reason']): ?>
    <p role="status" class="alert alert-warning mt-3"><?php echo e($ratingHistory['reason']); ?></p>
  <?php elseif(!$ratingHistory['series']): ?>
    <p role="status" class="mt-3">No eligible rating evidence was available in the last 12 months. Missing months are unrated, not zero.</p>
  <?php else: ?>
    <?php
      $points = $ratingHistory['points'];
      $latest = end($points) ?: null;
      $point = $ratingHistory['point'];
      $previous = $ratingHistory['previous'];
      $evidence = $ratingHistory['evidence'];
      $comparable = $point && $previous && $point['group'] === $previous['group'];
      $selectedIsLatest = $point && $latest && $point['index'] === $latest['index'] && $point['date'] === $latest['date'];
      $axis = $ratingHistory['axis'];
      $plotY = fn ($score) => 225 - (($score - $axis['min']) / ($axis['max'] - $axis['min'])) * 200;
      $query = ['month' => $ratingHistory['month'], 'scope' => $ratingHistory['scope'], 'scale' => request()->query('scale', $ratingHistory['scale']), 'rating_section' => 'history'] + $ratingBrowseFilters;
      if ($canBrowseRatings) {
          $query['rating_page'] = (int) request()->query('rating_page', 1);
      }
      $ratingUrl = fn ($changes = []) => route('my.tennis.rating', ['player' => $ratingPlayer->id] + array_replace($query, $changes));
    ?>
    <p class="small text-muted mt-3 mb-3">Saved through <?php echo e($ratingHistory['as_of']); ?>.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingHistory['stale']): ?> Update pending.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$points): ?><p role="status" class="alert alert-info">No comparable shared rating is available for this player. Suitable recent singles results must connect the player to the reference group.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($point['historical_connection'])): ?><p role="status" class="alert alert-warning"><strong>Low confidence — historical connection.</strong> The selected estimate needs an older match link dated <?php echo e($point['connection_reference_date'] ?? 'unknown'); ?> (<?php echo e($point['connection_age_days'] ?? '?'); ?> days old), even if this player has recent results.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($point['connection_status'] ?? '') === 'disconnected'): ?><p role="status" class="alert alert-warning"><strong>Provisional rating · low confidence.</strong> This estimate is based on <?php echo e(($point['evidence_basis'] ?? null) === 'finish_only' ? 'published final placings only' : 'this player’s connected evidence group'); ?>. It is not yet calibrated against other comparison groups; the same number in another group does not establish equal ability.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="ctr-heading mb-2"><h3 class="h5 mb-0"><?php echo e(ucfirst($ratingHistory['cohort'])); ?> progress</h3><span class="small text-muted">CTR out of 100</span></div>
    <div class="ctr-history-chart">
    <p class="ctr-chart-hint small text-muted mb-2">Scroll the chart sideways to see all months.</p>
    <div class="ctr-chart-scroll" role="region" aria-label="Rating chart; scroll horizontally on small screens" tabindex="0">
      <svg viewBox="0 0 660 270" role="group" aria-label="<?php echo e(ucfirst($ratingHistory['cohort'])); ?> monthly rating history; axis <?php echo e($axis['min']); ?> to <?php echo e($axis['max']); ?>" style="width:100%;min-width:660px;max-height:360px">
        <title><?php echo e(ucfirst($ratingHistory['cohort'])); ?> monthly rating history</title>
        <desc>Choose a point to view the saved personal results included in that month. Solid lines retain the same comparison group; dashed lines show changed groups.</desc>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $axis['ticks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><line x1="45" x2="635" y1="<?php echo e($plotY($tick)); ?>" y2="<?php echo e($plotY($tick)); ?>" stroke="#d9e2e6"/><text x="35" y="<?php echo e($plotY($tick) + 4); ?>" text-anchor="end" font-size="12" fill="#334e60"><?php echo e($tick); ?></text><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $points; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chartPoint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($before = $points[$loop->index - 1] ?? null); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($before && $before['index'] + 1 === $chartPoint['index']): ?>
            <line x1="<?php echo e(45 + $before['index'] * 53); ?>" y1="<?php echo e($plotY($before['score'])); ?>" x2="<?php echo e(45 + $chartPoint['index'] * 53); ?>" y2="<?php echo e($plotY($chartPoint['score'])); ?>" stroke="#004177" stroke-width="3" <?php if($before['group'] !== $chartPoint['group']): ?> stroke-dasharray="6 5" <?php endif; ?>><title><?php echo e($before['group'] === $chartPoint['group'] ? 'Same comparison group' : 'Comparison group or reference changed; monthly change is not directly comparable'); ?></title></line>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <a href="<?php echo e($ratingUrl(['month' => $chartPoint['index']])); ?>" data-rating-link aria-label="Explore <?php echo e($chartPoint['date']); ?> rating <?php echo e(number_format($chartPoint['score'], 1)); ?>"><circle cx="<?php echo e(45 + $chartPoint['index'] * 53); ?>" cy="<?php echo e($plotY($chartPoint['score'])); ?>" r="22" fill="transparent" aria-hidden="true"/><circle cx="<?php echo e(45 + $chartPoint['index'] * 53); ?>" cy="<?php echo e($plotY($chartPoint['score'])); ?>" r="<?php echo e($chartPoint['index'] === $ratingHistory['month'] ? 7 : 5); ?>" fill="<?php echo e($chartPoint['index'] === $ratingHistory['month'] ? '#b85b00' : '#004177'); ?>"><title><?php echo e($chartPoint['date']); ?>: <?php echo e(number_format($chartPoint['score'], 1)); ?> / 100</title></circle></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ratingHistory['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><text x="<?php echo e(45 + $index * 53); ?>" y="250" text-anchor="middle" font-size="11" fill="#334e60"><?php echo e(\Carbon\CarbonImmutable::parse($month['date'])->format('M')); ?></text><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </svg>
    </div>
    </div>
    <div class="ctr-legend mt-2"><span><i class="ctr-line-key" aria-hidden="true"></i>Same comparison group</span><span><i class="ctr-line-key ctr-line-key-dashed" aria-hidden="true"></i>Group or reference changed</span><span>Missing months remain gaps</span></div>
    <p class="small text-muted mt-2 mb-0">Dashed lines mark changes in comparison group or reference. Compare changes within solid sections.</p>
    <form method="get" action="<?php echo e(route('my.tennis.rating', $ratingPlayer)); ?>" data-rating-filters class="ctr-filters mt-3">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ratingBrowseFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($filter); ?>" value="<?php echo e($value); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canBrowseRatings): ?><input type="hidden" name="rating_page" value="<?php echo e($query['rating_page']); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <input type="hidden" name="rating_section" value="history">
      <div><span class="small text-muted d-block">Current rating group</span><strong data-current-rating-group><?php echo e($ratingHistory['current_group_label'] ?? 'Not rated yet'); ?></strong></div>
      <div><label for="ctr-month">Explore a month</label><select class="form-select" name="month" id="ctr-month">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ratingHistory['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($index); ?>" <?php if($ratingHistory['month'] === $index): echo 'selected'; endif; ?>><?php echo e(\Carbon\CarbonImmutable::parse($month['date'])->format('M Y')); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select></div>
      <input type="hidden" name="scope" value="<?php echo e($ratingHistory['scope']); ?>">
      <input type="hidden" name="scale" value="<?php echo e($query['scale']); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment): ?><noscript><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button class="btn btn-primary" type="submit">Show selection</button>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment): ?></noscript><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>
    <p class="small text-muted mt-3" data-rating-progress-explanation>Your CTR is recalculated from eligible results, including score margins, opponents’ playing strength and how recent the evidence is. There is no fixed number of CTR points for a win or loss. Compare monthly changes within the same comparison group.</p>
    <div class="ctr-stats">
      <div class="ctr-stat" data-selected-month-summary><h3>Selected month · <?php echo e(\Carbon\CarbonImmutable::parse($ratingHistory['months'][$ratingHistory['month']]['date'])->format('M Y')); ?></h3>
        <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedIsLatest): ?><p class="mb-0">Latest saved month</p><?php elseif($point): ?><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['stage' => $ratingHistory['ball_stage'] ?? null,'overall' => empty($ratingHistory['ball_stage']),'score' => $point['score'],'local' => ($point['connection_status'] ?? '') === 'disconnected']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratingHistory['ball_stage'] ?? null),'overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(empty($ratingHistory['ball_stage'])),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($point['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($point['connection_status'] ?? '') === 'disconnected')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?><?php else: ?><span class="ctr-stat-value">Unrated</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($point['evidence_basis'] ?? null) === 'finish_only'): ?><p class="small mt-2 mb-0">Low confidence · final placings only</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comparable): ?><p class="mt-2 mb-0"><strong><?php echo e(sprintf('%+.1f', $point['score'] - $previous['score'])); ?></strong> from the previous month · same comparison group</p>
        <?php elseif($point && $previous): ?><p class="small text-muted mt-2 mb-0">Comparison group changed. No like-for-like monthly change.</p>
        <?php else: ?><p class="small text-muted mt-2 mb-0">No comparable previous-month estimate.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <div class="ctr-stat"><h3>Singles results included</h3><div class="ctr-stat-value"><?php echo e($evidence['matches']); ?> <?php echo e(\Illuminate\Support\Str::plural('match', $evidence['matches'])); ?></div><p class="mt-2 mb-0"><?php echo e($evidence['wins']); ?> won · <?php echo e($evidence['losses']); ?> lost</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['matches'] < 5): ?><p class="small text-muted mt-1 mb-0">Small sample. Use this estimate with care.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['finishes']): ?> Also includes finishing-order evidence.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($point): ?>
      <details class="small mb-3">
        <summary>Rating evidence <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($point['confidence_label'] ?? null) === 'Low'): ?> · Low confidence <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></summary>
        <p class="mb-1"><strong>Evidence confidence: <?php echo e($point['confidence_label'] ?? ((!empty($point['historical_connection']) || ($point['connection_status'] ?? '') === 'disconnected') ? 'Low' : 'Not recorded')); ?></strong> · as of <?php echo e($point['date']); ?><br><span class="text-muted">Strength and freshness of the supporting results, not an accuracy percentage.</span></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(array_key_exists('last_direct_match', $point)): ?>
          <p class="mb-1">Last included singles result: <?php echo e($point['last_direct_match'] ? \Carbon\CarbonImmutable::parse($point['last_direct_match'])->format('j M Y') : 'None recorded'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($point['recent_played'])): ?> · <?php echo e($point['recent_played']); ?> <?php echo e(\Illuminate\Support\Str::plural('match', $point['recent_played'])); ?> in the 90 days before this estimate <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($point['direct_opponents'], $point['played_events'])): ?><p class="mb-1 text-muted">Included results cover <?php echo e($point['direct_opponents']); ?> distinct <?php echo e(\Illuminate\Support\Str::plural('opponent', $point['direct_opponents'])); ?> across <?php echo e($point['played_events']); ?> <?php echo e(\Illuminate\Support\Str::plural('event', $point['played_events'])); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($point['proxy_dated_matches'])): ?><p class="mb-1 text-muted">Some result dates use the event date where a match date was unavailable.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
          <p class="mb-1 text-muted">Detailed confidence and freshness were not recorded in this saved calculation. They will be included in the next nightly update.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details class="mt-3"><summary>Monthly rating values</summary><div class="table-responsive mt-2"><table class="table table-sm" style="white-space:nowrap"><thead><tr><th>Date</th><th>CTR / 100</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->hasRole('super-user')): ?><th>Monthly change</th><th>Comparison</th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead><tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $points; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tablePoint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($before = $points[$loop->index - 1] ?? null); ?>
        <?php ($same = $before && $before['index'] + 1 === $tablePoint['index'] && $before['group'] === $tablePoint['group']); ?>
        <tr><td><a href="<?php echo e($ratingUrl(['month' => $tablePoint['index']])); ?>" data-rating-link><?php echo e($tablePoint['date']); ?></a></td><td><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'small','score' => $tablePoint['score'],'local' => ($tablePoint['connection_status'] ?? '') === 'disconnected']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'small','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tablePoint['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($tablePoint['connection_status'] ?? '') === 'disconnected')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->hasRole('super-user')): ?><td><?php echo e($same ? sprintf('%+.1f', $tablePoint['score'] - $before['score']) : '—'); ?></td><td><?php echo e(!$before ? 'First estimate' : ($same ? 'Same group' : 'Group changed or gap')); ?></td><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody></table></div></details>
    <section class="mt-4" aria-labelledby="ctr-results-heading">
      <div class="ctr-heading"><h3 id="ctr-results-heading" class="h5 mb-0">Results used in this estimate</h3><span class="small text-muted"><?php echo e($evidence['included']); ?> records</span></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['restricted'] ?? 0): ?>
          <p class="small text-muted"><?php echo e($evidence['restricted']); ?> TeamSheet match details restricted to administrators. Their contributions remain included in your rating and match totals.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Evidence scope">
        <a class="btn btn-sm <?php echo e($ratingHistory['scope'] === 'new' ? 'btn-primary' : 'btn-outline-primary'); ?>" href="<?php echo e($ratingUrl(['scope' => 'new'])); ?>" data-rating-link <?php if($ratingHistory['scope'] === 'new'): ?> aria-current="true" <?php endif; ?>><?php echo e($ratingHistory['month'] === 0 ? 'Included at chart start' : 'Added since previous month'); ?> (<?php echo e($evidence['new']); ?>)</a>
        <a class="btn btn-sm <?php echo e($ratingHistory['scope'] === 'all' ? 'btn-primary' : 'btn-outline-primary'); ?>" href="<?php echo e($ratingUrl(['scope' => 'all'])); ?>" data-rating-link <?php if($ratingHistory['scope'] === 'all'): ?> aria-current="true" <?php endif; ?>>All included (<?php echo e($evidence['included']); ?>)</a>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$point): ?><p role="status">No rating was available for this category in the selected month.</p>
      <?php elseif(!$evidence['rows']): ?><p role="status"><?php echo e($ratingHistory['scope'] === 'new' ? 'No new results since the previous month.' : 'No direct singles results or retained finishing-order evidence were recorded for this player in this snapshot.'); ?></p>
      <?php else: ?>
        <p class="small text-muted">Showing <?php echo e(($evidence['page'] - 1) * 20 + 1); ?>–<?php echo e(min($evidence['page'] * 20, $evidence['total'])); ?> of <?php echo e($evidence['total']); ?> <?php echo e($ratingHistory['scope'] === 'all' ? 'included' : 'newly included'); ?> records.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $evidence['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <article class="ctr-result">
            <div class="ctr-result-top"><time datetime="<?php echo e($result['date']); ?>" class="small text-muted"><?php echo e(\Carbon\CarbonImmutable::parse($result['date'])->format('d M Y')); ?></time><span class="badge <?php echo e($result['kind'] === 'match' ? ($result['won'] ? 'bg-label-success' : 'bg-label-secondary') : 'bg-label-info'); ?>"><?php echo e($result['kind'] === 'match' ? ($result['won'] ? 'Won' : 'Lost') : 'Finishing order'); ?></span></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($result['kind'] === 'match'): ?><h4>vs <?php echo e($result['opponent']); ?> <strong class="ms-2"><?php echo e($result['score']); ?></strong></h4>
            <?php else: ?><h4>Model order #<?php echo e($result['position']); ?> of <?php echo e($result['field_size']); ?></h4><p class="small text-muted mb-1">Finishing-order evidence; this model order may combine A/B divisions and is not an official finishing position.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <p class="mb-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_starts_with($result['source_id'], 'teamsheet:')): ?>
                    <?php echo e($result['event']); ?> · TeamSheet result
                <?php else: ?>
                    <a href="<?php echo e(route('event.tab.results', ['id' => $result['event_id']])); ?>"><?php echo e($result['event']); ?></a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
            <p class="small text-muted mb-0"><?php echo e($result['role']); ?> · <?php echo e($result['date_basis']); ?> · <?php echo e(str_starts_with($result['source_id'], 'teamsheet:') ? 'match date' : 'event end date'); ?> <?php echo e($result['completed_at']); ?></p>
          </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['pages'] > 1): ?><nav class="d-flex justify-content-between gap-2 mt-3" aria-label="Rating evidence pages">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['page'] > 1): ?><a class="btn btn-outline-primary" href="<?php echo e($ratingUrl(['page' => $evidence['page'] - 1])); ?>" data-rating-link>Previous results</a><?php else: ?><span></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <span class="align-self-center small text-muted"><?php echo e($evidence['page']); ?> / <?php echo e($evidence['pages']); ?></span>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['page'] < $evidence['pages']): ?><a class="btn btn-outline-primary" href="<?php echo e($ratingUrl(['page' => $evidence['page'] + 1])); ?>" data-rating-link>More results</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </nav><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="tab-pane <?php echo e($activeRatingSection === 'understand' ? 'active show' : ''); ?>" id="ctr-panel-understand" role="tabpanel" aria-labelledby="ctr-tab-understand" tabindex="0">
    <h3 class="h5">What does my rating mean?</h3>
    <?php if (isset($component)) { $__componentOriginalf900df063c5677d5e8b51f684b5153a9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf900df063c5677d5e8b51f684b5153a9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rating-explanation','data' => ['class' => 'mt-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rating-explanation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf900df063c5677d5e8b51f684b5153a9)): ?>
<?php $attributes = $__attributesOriginalf900df063c5677d5e8b51f684b5153a9; ?>
<?php unset($__attributesOriginalf900df063c5677d5e8b51f684b5153a9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf900df063c5677d5e8b51f684b5153a9)): ?>
<?php $component = $__componentOriginalf900df063c5677d5e8b51f684b5153a9; ?>
<?php unset($__componentOriginalf900df063c5677d5e8b51f684b5153a9); ?>
<?php endif; ?>
    <details class="ctr-method"><summary>How to read CTR and its evidence</summary><div class="small text-muted mt-2">
      <p>CTR is a provisional ability estimate, not an official ranking or an accuracy percentage. The all-age singles rating uses played results across ages; compare players connected to the same shared reference group. Solid lines retain the same comparison group; dashed lines join consecutive snapshots whose group or baseline changed. Those changes are not like-for-like performance gains or losses.</p>
      <p>Ratings update nightly at 03:02 SAST. Opponents’ results and the age of your matches affect your estimate. Completed matches can count during an event. Finishing positions, where eligible, are considered after the event ends.</p>
      <p>Each point uses eligible results dated on or before that day, including completed matches from ongoing events. Scored singles matches carry more weight than published final placings. Placings never count as played matches. Results cover up to two years, with older matches losing weight. Green/Yellow CTR, Red and Orange are calculated separately, without cross-pool fallback. Connections older than one year have Low confidence. Disconnected groups receive provisional scores that cannot be compared across groups. Estimates use the publication and identity records available now, rather than ratings originally published at the time.</p>
    </div></details>
  </div>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->hasRole('super-user')): ?>
    <details class="mt-3" data-rating-admin-tools data-super-admin-only>
      <summary>Admin tools</summary>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3" data-history-refresh-controls data-refresh-url="<?php echo e(route('my.tennis.rating.refresh')); ?>" data-status-url="<?php echo e(route('my.tennis.rating.refresh-status')); ?>">
      <button class="btn btn-outline-primary" type="button" data-history-refresh>Update saved ratings</button>
      <span class="small text-muted" role="status" data-history-refresh-status>Recalculates saved rating history for all players in the background.</span>
      <span class="small text-muted w-100" data-history-last-updated>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingHistory['built_at'] ?? null): ?>
          Latest saved update for this player: <time datetime="<?php echo e($ratingHistory['built_at']); ?>"><?php echo e(\Carbon\CarbonImmutable::parse($ratingHistory['built_at'])->setTimezone('Africa/Johannesburg')->format('j M Y, H:i')); ?> SAST</time>
        <?php else: ?>
          No saved update for this player yet.
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </span>
    </div>
    </details>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\rating.blade.php ENDPATH**/ ?>