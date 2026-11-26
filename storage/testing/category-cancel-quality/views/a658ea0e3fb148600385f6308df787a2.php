        <li class="list-group-item ctr-ranking-row" data-ctr-ranking-row>
        <div class="ctr-place"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$snapshot['reason'] && $player['position'] !== null && (!$unified || $player['rating'])): ?><strong <?php echo e($diagnosticOrder ? 'data-score-list-position' : 'data-rating-position'); ?>="<?php echo e($player['position']); ?>" <?php if($diagnosticOrder): ?>aria-label="List number <?php echo e($player['position']); ?> in diagnostic score order<?php echo e($ageOrder ? ' for '.$player['age_group'] : ''); ?>"<?php endif; ?>><?php echo e($player['position']); ?>.</strong><?php else: ?><span aria-label="No overall position">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        <div class="ctr-player">
            <a href="<?php echo e(route('backend.player-performance.show', ['player' => $player['id'], 'ball_type' => $ratingStage ?? $ballType ?? 'overall', 'comparison_group' => ($comparisonGroup ?? '') !== '' ? $comparisonGroup : null])); ?>"><?php echo e($player['name']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['birth_year'] ?? null): ?> (<?php echo e($player['birth_year']); ?>)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
            <p><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($allAgesBall ?? false)): ?><?php echo e($player['age_group']); ?> · <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($player['gender']); ?></p>
            <p title="Recorded regional team affiliations; may include past seasons"><?php echo e(implode(' · ', $player['region_affiliations']['names'] ?? []) ?: 'Region not recorded'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($player['region_affiliations']['additional'] ?? 0) > 0): ?> · +<?php echo e($player['region_affiliations']['additional']); ?> more <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
        </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['rating']): ?>
                <?php ($rating = $player['rating']); ?>
                <div class="ctr-score"><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['overall' => !$ballLevel,'stage' => $ballLevel ? $ratingStage : null,'label' => $ratingLabel,'compact' => true,'score' => $rating['within_group_score'] ?? $rating['score'],'local' => isset($rating['within_group_score']) || $player['awaiting_calibration'] || ($rating['comparable'] ?? true) === false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(!$ballLevel),'stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ballLevel ? $ratingStage : null),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratingLabel),'compact' => true,'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['within_group_score'] ?? $rating['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(isset($rating['within_group_score']) || $player['awaiting_calibration'] || ($rating['comparable'] ?? true) === false)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></div>
                <div class="ctr-evidence"><p><?php if (isset($component)) { $__componentOriginale805b5da6e8d897845a3f39ddf861d9c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale805b5da6e8d897845a3f39ddf861d9c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-confidence-badge','data' => ['rating' => $rating]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-confidence-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rating' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating)]); ?>
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rating['historical_connection'])): ?><p class="text-warning-emphasis">Historical connection</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['awaiting_calibration']): ?><p>Awaiting calibration</p><?php else: ?><p><?php echo e($rating['cohort']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="ctr-row-detail">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($rating['evidence_basis'] ?? null) === 'finish_only'): ?><p class="small mb-1"><strong>Published final placings only · low confidence.</strong> No eligible played singles are recorded for this ball rating.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['awaiting_calibration']): ?><p class="mb-1 text-warning-emphasis"><strong>Awaiting calibration · <?php echo e($diagnosticOrder ? 'no overall ability rank' : 'no overall position'); ?></strong></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rating['historical_connection'])): ?><p class="small text-warning-emphasis mb-1"><strong>Low confidence — historical connection</strong> · link dated <?php echo e($rating['connection_reference_date'] ?? 'unknown'); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($rating['comparable'] ?? true) === false): ?><p class="small text-warning-emphasis mb-1"><strong>Provisional within-group rating · low confidence.</strong> Not calibrated against other groups.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($player['ratings']) > 1): ?><details><summary class="py-2">All saved ratings</summary><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player['ratings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $other): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="small mb-1"><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['overall' => !$ballLevel,'stage' => $ballLevel ? $ratingStage : null,'label' => $ratingLabel,'size' => 'small','score' => $other['within_group_score'] ?? $other['score'],'local' => isset($other['within_group_score']) || ($other['comparable'] ?? true) === false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(!$ballLevel),'stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ballLevel ? $ratingStage : null),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratingLabel),'size' => 'small','score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($other['within_group_score'] ?? $other['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(isset($other['within_group_score']) || ($other['comparable'] ?? true) === false)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?> · <?php echo e($other['cohort']); ?> · comparison group <?php echo e($other['component']); ?> · last played <?php echo e($other['last_played']); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php else: ?><div class="ctr-score"><?php echo e($snapshot['reason'] ? 'Rating unavailable' : 'Unrated'); ?></div><div class="ctr-evidence"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['rating_reason'] ?? null): ?><?php echo e($player['rating_reason']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </li>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\partials\ctr-directory-row.blade.php ENDPATH**/ ?>