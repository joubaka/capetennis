<?php ($scoreGridPrefix = $scoreGridPrefix ?? 'score'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/set-score-grid.css')); ?>?v=<?php echo e(filemtime(public_path('css/set-score-grid.css'))); ?>">
<div class="ct-set-score-grid" data-set-score-grid>
  <div class="ct-set-score-labels">
    <strong id="<?php echo e($scoreGridPrefix); ?>-home-label" data-score-home-label>Player 1</strong>
    <strong id="<?php echo e($scoreGridPrefix); ?>-away-label" data-score-away-label>Player 2</strong>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($setNumber = 1; $setNumber <= 5; $setNumber++): ?>
    <div class="score-set-row ct-set-score-row" data-score-row data-set-row="<?php echo e($setNumber); ?>">
      <input type="number" min="0" step="1" inputmode="numeric" class="form-control score-input" data-side="home" data-set="<?php echo e($setNumber); ?>" aria-label="Set <?php echo e($setNumber); ?> home score">
      <span>Set <?php echo e($setNumber); ?></span>
      <input type="number" min="0" step="1" inputmode="numeric" class="form-control score-input" data-side="away" data-set="<?php echo e($setNumber); ?>" aria-label="Set <?php echo e($setNumber); ?> away score">
    </div>
  <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<script src="<?php echo e(asset('js/set-score-grid.js')); ?>?v=<?php echo e(filemtime(public_path('js/set-score-grid.js'))); ?>" defer></script>
<?php /**PATH D:\wamp64\www\ct\resources\views\shared\scoring\set-grid.blade.php ENDPATH**/ ?>