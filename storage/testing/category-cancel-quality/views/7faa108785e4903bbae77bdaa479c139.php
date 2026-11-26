<details <?php echo e($attributes); ?>>
  <summary class="fw-semibold">Court Convenor Guide</summary>
  <p class="text-muted small mt-2 mb-2">Copy the current guide into your assignment message to the convenor.</p>
  <textarea class="form-control mb-2" rows="8" aria-label="Court Convenor Guide" readonly><?php echo e(\App\Models\SiteSetting::courtConvenorGuide()); ?></textarea>
  <button type="button" class="btn btn-sm btn-outline-primary"
          onclick="copyCourtConvenorGuide(this)">Copy guide</button>
  <span class="small ms-2" role="status"></span>
</details>

<?php if (! $__env->hasRenderedOnce('c92320cd-7501-48aa-90e6-75a65ad94527')): $__env->markAsRenderedOnce('c92320cd-7501-48aa-90e6-75a65ad94527'); ?>
  <script>
    async function copyCourtConvenorGuide(button) {
      const guide = button.previousElementSibling;
      const status = button.nextElementSibling;

      try {
        await navigator.clipboard.writeText(guide.value);
        status.textContent = 'Guide copied.';
      } catch {
        guide.focus();
        guide.select();
        status.textContent = 'Use your device’s Copy command.';
      }
    }
  </script>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\backend\court-convenor-guide.blade.php ENDPATH**/ ?>