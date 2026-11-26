<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($pathwayEvents)): ?>
<section id="pathway-upcoming-events" class="mt-3" aria-labelledby="pathway-events-heading" data-pathway-upcoming-events>
  <h3 class="h6" id="pathway-events-heading">Upcoming events for your stage</h3>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathwayEvents['stage']): ?>
    <p class="small text-muted">Matches for <?php echo e($pathway['presentation']['stage']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['presentation']['state'] === 'update_pending'): ?> · based on your latest saved rating; update pending <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>. Check age, division, invitation and entry requirements with the organiser. A stage match does not guarantee entry.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pathwayEvents['events']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <article class="pathway-stage mb-2" style="--stage-ink:#145c8d" data-pathway-event="<?php echo e($option['id']); ?>">
        <h4 class="h6 mb-1"><a href="<?php echo e($option['url']); ?>"><?php echo e($option['name']); ?></a></h4>
        <p class="small mb-1"><?php echo e($option['date']); ?> · <?php echo e($option['venue']); ?> · <?php echo e($option['status']); ?></p>
        <p class="small mb-1">Matching categories: <?php echo e(implode(', ', $option['categories'])); ?></p>
        <a class="small d-inline-flex align-items-center" style="min-height:44px" href="<?php echo e($option['url']); ?>">Check entry requirements</a>
      </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p class="small">No upcoming published events with confirmed matching stage details are listed here yet. Browse events or ask your coach about other options.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathwayEvents['limited']): ?><p class="small text-muted">More upcoming events may be available. <a href="<?php echo e(route('home')); ?>">Browse all events</a>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php else: ?>
    <p class="small text-muted">Stage-specific events will appear once comparable rating evidence and a confirmed stage are available. You can still browse events and check requirements with the organiser.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-events.blade.php ENDPATH**/ ?>