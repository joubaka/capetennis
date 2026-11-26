<?php $__env->startSection('title', 'CTR marketing contacts'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.marketing.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2"><label class="visually-hidden" for="search">Search coach contacts</label><input class="form-control w-auto" id="search" name="search" maxlength="100" value="<?php echo e($search); ?>" placeholder="Academy, coach, town or email"><button class="btn btn-outline-primary">Search</button><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== ''): ?><a class="btn btn-outline-secondary" href="<?php echo e(route('superadmin.marketing.contacts.index')); ?>">Clear</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></form>
    <a class="btn btn-primary" href="<?php echo e(route('superadmin.marketing.contacts.create')); ?>">Add coach contact</a>
  </div>
  <p class="small text-muted"><?php echo e($contacts->total()); ?> contacts. Preferences are planning records, not proof of consent. No contacts are imported automatically.</p>
  <div class="table-responsive"><table class="table"><thead><tr><th>Academy / coach</th><th>Contact details</th><th>Venue</th><th>Interest</th><th>Preference</th><th>Action</th></tr></thead><tbody>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $contacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contact): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr><td><strong><?php echo e($contact->academy); ?></strong><br><?php echo e($contact->name); ?></td><td><?php echo e($contact->email ?: 'No email'); ?><br><?php echo e($contact->phone ?: 'No phone'); ?></td><td><?php echo e($contact->town ?: 'Not recorded'); ?><br><?php echo e($contact->courts ?? 'Unknown'); ?> courts</td><td><?php echo e(ucfirst($contact->status)); ?></td><td><?php echo e(ucfirst(str_replace('_', ' ', $contact->preference))); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('superadmin.marketing.contacts.edit', $contact)); ?>">Edit</a></td></tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6">No coach contacts found. Add a contact to start planning outreach.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </tbody></table></div>
  <div class="mt-3"><?php echo e($contacts->links()); ?></div>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\marketing\contacts.blade.php ENDPATH**/ ?>