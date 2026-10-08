<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
document.getElementById('ranking-rule-preset')?.addEventListener('change', event => {
    const selected = event.target.selectedOptions[0];
    if (selected?.dataset.bestCount) {
        document.getElementById('best-scores-count').value = selected.dataset.bestCount;
    }
});
</script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header"></div>
    <div class="card-body">
        <form action="<?php echo e(route('series.store')); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="col-6">
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Series Name</label>
                    <div class="col-md-8">
                        <input class="form-control" name="name" type="text" value="" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="ranking-rule-preset" class="col-md-4 col-form-label">Ranking Rules Preset</label>
                    <div class="col-md-8">
                        <select name="ranking_rule_preset_id" id="ranking-rule-preset" class="form-select">
                            <option value="">Custom rules</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingRulePresets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                              <option value="<?php echo e($preset->id); ?>" data-best-count="<?php echo e($preset->rules['best_num_of_scores']); ?>">
                                <?php echo e($preset->name); ?><?php echo e($preset->is_system ? ' (built-in)' : ''); ?>

                              </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                        <div class="form-text">The preset supplies the best-results count and ranking tie-break rules.</div>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Best nr of Scores </label>
                    <div class="col-md-8">
                        <select name="numScores" id="best-scores-count" class="form-select">
                            <option>Please select</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Ranking Type </label>
                    <div class="col-md-8">
                        <select name="rankType" id="defaultSelect" class="form-select">
                            <option>Please select</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rankType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($rankType->id); ?>"><?php echo e($rankType->type); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                  <label for="series-year" class="col-md-4 col-form-label">Year</label>
                  <div class="col-md-8">
                    <select name="year" id="series-year" class="form-select" required>
                      <option value="">Select Year</option>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($y = now()->year; $y <= 2030; $y++): ?>
                        <option value="<?php echo e($y); ?>"><?php echo e($y); ?></option>
                      <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                  </div>
                </div>

            </div>
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>

    </div>
</div>



<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\series-create.blade.php ENDPATH**/ ?>