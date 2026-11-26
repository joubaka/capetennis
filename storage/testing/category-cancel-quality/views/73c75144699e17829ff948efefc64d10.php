<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', $event ? 'Event player ratings' : 'Site-wide player ratings'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.player-performance.partials.ctr-directory-style', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="operational-page">
<style>
    .ratings-page .ratings-explanation summary, .ratings-page .rating-evidence summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; gap: .5rem; }
    .ratings-page summary::before { content: '+'; font-weight: 600; }
    .ratings-page details[open] > summary::before { content: '−'; }
    .ratings-page summary:focus-visible, .ratings-page a:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
    .ratings-page h2, .ratings-page h3 { overflow-wrap: anywhere; }
    .ratings-page .ctr-hero h2 { color: #fff !important; margin: 0 0 .35rem; }
    .ratings-page .ctr-hero summary { color: #fff; }
    .ratings-page .rating-evidence { display: inline-block; max-width: 100%; }
    .ratings-page .rating-evidence summary { display: inline-flex; width: fit-content; padding: .15rem .45rem; }
    .ratings-page .rating-evidence[open] { display: block; }
    .ratings-page .rating-evidence p { margin: .35rem 0; }
    .ratings-page .ctr-score { display: flex; flex-wrap: wrap; align-items: center; gap: .15rem; }
    .ratings-page .ratings-status { font-size: .875rem; }
    .ratings-page .ratings-filters button { min-height: 44px; width: 100%; }
    .ratings-page .ratings-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: start; gap: 1rem; }
    .ratings-page .ratings-tools { display: flex; flex-wrap: wrap; gap: .5rem; }
    .ratings-page .ratings-tools a { min-height: 44px; display: inline-flex; align-items: center; }
    .ratings-page .ratings-filters { margin: 0 0 1rem; }
    .ratings-page .ratings-filters-event { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .ratings-page .ratings-filters > div { width: auto; padding: 0; }
    @media (min-width: 576px) and (max-width: 991.98px) {
        .ratings-page .ratings-filters-event { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ratings-page .ratings-filters-event .ratings-submit { grid-column: 1/-1; }
    }
    @media (max-width: 575.98px) {
        .ratings-page .ratings-filters { grid-template-columns: minmax(0, 1fr); }
        .ratings-page .ctr-ranking-row { grid-template-columns: 1.5rem minmax(0, 1fr) 4.5rem; }
        .ratings-page .ctr-player { grid-column: 2; grid-row: 1; }
        .ratings-page .ctr-score { grid-column: 3; grid-row: 1; }
        .ratings-page .ctr-evidence { grid-column: 2/-1; grid-row: 2; }
    }
</style>
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?>
    <?php echo $__env->make('backend.event.partials.header', ['event' => $event, 'eventWorkspaceActive' => 'ratings', 'eventWorkspaceShowHome' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="ctr-directory ratings-page">
    <div class="ctr-hero ratings-heading">
        <div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?><h2 class="h3">Player ratings</h2><?php else: ?><h1 class="h3">Site-wide player ratings</h1><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <p>Private provisional singles ratings.</p>
        </div>
        <details class="ratings-explanation small">
            <summary>Review tools</summary>
            <div class="ratings-tools">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.player-performance.identity-review', $event)); ?>">Review historical identity evidence</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a class="btn btn-outline-primary" href="<?php echo e(route('backend.player-performance.masters-review')); ?>">Review Masters qualification</a>
            </div>
        </details>
    </div>
    <div class="ctr-content">
    <p class="ctr-intro mb-2">Red CTR, Orange CTR and CTR (Green/Yellow) are separate rating pools. Compare players only within the same ball pool and comparison group. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?>The all-pools view shows separate lists, never a combined score.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
    <details class="ratings-explanation small text-muted mb-2">
        <summary>How to read these ratings</summary>
        <p>Cohorts follow age order. Players are grouped by comparison group within each exact cohort, highest rating to lowest within each group, with unrated players last. Positions refer to each comparison group. Separate groups cannot be compared directly. Ratings are calculated from published evidence; they cannot be edited here.</p>
        <p>The score out of 100 represents relative ability within a connected comparison group, not a win percentage. Confidence describes the strength and freshness of the evidence, not rating accuracy. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unified ?? false): ?>Age categories filter membership; they do not recalculate the shared rating. Disconnected players receive a provisional within-group score with low confidence until suitable played evidence calibrates them against other groups.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
        <p class="mb-2">Includes recorded individual entries (including unpaid and withdrawn entries) and saved team rosters. Unrated members remain visible. A player entered in multiple cohorts appears once in each.</p>
    </details>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshot['reason']): ?>
        <p class="text-muted ratings-status mb-2">Ratings are currently unavailable. Recorded players remain visible below.</p>
    <?php else: ?>
        <p class="text-muted ratings-status mb-2">Last successful calculation: <?php echo e($snapshot['built_at']); ?> · evidence as of <?php echo e($snapshot['snapshot_as_of'] ?? 'unknown'); ?>.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refreshStatus['pending'] || $refreshStatus['failed']): ?>
        <div class="alert alert-info py-2 mb-3" role="status">A rating update is pending. This page shows the latest available saved ratings.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$refreshStatus['pending'] && !$refreshStatus['failed'] && $snapshot['reason']): ?><div class="alert alert-warning" role="status"><?php echo e($snapshot['reason']); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($limitReason): ?><div class="alert alert-warning" role="status"><?php echo e($limitReason); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="get" class="ctr-filters ratings-filters <?php echo e($event ? 'ratings-filters-event' : ''); ?>">
        <div class="<?php echo e($event ? 'col-md-4' : 'col-md-6'); ?>"><label for="cohort" class="form-label"><?php echo e($event ? 'Division' : 'Exact age / gender / playing context'); ?></label>
            <select name="cohort" id="cohort" class="form-select" style="min-height:44px">
                <option value=""><?php echo e($event ? 'All event divisions' : 'Choose a cohort'); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cohorts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cohort): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($cohort); ?>" <?php if($selected === $cohort): echo 'selected'; endif; ?>><?php echo e($cohort); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?>
        <div><label for="group_by" class="form-label">Group by</label>
            <select name="group_by" id="group_by" class="form-select" style="min-height:44px">
                <option value="division" <?php if($groupBy === 'division'): echo 'selected'; endif; ?>>Division</option>
                <option value="age" <?php if($groupBy === 'age'): echo 'selected'; endif; ?>>Player age</option>
            </select>
        </div>
        <div class="col-md-3"><label for="ball_type" class="form-label">Rating pool</label>
            <select name="ball_type" id="ball_type" class="form-select" style="min-height:44px">
                <option value="">All event pools (separate lists)</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['red' => 'Red CTR', 'orange' => 'Orange CTR', 'standard' => 'CTR (Green/Yellow)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($stage); ?>" <?php if($pool === $stage): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="col-12 <?php echo e($event ? 'col-md-3' : 'col-md-4'); ?>"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="<?php echo e($search); ?>"></div>
        <div class="col-12 col-md-2 d-flex align-items-end ratings-submit"><button class="btn btn-primary" type="submit">Show ratings</button></div>
    </form>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="text-danger"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$event && !$selected): ?>
        <p>Choose a cohort to see players across the site. Cohorts follow recorded event categories, rather than a player's current age.</p>
    <?php else: ?>
        <p class="small text-muted mb-3"><?php echo e($players->total()); ?> player / cohort entries · ordered within separate comparison groups.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$event): ?><p class="small text-muted">Membership follows recorded categories and saved rating evidence. Legacy teams without a category are available in their event view as unresolved; they are not assigned an age group by guesswork.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($groupBy === 'age'): ?><p class="small text-muted">Age on <?php echo e($ageReference); ?><?php echo e($ageReferenceFallback ? ' (today; event start date unavailable)' : ' (event start)'); ?>. Positions remain those of the original division and comparison group.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players->getCollection()->groupBy(fn ($row) => $groupBy === 'age' ? ($row['player_age'] ?? 'unknown') : 'division'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $age => $ageMembers): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($groupBy === 'age'): ?><h2 class="h4 mt-4" data-player-age-group="<?php echo e($age); ?>"><?php echo e($age === 'unknown' ? 'Unknown age' : 'Age '.$age); ?></h2><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ageMembers->groupBy(fn ($row) => $row['cohort'].'|'.($row['ball_stage'] ?? 'unknown')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $members): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <section class="ctr-results-section mb-4" aria-label="<?php echo e($members->first()['cohort']); ?> · <?php echo e($members->first()['ball_label']); ?>"><h2 class="h5"><?php echo e($members->first()['cohort']); ?> · <?php echo e($members->first()['ball_label']); ?></h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $members->groupBy('component'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $component => $comparisonMembers): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <h3 class="h6 mt-3"><?php echo e($snapshot['reason'] ? 'Ratings unavailable' : ($comparisonMembers->first()['rating'] ? 'Comparison group: '.$component : 'Unrated players')); ?></h3>
                <div class="ctr-column-head" aria-hidden="true"><span>Position</span><span>Player</span><span>CTR</span><span>Evidence</span></div>
                <ul class="list-group mb-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $comparisonMembers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php ($displayRating = $snapshot['reason'] ? null : $member['rating']); ?>
                    <li class="list-group-item ctr-ranking-row" data-event-rating-row>
                            <div class="ctr-place"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($displayRating && $member['position']): ?><strong><?php echo e($member['position']); ?>.</strong><?php else: ?><span aria-label="No position">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                            <div class="ctr-player">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member['player_id']): ?><a href="<?php echo e(route('backend.player-performance.show', [$member['player_id'], 'ball_type' => $member['ball_stage'] ?? 'standard'])); ?>"><?php echo e($member['name']); ?></a><?php else: ?><span><?php echo e($member['name']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event && $member['regions']): ?><p>(<?php echo e(implode(', ', $member['regions'])); ?>)</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($groupBy === 'age'): ?><p class="small text-muted"><?php echo e($member['player_age'] === null ? 'Unknown age' : 'Age '.$member['player_age']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="ctr-score"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($displayRating): ?><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['stage' => $member['ball_stage'],'size' => 'inline','score' => $displayRating['score'],'local' => ($displayRating['comparable'] ?? true) === false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($member['ball_stage']),'size' => 'inline','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($displayRating['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($displayRating['comparable'] ?? true) === false)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?><?php elseif($snapshot['reason'] && $member['player_id']): ?><span class="small text-muted">Rating unavailable</span><?php else: ?><span class="small text-muted">Unrated</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                        <div class="ctr-evidence">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($displayRating): ?>
                            <p><?php if (isset($component)) { $__componentOriginale805b5da6e8d897845a3f39ddf861d9c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale805b5da6e8d897845a3f39ddf861d9c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-confidence-badge','data' => ['rating' => $displayRating]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-confidence-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rating' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($displayRating)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale805b5da6e8d897845a3f39ddf861d9c)): ?>
