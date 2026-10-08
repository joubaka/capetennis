

<?php $__env->startSection('title', 'Fixtures at ' . $venue->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl py-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Fixtures at <?php echo e($venue->name); ?></h2>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            
                            <th class="d-none d-sm-table-cell">#</th>
                            <th>Home</th>
                            <th>Away</th>
                            <th>Result</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            
                            <td class="text-muted d-none d-sm-table-cell"><?php echo e($fx->id); ?></td>
                            
                            <td class="fw-semibold"><?php echo e($fx->home_side_name ?? $fx->home_team_name); ?></td>
                            <td class="fw-semibold"><?php echo e($fx->away_side_name ?? $fx->away_team_name); ?></td>
                            
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->fixtureResults->count()): ?>
                                    <div class="mb-2"><?php echo e($fx->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->team1_score.'-'.$set->team2_score)->implode(', ')); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.saveScore', $fx)): ?>
                                    <form method="POST" action="<?php echo e(route('frontend.fixtures.score.store', $fx->id)); ?>">
                                        <input type="hidden" name="participant_revision" value="<?php echo e(app(\App\Services\TeamParticipantHistoryService::class)->revision($fx)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($set = 1; $set <= ($fx->draw->team_scoring_rules ? app(\App\Services\TeamRubberResultService::class)->rules($fx)['sets_to_win'] * 2 - 1 : 3); $set++): ?>
                                            <?php $existing = $fx->fixtureResults->firstWhere('set_nr', $set); ?>
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <label class="small mb-0">Set <?php echo e($set); ?></label>
                                                <input type="number" min="0" max="99" name="set<?php echo e($set); ?>_home" aria-label="Home set <?php echo e($set); ?>" value="<?php echo e(old('set'.$set.'_home', $existing?->team1_score)); ?>" class="form-control form-control-sm" style="width:70px" <?php if($set === 1): echo 'required'; endif; ?>>
                                                <span>-</span>
                                                <input type="number" min="0" max="99" name="set<?php echo e($set); ?>_away" aria-label="Away set <?php echo e($set); ?>" value="<?php echo e(old('set'.$set.'_away', $existing?->team2_score)); ?>" class="form-control form-control-sm" style="width:70px" <?php if($set === 1): echo 'required'; endif; ?>>
                                            </div>
                                        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-primary">Save scores</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-end">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fx->fixtureResults->count() && auth()->user()?->can('team-fixture.saveScore', $fx)): ?>
                                    <form method="POST" action="<?php echo e(route('frontend.fixtures.score.delete', $fx->id)); ?>" onsubmit="return confirm('Delete result?');">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                            <span class="d-none d-md-inline">Delete</span>
                                        </button>
                                    </form>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixtures\venue-fixtures.blade.php ENDPATH**/ ?>