<p>Dear Player,</p>

<p>The courts and venues for <strong><?php echo e($event->name); ?></strong> have now been assigned.</p>

<p><strong>The following venues and courts will be used:</strong></p>

<ul>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <li>
      <strong><?php echo e($assignment['name']); ?></strong>
      <ul>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $assignment['venues']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li>
            <?php echo e($venue['name']); ?> —
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venue['courts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $court): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php echo e(preg_match('/^court\b/i', $court) ? $court : 'Court '.$court); ?><?php echo e(!$loop->last ? ', ' : ''); ?>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
    </li>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</ul>

<p>Please check the event page regularly for any further updates.</p>

<p>Kind regards,<br>Cape Tennis</p>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\schedule\partials\venue-announcement-draft.blade.php ENDPATH**/ ?>