<?php $attributes = $__attributesOriginale805b5da6e8d897845a3f39ddf861d9c; ?>
<?php unset($__attributesOriginale805b5da6e8d897845a3f39ddf861d9c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale805b5da6e8d897845a3f39ddf861d9c)): ?>
<?php $component = $__componentOriginale805b5da6e8d897845a3f39ddf861d9c; ?>
<?php unset($__componentOriginale805b5da6e8d897845a3f39ddf861d9c); ?>
<?php endif; ?></p>
                            <p><?php echo e($displayRating['played'] ?? 'Unknown'); ?> matches · <?php echo e($displayRating['direct_opponents'] ?? 'Unknown'); ?> opponents</p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($displayRating['historical_connection'])): ?><p><strong>Low confidence — historical connection</strong> · link dated <?php echo e($displayRating['connection_reference_date'] ?? 'unknown'); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <details class="rating-evidence text-muted"><summary>Rating evidence</summary>
                            <p>Last match <?php echo e($displayRating['last_direct_match'] ?? 'unknown'); ?></p>
                            <p class="small text-muted mb-1">Last eligible activity: <?php echo e($displayRating['last_eligible_activity'] ?? $displayRating['last_played'] ?? 'unknown'); ?> · <?php echo e($displayRating['baseline_status'] ?? 'No connected main-trial baseline'); ?></p>
                            <p class="small text-muted mb-0"><?php echo e($displayRating['confidence_explanation']); ?></p>
                            </details>
                        <?php else: ?><p class="small text-muted mb-0"><?php echo e(!$member['player_id'] ? 'Imported roster member has no linked player profile.' : ($snapshot['reason'] ? 'A safe current estimate is unavailable; this does not mean the player has no rating evidence.' : ($member['rating_reason'] ?? 'No eligible saved estimate; check the player evidence or pending update.'))); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No matching players in this selection.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo e($players->links('pagination::bootstrap-5')); ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="d-flex flex-wrap gap-3 mt-3">
        <a style="min-height:44px" href="<?php echo e(route('backend.player-performance.ratings')); ?>">Browse current age groups and gender</a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?><a style="min-height:44px" href="<?php echo e(route('backend.player-performance.ratings')); ?>">Site-wide ratings</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <a style="min-height:44px" href="<?php echo e(route('backend.player-performance.directory')); ?>">Search all player profiles</a>
        <a style="min-height:44px" href="<?php echo e(route('backend.player-performance.index')); ?>">A/B performance comparison</a>
    </div>
</div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\leaderboard.blade.php ENDPATH**/ ?>