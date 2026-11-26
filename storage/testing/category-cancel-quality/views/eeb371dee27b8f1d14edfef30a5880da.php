<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float) $freeTrialTermsEvent->entryFee <= 0): ?>
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="terms_accepted" value="1" id="<?php echo e($freeTrialTermsId); ?>" required>
    <label class="form-check-label small" for="<?php echo e($freeTrialTermsId); ?>">I accept the <a href="#free-trial-registration-terms">registration terms and refund policy</a>.</label>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\partials\free-trial-terms-acceptance.blade.php ENDPATH**/ ?>