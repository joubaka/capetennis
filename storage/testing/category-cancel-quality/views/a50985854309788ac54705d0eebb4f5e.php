<?php $__env->startSection('title', 'Send to Capester'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Capester support'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card"><div class="card-body">
  <h2 class="h4">Send a task to Capester</h2>
  <p>Describe the task for this event. Only the text you enter below and the event reference will be transferred to your local Capester inbox. Review it before sending; leave out passwords and personal player details.</p>
  <p>Delivery means received for background review. It does not perform the task or change the application.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?><p role="status"><?php echo e(session('status')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="post" action="<?php echo e(route('backend.capester.store', $event)); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="submission_uuid" value="<?php echo e(old('submission_uuid', (string) Illuminate\Support\Str::uuid())); ?>">
    <input type="hidden" name="kind" value="support">
    <label for="capester-description">Task description</label>
    <textarea id="capester-description" name="capester_description" maxlength="2000" rows="6" class="form-control" required><?php echo e(old('capester_description')); ?></textarea>
    <label class="d-block mt-3"><input type="checkbox" name="reviewed" value="1" required> I reviewed this text and agree to transfer it to my local Capester inbox.</label>
    <button class="btn btn-primary mt-3" type="submit">Send to Capester</button>
  </form>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app()->environment(['local', 'testing'])): ?>
  <details class="mt-3"><summary>Local connection test</summary>
    <form method="post" action="<?php echo e(route('backend.capester.store', $event)); ?>"><?php echo csrf_field(); ?>
      <input type="hidden" name="submission_uuid" value="<?php echo e((string) Illuminate\Support\Str::uuid()); ?>">
      <input type="hidden" name="kind" value="hello"><input type="hidden" name="capester_description" value="Synthetic fixed-prompt connection test.">
      <input type="hidden" name="reviewed" value="1">
      <button class="btn btn-secondary" type="submit">Queue connection test</button>
    </form>
  </details>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<div class="card mt-3"><div class="card-body"><h3 class="h5">Your requests for this event</h3>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <article class="border-bottom py-3" style="overflow-wrap:anywhere"><strong><?php echo e($item->kind === 'hello' ? 'Connection test' : 'Task for review'); ?></strong>
    <p><?php echo e($item->capester_description); ?></p><p>Status: <?php echo e(str_replace('_', ' ', $item->status)); ?> · <?php echo e($item->created_at->format('Y-m-d H:i')); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->status === 'received'): ?><p>Delivered to the local inbox for review; no fix has been applied.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </article>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No requests yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo e($items->links()); ?>

</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\capester.blade.php ENDPATH**/ ?>