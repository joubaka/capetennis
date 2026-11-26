<?php $__env->startSection('title', 'Share nomination report'); ?>
<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-style'); ?>
<style>
[data-roster-report] .select2-selection--multiple .select2-selection__rendered { white-space: normal; }
[data-roster-report] .select2-selection--multiple .select2-selection__choice { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
.select2-results__option { overflow-wrap: anywhere; }
</style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/cavaliers-report-share.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/cavaliers-report-share.js'))); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="card"><div class="card-body" data-roster-report data-url="<?php echo e(route('backend.cavaliers-report.index', $event)); ?>" data-preview-url="<?php echo e(route('backend.cavaliers-report.preview', $event)); ?>">
<h3>Share nomination report</h3><p><?php echo e($event->name); ?> · All prepared divisions</p>
<p>Choose up to 30 accounts, then preview the email and PDF. Each division includes its saved team, up to five reserves, the next five outside that list, and explanations for Inter-Districts players outside the selected list.</p>
<div class="mb-3">
<label class="form-label" for="roster-recipients">Recipients</label>
<select class="form-select" id="roster-recipients" data-recipients multiple aria-describedby="roster-recipient-help roster-recipient-status" style="width:100%"></select>
<p class="small text-muted mt-1 mb-0" id="roster-recipient-help">Search by account name or email using at least two characters. Select up to 30 accounts.</p>
<p class="small text-muted mt-1 mb-0" id="roster-recipient-status" data-recipient-status role="status" aria-live="polite"></p>
</div>
<fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto px-1 h6">Sender details</legend><div class="row g-3">
<div class="col-12 col-lg-4"><label class="form-label" for="roster-from-name">Sender name</label><input class="form-control" id="roster-from-name" data-from-name type="text" value="<?php echo e($fromName); ?>" maxlength="120" required></div>
<div class="col-12 col-lg-4"><label class="form-label" for="roster-from-address">From email address</label><select class="form-select" id="roster-from-address" data-from-address required><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fromAddresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($address); ?>" <?php if($address === $fromAddress): echo 'selected'; endif; ?>><?php echo e($address); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
<div class="col-12 col-lg-4"><label class="form-label" for="roster-reply-to">Reply-to email address</label><input class="form-control" id="roster-reply-to" data-reply-to type="email" value="<?php echo e($replyTo); ?>" maxlength="254" required></div>
</div><p class="small text-muted mt-2 mb-0">From addresses use your configured verified senders. Set Reply-to to the address that should receive responses.</p></fieldset>
<div class="mb-3"><label class="form-label" for="roster-email-subject">Email subject</label><input class="form-control" id="roster-email-subject" data-subject type="text" value="<?php echo e($subject); ?>" maxlength="200" required></div>
<div class="mb-3"><label class="form-label" for="roster-email-message">Email message</label><textarea class="form-control" id="roster-email-message" data-message rows="10" maxlength="10000" required><?php echo e($message); ?></textarea><p class="small text-muted mt-1">Edit the message before previewing. It is sent as plain text with your line breaks preserved.</p></div>
<button class="btn btn-primary" type="button" data-preview>Preview email and PDF</button>
<div data-status role="status" aria-live="polite" aria-atomic="true" class="mt-2"></div>
<section data-review hidden><h4>Review before sending</h4><p data-reviewed-recipients></p><h5>Email preview</h5><p data-reviewed-sender style="overflow-wrap:anywhere"></p><p><strong>Subject:</strong> <span data-reviewed-subject></span></p><div class="border rounded p-3" style="white-space:pre-wrap;overflow-wrap:anywhere;color:#17283b;background:#fff" data-email aria-label="Email message preview"></div>
<h5 class="mt-3">PDF attachment preview</h5><a data-pdf-link target="_blank" rel="noopener">Open PDF in a new tab</a><iframe title="PDF attachment preview" style="width:100%;height:38rem;border:1px solid #ddd" data-pdf></iframe>
<button class="btn btn-primary mt-3" type="button" data-send>Send reviewed email and PDF</button></section>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\nominations\cavaliers-report-share.blade.php ENDPATH**/ ?>