<?php $__env->startSection('title', 'CTR campaign draft'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.marketing.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div data-marketing-editor>
<div class="row g-4">
<div class="col-12 col-xl-6 marketing-editor-column"><div class="card"><div class="card-body">
<div class="marketing-eyebrow">Campaign studio</div>
<h2 class="h5 mt-1"><?php echo e($campaign->exists ? 'Edit campaign draft' : 'Create campaign draft'); ?></h2>
<p class="text-muted">Write your message and check the live previews. Edits appear before saving; nothing is sent.</p>
<form method="post" action="<?php echo e($campaign->exists ? route('superadmin.marketing.campaigns.update', $campaign) : route('superadmin.marketing.campaigns.store')); ?>">
<?php echo csrf_field(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($campaign->exists): ?> <?php echo method_field('put'); ?> <input type="hidden" name="version" value="<?php echo e($campaign->version); ?>"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="mb-3"><label class="form-label" for="title">Campaign title *</label><input class="form-control" id="title" name="title" maxlength="255" required value="<?php echo e(old('title', $campaign->title)); ?>"></div>
<div class="mb-3"><label class="form-label" for="subject">Email subject</label><input class="form-control" id="subject" name="subject" maxlength="255" value="<?php echo e(old('subject', $campaign->subject)); ?>"></div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['email_body' => 'Email draft', 'whatsapp_body' => 'WhatsApp draft']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="mb-3"><div class="d-flex flex-wrap justify-content-between gap-1"><label class="form-label" for="<?php echo e($field); ?>"><?php echo e($label); ?> *</label><small class="text-muted" data-count="<?php echo e($field); ?>"></small></div><textarea class="form-control marketing-copy-input" id="<?php echo e($field); ?>" name="<?php echo e($field); ?>" rows="<?php echo e($field === 'email_body' ? 15 : 11); ?>" maxlength="30000" required><?php echo e(old($field, $campaign->$field)); ?></textarea></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p class="small text-muted">Use blank lines for spacing, *bold* for emphasis, ## for headings and | separated rows for tables. HTML is shown as text. Saving keeps the original plain-text copy.</p>
<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary">Save draft</button><a class="btn btn-outline-secondary" href="<?php echo e(route('superadmin.marketing.campaigns.index')); ?>">Cancel</a></div>
</form>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($campaign->exists): ?><details class="mt-4"><summary>Delete draft</summary><form method="post" class="mt-2" action="<?php echo e(route('superadmin.marketing.campaigns.destroy', $campaign)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('delete'); ?><input type="hidden" name="version" value="<?php echo e($campaign->version); ?>"><button class="btn btn-outline-danger">Delete this draft</button></form></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
</div>
<div class="col-12 col-xl-6 marketing-editor-column">
<section class="marketing-preview-workspace" aria-label="Campaign preview">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Live preview</h2><span class="badge bg-label-secondary">Unsaved edits included</span></div>
  <p class="small text-muted marketing-preview-campaign">Campaign: <span data-preview-campaign></span></p>
  <div class="nav nav-pills gap-2 mb-3" role="tablist" aria-label="Preview channel">
    <button type="button" class="nav-link active" id="marketing-email-tab" role="tab" aria-selected="true" aria-controls="marketing-email-panel" data-preview-tab>Email</button>
    <button type="button" class="nav-link" id="marketing-whatsapp-tab" role="tab" aria-selected="false" aria-controls="marketing-whatsapp-panel" tabindex="-1" data-preview-tab>WhatsApp</button>
  </div>
  <div id="marketing-email-panel" role="tabpanel" aria-labelledby="marketing-email-tab">
    <div class="marketing-email-envelope"><span class="marketing-eyebrow">Subject</span><div class="fw-semibold mt-1" data-preview-subject></div></div>
    <article class="marketing-email-sheet">
      <header class="marketing-brand-header"><span class="marketing-brand-logo"><?php echo $__env->make('_partials.macros', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span><div><strong>CAPE TENNIS</strong><span>More competition. More opportunity.</span></div></header>
      <div class="marketing-email-hero"><span class="marketing-eyebrow">CTR host programme</span><h2 data-preview-email-heading></h2><div class="marketing-orange-rule"></div></div>
      <div class="marketing-message-copy marketing-email-copy" data-preview-email></div>
      <div class="px-4 pb-4"><a class="btn btn-primary" href="<?php echo e(route('host-events.index')); ?>">I’d like to host an event</a></div>
      <footer class="marketing-email-footer">Cape Tennis · Academy host programme<br>Campaign draft for review</footer>
    </article>
    <p class="small text-muted mt-3">Branded layout preview only. No email has been generated or sent; actual email-client rendering has not been tested.</p>
  </div>
  <div id="marketing-whatsapp-panel" role="tabpanel" aria-labelledby="marketing-whatsapp-tab" hidden>
    <div class="marketing-phone">
      <header class="marketing-phone-header"><span class="marketing-brand-logo"><?php echo $__env->make('_partials.macros', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span><div><strong>Cape Tennis</strong><span>Campaign preview</span></div></header>
      <div class="marketing-phone-chat">
        <div class="marketing-whatsapp-bubble">
          <div class="marketing-social-card"><span class="marketing-eyebrow">Cape Tennis · CTR</span><h2 data-preview-whatsapp-heading></h2><div class="marketing-orange-rule"></div><span>Host. Develop. Grow.</span></div>
          <div class="marketing-message-copy marketing-whatsapp-copy" data-preview-whatsapp></div>
          <p class="px-3 small"><a href="<?php echo e(route('host-events.index')); ?>">I’d like to host an event</a></p>
          <div class="marketing-bubble-footer">Draft · not sent</div>
        </div>
      </div>
    </div>
    <p class="small text-muted mt-3">Illustrative WhatsApp layout. The branded card is a visual mockup, not an image attachment or an exported graphic. Only the text draft is saved.</p>
  </div>
  <noscript><p>Enable JavaScript to preview your draft. You can still edit and save the text.</p></noscript>
</section>
</div>
</div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/css/marketing-campaign-preview.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/marketing-campaign-preview.css'))); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/js/marketing-campaign-preview.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/marketing-campaign-preview.js'))); ?>" defer></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\marketing\campaign-form.blade.php ENDPATH**/ ?>