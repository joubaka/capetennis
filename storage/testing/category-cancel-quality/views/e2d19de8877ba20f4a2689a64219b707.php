
<?php $__env->startSection('title', 'Public schedule preview – '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl py-3" data-schedule-preview-page>
    <h3>Public schedule preview</h3>
    <p class="text-muted">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hideTimes): ?>
            Preview with match times and venues hidden. The published draw remains available. Nothing has been changed.
        <?php elseif($afterPublish): ?>
            Preview after publishing the saved schedule in this selection. Hidden events and draws remain hidden. Nothing has been published.
        <?php else: ?>
            What people currently see: published match times and currently public draws. Private saved changes are excluded.
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>
    <p class="small text-muted">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hideTimes): ?>
            Showing the full public draw with times and venues hidden.
        <?php elseif($afterPublish): ?>
            Showing the full public draw. The date and venue selection determines which saved times are published in this preview.
        <?php else: ?>
            Showing the full public draw with currently published times.
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-venue-schedule.calendar',['event'=>$event->id]+$scope)); ?>">Back to saved schedule</a>
        <button type="button" class="btn <?php echo e(!$afterPublish && !$hideTimes ? 'btn-primary' : 'btn-outline-primary'); ?>" aria-pressed="<?php echo e(!$afterPublish && !$hideTimes ? 'true' : 'false'); ?>" aria-controls="schedule-preview-content"
           data-preview-url="<?php echo e(route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['after_publish' => 0])); ?>">Currently public</button>
        <button type="button" class="btn <?php echo e($afterPublish && !$hideTimes ? 'btn-primary' : 'btn-outline-primary'); ?>" aria-pressed="<?php echo e($afterPublish && !$hideTimes ? 'true' : 'false'); ?>" aria-controls="schedule-preview-content"
           data-preview-url="<?php echo e(route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['after_publish' => 1])); ?>">With times published</button>
        <button type="button" class="btn <?php echo e($hideTimes ? 'btn-primary' : 'btn-outline-primary'); ?>" aria-pressed="<?php echo e($hideTimes ? 'true' : 'false'); ?>" aria-controls="schedule-preview-content"
           data-preview-url="<?php echo e(route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['hide_times' => 1])); ?>">With times hidden</button>
        <span class="badge bg-label-secondary"><?php echo e($hideTimes ? 'Times hidden' : ($afterPublish ? 'After publishing' : 'Currently public')); ?></span>
    </div>
    <p class="small text-muted mb-2" data-preview-status role="status" aria-live="polite"></p>
    <div data-public-schedule-preview id="schedule-preview-content" aria-busy="false">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $previewDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preview): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <section class="mb-4" aria-label="<?php echo e($preview['draw']->drawName); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['fixtures']->first() instanceof \App\Models\TeamFixture): ?>
                    <?php echo $__env->make('frontend.fixture.partials.team-schedule', ['draw' => $preview['draw'], 'fixtures' => $preview['fixtures'], 'enableLiveResults' => false, 'fixtureScheduleSummary' => $preview['scheduleSummary']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php else: ?>
                    <?php echo $__env->make('frontend.fixture.fixture-table', ['draw' => $preview['draw'], 'fixtures' => $preview['fixtures'], 'hideFixtureHeader' => false, 'fixtureTableId' => 'preview-fixtures-'.$preview['draw']->id, 'fixtureScheduleSummary' => $preview['scheduleSummary']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="card"><div class="card-body text-muted"><?php echo e($hideTimes ? 'No public fixtures in this selection.' : ($afterPublish ? 'No public match times after publishing this selection.' : 'No public match times in this view.')); ?></div></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('js/schedule-public-preview.js')); ?>?v=<?php echo e(filemtime(public_path('js/schedule-public-preview.js'))); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\schedule\public-schedule-preview.blade.php ENDPATH**/ ?>