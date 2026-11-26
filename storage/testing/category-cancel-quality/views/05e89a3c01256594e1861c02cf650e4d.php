<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Player Plus · Membership preview'); ?>
<?php $__env->startSection('content'); ?>
<div class="mp-preview">
<?php echo $__env->make('backend.player-performance.membership-preview.chrome', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<p><a class="mp-btn" href="<?php echo e(route('backend.player-performance.membership-preview.alerts')); ?>">Try fictional draw alerts &amp; preferences</a></p>
<div class="mp-grid">
<section class="mp-card mp-plan"><div class="mp-topline"><span class="mp-eyebrow">One proposed membership</span><span class="mp-chip">Coming later</span></div><h2 class="mt-3">Player Plus</h2><p class="mp-subtle">For a player and the parent following their tennis journey.</p><?php if (isset($component)) { $__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-pricing','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-pricing'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f)): ?>
<?php $attributes = $__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f; ?>
<?php unset($__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f)): ?>
<?php $component = $__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f; ?>
<?php unset($__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f); ?>
<?php endif; ?><ul class="mp-checklist"><li><strong>Compare with other players</strong><br><span class="mp-subtle">Ratings, evidence and context within comparable age groups.</span></li><li><strong>Follow progress over time</strong><br><span class="mp-subtle">Planned progress history, showing when evidence changes.</span></li><li><strong>Keep up with tournament draws</strong><br><span class="mp-subtle">Planned alerts when a relevant draw is published or updated.</span></li><li><strong>Understand provisional ratings</strong><br><span class="mp-subtle">Clear activity and evidence labels alongside each estimate.</span></li></ul><button class="mp-btn w-100" type="button" disabled aria-describedby="mp-purchase-note">Membership purchase unavailable</button><p id="mp-purchase-note" class="mp-subtle mt-2 mb-0">This is a design preview. No membership can be purchased or activated here.</p></section>
<div class="d-flex flex-column gap-3"><section class="mp-card"><div class="mp-eyebrow">A considered launch</div><h2 class="mt-2">Useful insight, with honest limits.</h2><p class="mp-subtle">The directory demonstrates saved boys and girls profiles from U10 to U13. Progress history, membership access and real draw delivery are planned. Try browser-only alert choices with fictional examples in the simulator.</p><div class="mp-note">A paid membership would not change a score, award a leaderboard position or remove a provisional label.</div></section><section class="mp-card"><div class="mp-topline"><h2 class="mb-0">A future draw alert</h2><span class="mp-chip">Illustration only</span></div><div class="mp-alert-example mt-3"><strong>Your tournament draw has been published</strong><p class="mp-subtle mt-2 mb-0">A proposed alert would help a player or parent find their published draw and check match details.</p></div><p class="mp-subtle mt-3 mb-0">No real event, recipient or delivery is connected to this example. It sends no email or notification.</p></section><section class="mp-card"><h2>Before membership goes live</h2><p class="mp-subtle mb-0">Parent and player access, privacy, refund terms and membership benefits still need to be implemented and verified. Review the current monthly and annual plans on the Player Plus offer page.</p></section></div>
</div>
<?php echo $__env->make('backend.player-performance.membership-preview.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\membership-preview\membership.blade.php ENDPATH**/ ?>