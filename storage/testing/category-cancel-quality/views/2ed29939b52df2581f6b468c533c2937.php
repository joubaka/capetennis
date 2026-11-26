

<?php $__env->startSection('title', 'Create series'); ?>

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
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="card">
    <div class="card-header"><h4 class="mb-1">Create series</h4><p class="small text-muted mb-0">Set up the series first. Add events, configure points and build rankings separately.</p></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
      <div class="alert alert-danger m-3">
        <ul class="mb-0">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e($error); ?></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="card-body">
        <form action="<?php echo e(route('series.store')); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="col-6">
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Series Name</label>
                    <div class="col-md-8">
                        <input class="form-control" name="name" required maxlength="255" type="text" value="<?php echo e(old('name')); ?>" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="ranking-rule-preset" class="col-md-4 col-form-label">Ranking Rules Preset</label>
                    <div class="col-md-8">
                        <select name="ranking_rule_preset_id" id="ranking-rule-preset" class="form-select">
                            <option value="">Custom rules</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingRulePresets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                              <option value="<?php echo e($preset->id); ?>" data-best-count="<?php echo e($preset->rules['best_num_of_scores']); ?>" <?php if((string) old('ranking_rule_preset_id') === (string) $preset->id): echo 'selected'; endif; ?>>
                                <?php echo e($preset->name); ?><?php echo e($preset->is_system ? ' (built-in)' : ''); ?>

                              </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                        <div class="form-text">The preset supplies the best-results count and ranking tie-break rules.</div>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="best-scores-count" class="col-md-4 col-form-label">Best nr of Scores </label>
                    <div class="col-md-8">
                        <select name="numScores" id="best-scores-count" required class="form-select">
                            <option value="">Please select</option>
                            <option value="1" <?php if((string) old('numScores') === '1'): echo 'selected'; endif; ?>>1</option>
                            <option value="2" <?php if((string) old('numScores') === '2'): echo 'selected'; endif; ?>>2</option>
                            <option value="3" <?php if((string) old('numScores') === '3'): echo 'selected'; endif; ?>>3</option>
                            <option value="4" <?php if((string) old('numScores') === '4'): echo 'selected'; endif; ?>>4</option>
                            <option value="5" <?php if((string) old('numScores') === '5'): echo 'selected'; endif; ?>>5</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="defaultSelect" class="col-md-4 col-form-label">Ranking Type </label>
                    <div class="col-md-8">
                        <select name="rankType" id="defaultSelect" required class="form-select">
                            <option value="">Please select</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rankType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($rankType->id); ?>" <?php if((string) old('rankType') === (string) $rankType->id): echo 'selected'; endif; ?>><?php echo e($rankType->type); ?></option>
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
                        <option value="<?php echo e($y); ?>" <?php if((string) old('year') === (string) $y): echo 'selected'; endif; ?>><?php echo e($y); ?></option>
                      <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                  </div>
                </div>

            </div>
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>

    </div>
</div>



</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\series\series-create.blade.php ENDPATH**/ ?>