<?php $__env->startSection('title', 'Past-event player ratings'); ?>
<?php $__env->startSection('content'); ?>
<div data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-event-rating-history="v1">
    <?php echo $__env->make('frontend.my-tennis.profile-header', ['workspaceTab' => 'rating'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="card"><div class="card-body">
        <h2 class="h5">Past-event ratings</h2>
        <p><a data-my-tennis-link href="<?php echo e(route('my.tennis', ['player' => $ratingPlayer, 'tab' => 'rating'])); ?>">Back to rating</a></p>
        <p>See your estimated rating at the end of each event.</p>
        <details class="mb-3">
            <summary>How these estimates are worked out</summary>
            <p class="small text-muted mb-0 mt-2">These ratings were calculated later using eligible results from the <?php echo e(\App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS); ?> days up to each event’s end. Each ball level has its own rating. Published final placings carry less weight than scored singles matches.</p>
        </details>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('event_history_status')): ?>
            <p class="alert alert-info" role="status"><?php echo e(session('event_history_status')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeline['reason']): ?>
            <p class="alert alert-info" role="status"><?php echo e($timeline['reason']); ?></p>
        <?php else: ?>
            <p class="small text-muted"><?php echo e($timeline['total']); ?> published Cape Tennis events · saved through <?php echo e($timeline['as_of']); ?>.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <form method="post" action="<?php echo e(route('my.tennis.event-history.refresh', $ratingPlayer)); ?>" class="mb-3">
            <?php echo csrf_field(); ?>
            <button class="btn btn-outline-primary" style="min-height:44px" <?php if(in_array($refreshStatus['state'], ['pending', 'running'], true)): echo 'disabled'; endif; ?>>Update past-event history</button>
            <span class="small text-muted d-block mt-2" role="status"><?php echo e($refreshStatus['message']); ?></span>
        </form>
        <style>
            .event-rating-levels{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
            .event-rating-card{min-width:0;overflow-wrap:anywhere;border:1px solid #d9e3e0;border-radius:.5rem;padding:1rem;margin-top:1rem}
            .event-rating-level{min-width:0;padding:.75rem;border-top:3px solid var(--ball-color);background:#f7faf9;border-radius:.3rem}
            @media(max-width:767px){.event-rating-levels{grid-template-columns:1fr}}
        </style>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $timeline['events']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <article class="event-rating-card">
                <h2 class="h5"><?php echo e($event['event']); ?></h2><p class="small text-muted">As of <?php echo e($event['date']); ?></p>
                <p class="small">Recorded entry level: <?php echo e(collect($event['entry_stages'])->map(fn($stage) => \App\Services\Performance\BallRatingModel::LABELS[$stage])->implode(' · ') ?: 'Unclassified mixed level'); ?>. Entry is participation metadata, not completion of that level.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($event['has_eligible_matches'])): ?>
                    <p class="small text-muted">No eligible scored singles were found for this event. Valid published final placings may contribute weaker rating evidence, but do not add to the played-event count.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event['finishes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $finish): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p class="small mb-1">Published finishing position: <?php echo e($finish['position']); ?> · <?php echo e($finish['category']); ?>. A valid complete singles field may contribute weaker finishing-order evidence.</p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="event-rating-levels">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Performance\BallRatingModel::LABELS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php ($rating = $event['levels'][$stage]); ?>
                        <section class="event-rating-level" data-event-rating-stage="<?php echo e($stage); ?>" data-rating-as-of="<?php echo e($event['date']); ?>" style="--ball-color:<?php echo e(['red' => '#b92727', 'orange' => '#bd640b', 'standard' => '#14645a'][$stage]); ?>">
                            <h3 class="h6"><?php echo e($label); ?></h3>
                            <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['stage' => $stage,'score' => $rating['score'],'local' => $rating['local'],'title' => 'Reconstructed '.$label.' rating as of '.$event['date'].'. Retrospective estimate, not an original published rating.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stage),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rating['local']),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Reconstructed '.$label.' rating as of '.$event['date'].'. Retrospective estimate, not an original published rating.')]); ?>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($rating['evidence_basis'] ?? null) === 'finish_only'): ?>
                                <p class="small mb-1">Low confidence · final placings only</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rating['score'] === null): ?>
                                <p class="small mb-1">Not rated at this date.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <p class="small mt-2 mb-1"><?php echo e($rating['matches_in_event']); ?> eligible singles matches at this event · <?php echo e($rating['cape_events']); ?> played Cape Tennis events at this level by this date.</p>
                            <p class="small text-muted mb-0"><?php echo e($rating['reason']); ?></p>
                        </section>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($timeline['reason'])): ?><p>No published completed events were found for this player.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeline['pages'] > 1): ?>
            <nav aria-label="Past-event history pages" class="d-flex flex-wrap gap-3 mt-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeline['page'] > 1): ?><a href="<?php echo e(route('my.tennis.event-history', ['player' => $ratingPlayer, 'page' => $timeline['page'] - 1])); ?>">Previous</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span>Page <?php echo e($timeline['page']); ?> of <?php echo e($timeline['pages']); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeline['page'] < $timeline['pages']): ?><a href="<?php echo e(route('my.tennis.event-history', ['player' => $ratingPlayer, 'page' => $timeline['page'] + 1])); ?>">Next</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('frontend.my-tennis.workspace-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\event-rating-history.blade.php ENDPATH**/ ?>