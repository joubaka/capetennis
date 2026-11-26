<?php $__env->startSection('title', 'Email participating regions'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl py-3">
<h3><?php echo e($event->name); ?> — Email participating regions</h3>
<p>Select the regions whose players and parents should receive this email. Every email contains all saved selected teams and reserves, across all age groups. Sending this private communication does not publish selections or create invitations.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<form method="post" action="<?php echo e(route('backend.selection-region-mail.preview', $event)); ?>"><?php echo csrf_field(); ?>
<input type="hidden" name="selection_fingerprint" value="<?php echo e(old('selection_fingerprint', $draft['selection_fingerprint'])); ?>">
<div class="card card-body mb-3"><h5>1. Select regions</h5>
<label class="mb-2"><input type="checkbox" id="select-all-regions" <?php if(count(old('selection_region_ids', $selectedRegions)) === $regions->count()): echo 'checked'; endif; ?>> Select all participating regions</label>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label class="d-block mb-2"><input type="checkbox" name="selection_region_ids[]" value="<?php echo e($region->region_id); ?>" <?php if(in_array($region->region_id, old('selection_region_ids', $selectedRegions))): echo 'checked'; endif; ?>> <?php echo e($region->region?->region_name); ?></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p class="form-text">Recipients are current participating players and their linked parent contacts, including reserves, in the selected regions. Shared addresses receive one email. Missing contacts will be shown for review.</p></div>
<div class="card card-body mb-3"><h5>2. Customize email</h5>
<label class="d-block w-100 mb-3">Subject<input class="form-control" name="subject" maxlength="200" value="<?php echo e(old('subject', $draft['subject'])); ?>" required></label>
<label class="d-block w-100 mb-3">Message<textarea class="form-control" name="body" rows="24" maxlength="30000" required><?php echo e(old('body', $draft['body'])); ?></textarea></label>
<label class="d-block w-100 mb-3">Sender name<input class="form-control" name="from_name" maxlength="150" value="<?php echo e(old('from_name', $draft['from_name'] ?? auth()->user()->name)); ?>" required></label>
<label class="d-block w-100 mb-3">Reply-to email<input class="form-control" type="email" name="reply_to" value="<?php echo e(old('reply_to', $draft['reply_to'] ?? auth()->user()->email)); ?>" required></label>
<label class="d-block w-100">Extra BCC email addresses (optional)<textarea class="form-control" name="bcc_emails" rows="3" maxlength="13000" aria-describedby="bcc-help"><?php echo e(old('bcc_emails', $draft['bcc_emails'] ?? '')); ?></textarea></label>
<p class="form-text" id="bcc-help">Separate addresses with commas, semicolons or new lines (up to 50). Each extra address receives one hidden copy. An address already in the player or parent audience receives only its usual email. You will review these addresses before sending.</p></div>
<button class="btn btn-primary">Review recipients and email</button>
<a class="btn btn-outline-secondary" href="<?php echo e(route('backend.selection-preview.show', $event)); ?>">Back to selections</a>
<p class="mt-2">Nothing is sent at this step. You will approve the reviewed email on the next screen.</p>
</form></div>
<script>
const regionBoxes = [...document.querySelectorAll('input[name="selection_region_ids[]"]')];
const allRegions = document.getElementById('select-all-regions');
allRegions.addEventListener('change', () => regionBoxes.forEach(box => box.checked = allRegions.checked));
regionBoxes.forEach(box => box.addEventListener('change', () => { allRegions.checked = regionBoxes.every(item => item.checked); allRegions.indeterminate = regionBoxes.some(item => item.checked) && !allRegions.checked; }));
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\selection-region-mail.blade.php ENDPATH**/ ?>