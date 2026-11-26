<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingTopTen['visible'] ?? false): ?>
<?php
  $canBrowse = $ratingTopTen['can_browse'] ?? false;
  $ageLabel = $ratingTopTen['age_options'][$ratingTopTen['selected_age']] ?? 'Age group unavailable';
  $genderLabel = \App\Services\Performance\PlayerRatingProfileDirectory::GENDERS[$ratingTopTen['filters']['rating_gender']] ?? ($ratingTopTen['filters']['rating_gender'] === '' ? 'All genders' : 'Gender unavailable');
  $positionLabels = $canBrowse
      ? ['age' => 'CTR · '.$ageLabel.' · '.$genderLabel, 'overall' => 'CTR · all ages · '.$genderLabel]
      : ['age' => 'CTR · '.$ageLabel.' · '.$genderLabel];
  $hasRatingPosition = !empty($ratingTopTen['summary']['age']['player']) || ($canBrowse && !empty($ratingTopTen['summary']['overall']['player']));
?>
<section class="mt-4" aria-labelledby="player-top-ten-heading" data-player-rating-top-ten>
  <h3 class="h5" id="player-top-ten-heading"><?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['class' => 'me-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'me-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>Your rating positions</h3>
  <style>
    .ctr-top-lists { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem; }
    .ctr-top-list { min-width:0;border:1px solid #dbe4ef;border-radius:14px;padding:1rem;background:#fff; }
    .ctr-position-card { background:#f1f5fa; }
    .ctr-position-card h4 { color:#172e45;margin-bottom:.5rem; }
    .ctr-position-value { font-size:1.5rem;line-height:1.2;color:#004177;font-variant-numeric:tabular-nums; }
    .ctr-member-leaderboard { padding:clamp(.75rem,2vw,1.25rem); }
    .ctr-member-leaderboard li[aria-current="true"] { background:#eef4fb;box-shadow:inset 3px 0 #004177; }
    @media(max-width:575.98px) { .ctr-top-lists { grid-template-columns:1fr; } }
  </style>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasRatingPosition): ?>
  <div class="<?php echo e($canBrowse ? 'ctr-top-lists ' : ''); ?>mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $positionLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scope => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $summary = $ratingTopTen['summary'][$scope] ?? null;
      ?>
      <section class="ctr-top-list ctr-position-card">
        <h4 class="h6"><?php echo e($label); ?></h4>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($summary && $summary['player']): ?>
          <div class="d-flex align-items-center flex-wrap gap-2">
            <p class="mb-0"><span class="visually-hidden">Rating position </span><strong class="ctr-position-value">#<?php echo e($summary['player']['position']); ?></strong> <span class="small text-muted">of <?php echo e($summary['total']); ?></span></p>
            <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'inline','score' => $summary['player']['score'],'overall' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'inline','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($summary['player']['score']),'overall' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($summary['player']['confidence'])): ?><p class="small text-muted mt-1 mb-0" data-rating-position-confidence><?php echo e($summary['player']['confidence']); ?> confidence</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
          <p class="small mb-0">Position unavailable.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php else: ?>
    <p class="small text-muted mb-3">No comparable position yet.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingTopTen['as_of']): ?><p class="small text-muted">Saved through <?php echo e($ratingTopTen['as_of']); ?>.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingTopTen['stale']): ?> Latest saved estimates; an update is pending.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$canBrowse): ?>
    <section class="ctr-top-list ctr-member-leaderboard" aria-labelledby="ctr-age-top-ten-heading">
    <h4 class="h6" id="ctr-age-top-ten-heading">Your age-group Top 10</h4>
    <p class="small text-muted"><?php echo e($positionLabels['age']); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingTopTen['selected_age'] === 'unknown'): ?>
      <p class="small text-muted">A valid birth date is needed to show your player's age-group Top 10.</p>
    <?php else: ?>
      <ol class="list-group list-group-flush mb-0" aria-label="Your age-group Top 10 ratings" data-plus-age-top-ten>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ratingTopTen['lists']['overall']['players'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <li class="list-group-item d-flex align-items-start gap-2" <?php if($member['id'] === (int) $ratingPlayer->id): ?> aria-current="true" <?php endif; ?>>
            <span><?php echo e($member['position']); ?>.</span><span class="flex-grow-1 text-break"><?php echo e($member['name']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member['id'] === (int) $ratingPlayer->id): ?><small class="d-block text-muted">Your selected player</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><small class="d-block text-muted" data-rating-confidence><?php echo e($member['confidence']); ?> confidence</small></span><span class="d-inline-flex flex-column align-items-center" style="width:4rem;flex-shrink:0"><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'inline','score' => $member['score'],'overall' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'inline','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($member['score']),'overall' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></span>
          </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <li class="list-group-item text-muted"><span role="status"><?php echo e($ratingTopTen['reason'] ? 'Rating comparisons unavailable.' : 'No comparable published ratings for this age group yet.'); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingTopTen['reason']): ?><small class="d-block"><?php echo e($ratingTopTen['reason']); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ol>
      <p class="small text-muted mt-2 mb-0">Confidence describes the strength and freshness of recorded evidence, not a guarantee of rating accuracy.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
  <?php else: ?>
  <h4 class="h6">Explore ratings · Full list</h4>
  <form method="get" action="<?php echo e(route('my.tennis.rating', $ratingPlayer)); ?>" data-rating-filters class="ctr-filters mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['month', 'scope', 'scale']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $historyFilter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($historyFilter); ?>" value="<?php echo e($ratingHistory[$historyFilter] ?? ''); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div data-rating-age-filter <?php if($ratingTopTen['filters']['rating_level'] !== 'overall'): ?> hidden <?php endif; ?>><label for="ctr-rating-age">Overall age group</label><select id="ctr-rating-age" name="rating_age" class="form-select"><option value="">All ages</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ratingTopTen['age_options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if($ratingTopTen['filters']['rating_age'] === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div><label for="ctr-rating-gender">Gender</label><select id="ctr-rating-gender" name="rating_gender" class="form-select"><option value="">All genders</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Performance\PlayerRatingProfileDirectory::GENDERS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if($ratingTopTen['filters']['rating_gender'] === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div><label for="ctr-rating-level">Rating level</label><select id="ctr-rating-level" name="rating_level" class="form-select"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['red' => 'Red', 'orange' => 'Orange', 'overall' => 'CTR']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if($ratingTopTen['filters']['rating_level'] === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <div><label for="ctr-rating-per-page">Players per page</label><select id="ctr-rating-per-page" name="rating_per_page" class="form-select"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Performance\PlayerRatingTopTen::PAGE_SIZES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($size); ?>" <?php if(($ratingTopTen['filters']['rating_per_page'] ?? \App\Services\Performance\PlayerRatingTopTen::PER_PAGE) === $size): echo 'selected'; endif; ?>><?php echo e($size); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment ?? true): ?><noscript><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <button type="submit" class="btn btn-primary">Show ratings</button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingFragment ?? true): ?></noscript><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </form>
  <style>
    .ctr-full-list li { padding:.7rem 0;border-top:1px solid #edf1ef;overflow-wrap:anywhere; }
    .ctr-full-list .ctr-player-row { display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.5rem;align-items:center; }
    .ctr-full-list .ctr-top-score { font-variant-numeric:tabular-nums;color:#004177; }
    .ctr-full-list small { display:block;color:#62766e; }
  </style>
  <div class="ctr-full-list">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ratingTopTen['lists']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $list): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <section class="ctr-top-list" aria-label="<?php echo e($list['label']); ?> ratings" data-top-ten-cohort="<?php echo e($key); ?>">
        <h4 class="h6" tabindex="-1" data-rating-list-focus>Rating positions</h4>
        <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Displayed rating group">
          <span class="badge rounded-pill bg-success">Rating: <?php echo e($list['label']); ?></span>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'overall'): ?><span class="badge rounded-pill bg-light text-dark border text-wrap text-start">Age group: <?php echo e($ratingTopTen['age_options'][$ratingTopTen['filters']['rating_age']] ?? 'All ages'); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <span class="badge rounded-pill bg-light text-dark border"><?php echo e(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS[$ratingTopTen['filters']['rating_gender']] ?? 'All genders'); ?></span>
        </div>
        <ol class="list-unstyled mb-0">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $list['players']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <li class="ctr-player-row" value="<?php echo e($member['position']); ?>" <?php if($member['id'] === (int) $ratingPlayer->id): ?> aria-current="true" <?php endif; ?>><span aria-hidden="true"><?php echo e($member['position']); ?></span><span><?php echo e($member['name']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member['id'] === (int) $ratingPlayer->id): ?><small>Your selected player</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><small><?php echo e($member['confidence']); ?> confidence</small></span><span class="ctr-top-score d-inline-flex flex-column align-items-center" style="width:4rem"><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'inline','score' => $member['score'],'overall' => $key === 'overall','stage' => $key === 'overall' ? null : $key]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'inline','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($member['score']),'overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($key === 'overall'),'stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($key === 'overall' ? null : $key)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></span></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <li class="small text-muted"><span role="status"><?php echo e($ratingTopTen['reason'] ? 'Rating comparisons unavailable.' : 'No ratings for these filters.'); ?></span></li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ol>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($list['pagination']) && $list['pagination']['total'] > 0): ?>
          <?php
            $pagination = $list['pagination'];
            $pageQuery = array_intersect_key($ratingHistory, array_flip(['month', 'scope', 'scale']));
            if (isset($ratingHistory['evidence']['page'])) $pageQuery['page'] = $ratingHistory['evidence']['page'];
            $pageQuery += $ratingTopTen['filters'];
            $pageUrl = fn ($page) => route('my.tennis.rating', ['player' => $ratingPlayer->id] + $pageQuery + ['rating_page' => $page]);
          ?>
          <p class="small text-muted mt-3">Showing <?php echo e(($pagination['page'] - 1) * $pagination['per_page'] + 1); ?>–<?php echo e(min($pagination['page'] * $pagination['per_page'], $pagination['total'])); ?> of <?php echo e($pagination['total']); ?> players</p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pagination['pages'] > 1): ?>
            <nav class="d-flex flex-wrap justify-content-between align-items-center gap-2" aria-label="Rating list pages">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pagination['page'] > 1): ?><a id="ctr-rating-previous" class="btn btn-outline-primary" href="<?php echo e($pageUrl($pagination['page'] - 1)); ?>" data-rating-link>Previous players</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <span class="small text-muted">Page <?php echo e($pagination['page']); ?> of <?php echo e($pagination['pages']); ?></span>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pagination['page'] < $pagination['pages']): ?><a id="ctr-rating-next" class="btn btn-outline-primary" href="<?php echo e($pageUrl($pagination['page'] + 1)); ?>" data-rating-link>Next players</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </nav>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <details class="small mt-3">
    <summary>Who’s included?</summary>
    <p class="mt-2 mb-1">Positions compare players who have a comparable CTR and are publicly listed as an event participant. They are separate from official rankings. Junior age groups include younger players, based on the age they reach this calendar year.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingTopTen['can_browse'] ?? false): ?>
      <p class="mb-0">Red and Orange have separate ratings and include all ages. CTR uses Green/Yellow results only. Filters change positions without changing rating scores. Players with equal displayed scores share a position.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </details>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\rating-top-ten.blade.php ENDPATH**/ ?>