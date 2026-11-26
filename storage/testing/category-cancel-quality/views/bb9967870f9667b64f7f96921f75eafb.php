<?php $__env->startSection('title', 'Email order of play — '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-4" style="max-width: 980px">
  <a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue, 'schedule_source' => $scope['source'], 'date' => $scope['date'], 'draw_ids' => $scope['draw_ids']])); ?>" class="d-inline-block mb-3">← Back to scoring</a>
  <div class="card"><div class="card-body">
    <h1 class="h4">Email order of play</h1>
    <p class="mb-1"><strong><?php echo e($event->name); ?></strong> · <?php echo e($venue->name); ?></p>
    <p class="mb-1"><?php echo e($scope['date'] ? \Carbon\Carbon::parse($scope['date'])->format('l j F Y') : 'All saved days'); ?> · <strong><?php echo e(count($rows)); ?> <?php echo e(Str::plural('match', count($rows))); ?></strong></p>
    <p class="small text-muted">Draws: <?php echo e(implode(', ', $drawNames)); ?></p>
    <div class="alert text-dark <?php echo e($scope['source'] === 'working' ? 'alert-warning' : 'alert-info'); ?>" role="status">
      <?php echo e($scope['source'] === 'working' ? 'Working schedule — private staff copy. Sending does not publish match times.' : 'Published schedule — saved public match times.'); ?>

      Includes the selected venue, days and draws.
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rows): ?>
      <a class="btn btn-outline-primary mb-3" target="_blank" rel="noopener" href="<?php echo e(route('frontend.scoring.email.preview', ['event' => $event, 'venue' => $venue, ...$scope, 'review_token' => $reviewToken])); ?>">Preview PDF attachment</a>
    <?php else: ?>
      <p class="alert alert-warning">There are no scheduled matches to email in this selection.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('frontend.scoring.email.send', [$event, $venue])); ?>" id="email-order-of-play-form">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="source" value="<?php echo e($scope['source']); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scope['date']): ?><input type="hidden" name="date" value="<?php echo e($scope['date']); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scope['draw_ids']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="draw_ids[]" value="<?php echo e($id); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <input type="hidden" name="request_key" value="<?php echo e(old('request_key', $requestKey)); ?>">
      <input type="hidden" name="review_token" value="<?php echo e(old('review_token', $reviewToken)); ?>">
      <label class="form-label" for="order-of-play-recipient">Recipient email</label>
      <input class="form-control mb-2" id="order-of-play-recipient" name="recipient" type="email" required maxlength="254" value="<?php echo e(old('recipient')); ?>" aria-describedby="order-of-play-recipient-help">
      <p class="small text-muted" id="order-of-play-recipient-help">Enter the convenor’s or another intended recipient’s email. Check the address and PDF before sending.</p>
      <button type="submit" class="btn btn-primary" <?php if(!$rows): echo 'disabled'; endif; ?>>Send order of play</button>
    </form>
  </div></div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script>
document.getElementById('email-order-of-play-form').addEventListener('submit', function () {
  const button = this.querySelector('button[type="submit"]');
  button.disabled = true;
  button.textContent = 'Queuing email…';
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\scoring\email-order-of-play.blade.php ENDPATH**/ ?>