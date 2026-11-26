<?php $__env->startSection('title', 'Plus email preferences'); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-4" style="max-width:720px">
  <h1 class="h3">Your Plus update emails</h1>
  <p>Morning summaries cover new events and unseen updates. Entered-event alerts are queued after public draws, schedules, announcements or event information stay unchanged for ten minutes. No update means no email.</p>
  <p>These preferences apply to your account, across all your linked Plus players.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?><p class="alert alert-success" role="status"><?php echo e(session('status')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="alert alert-danger"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$ready): ?><p class="alert alert-info">Email preferences will be available when this feature launches.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php ($snoozed = $preferences->snoozed_until && \Illuminate\Support\Carbon::parse($preferences->snoozed_until)->isFuture()); ?>
  <p><strong>Current setting:</strong> <?php echo e(!$preferences->enabled ? 'Off' : ($snoozed ? 'Snoozed until '.\Illuminate\Support\Carbon::parse($preferences->snoozed_until)->timezone('Africa/Johannesburg')->format('d M Y, H:i').' South African time' : 'On, only when there are updates')); ?></p>
  <form method="post" action="<?php echo e(route('player-plus.notifications.update')); ?>">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PATCH'); ?>
    <fieldset <?php if(!$ready): echo 'disabled'; endif; ?>>
      <legend class="h5">Choose what works for you</legend>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['daily' => 'Receive Plus updates / resume now', 'snooze7' => 'Snooze for 7 days', 'snooze30' => 'Snooze for 30 days', 'off' => 'Turn Plus update emails off']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <label class="d-flex align-items-center gap-2 py-2" style="min-height:44px">
          <input type="radio" name="choice" value="<?php echo e($value); ?>" <?php if(old('choice', !$preferences->enabled ? 'off' : ($snoozed ? '' : 'daily')) === $value): echo 'checked'; endif; ?> required> <?php echo e($label); ?>

        </label>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button class="btn btn-primary my-3" style="min-height:44px">Save preference</button>
    </fieldset>
  </form>
  <p class="small text-muted">After resuming, the next checks quietly establish fresh starting points for morning summaries and entered-event alerts. Later new updates can be emailed without a catch-up backlog. Your choice applies to your account address. Extra notification-contact copies are sent only when all currently linked accounts allow updates; these contacts do not gain account access. Turning Plus updates off does not stop payment receipts, registration confirmations or essential organiser communications.</p>
  <a href="<?php echo e(route('my.tennis')); ?>">Back to My Tennis</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player-plus\notifications.blade.php ENDPATH**/ ?>