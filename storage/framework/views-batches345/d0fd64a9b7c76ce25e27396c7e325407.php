

<a href="javascript:void(0);"
   class="btn btn-sm btn-outline-primary edit-score-btn"
   data-id="<?php echo e($fixture->id); ?>" data-participant-revision="<?php echo e(app(\App\Services\TeamParticipantHistoryService::class)->revision($fixture)); ?>"
   data-home="<?php echo e(e($homeLabel)); ?>"
   data-away="<?php echo e(e($awayLabel)); ?>"
   data-action="<?php echo e(route('frontend.fixtures.score.store', $fixture->id)); ?>"
   <?php $__currentLoopData = $fixture->fixtureResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
       data-set<?php echo e($r->set_nr); ?>_home="<?php echo e($r->team1_score); ?>"
       data-set<?php echo e($r->set_nr); ?>_away="<?php echo e($r->team2_score); ?>"
   <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
>
    <i class="bi bi-clipboard-data"></i> Insert Score
</a>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixtureResults->count()): ?>
    <a href="javascript:void(0);"
       class="btn btn-sm btn-outline-danger delete-result-btn"
       data-id="<?php echo e($fixture->id); ?>" data-participant-revision="<?php echo e(app(\App\Services\TeamParticipantHistoryService::class)->revision($fixture)); ?>"
       data-action="<?php echo e(route('frontend.fixtures.score.delete', $fixture->id)); ?>">
        <i class="bi bi-trash"></i> Delete Result
    </a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixtures\partials\actions.blade.php ENDPATH**/ ?>