<?php
  $registrationTermsContent = \App\Models\SiteSetting::get('terms_conditions_content', '');
?>
<section class="registration-terms mb-3" aria-label="Registration terms and refunds">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(trim(strip_tags($registrationTermsContent)) !== ''): ?>
    <details class="mb-3">
      <summary class="fw-semibold">Cape Tennis Terms &amp; Conditions</summary>
      <div class="mt-2"><?php echo $registrationTermsContent; ?></div>
    </details>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo $__env->make('frontend.partials.registration-refund-terms', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\partials\registration-terms.blade.php ENDPATH**/ ?>