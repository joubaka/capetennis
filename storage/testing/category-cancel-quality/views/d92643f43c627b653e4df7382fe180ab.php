<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($application->court_plan): ?>
<div class="alert alert-info mt-3">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($application->court_plan['model'] ?? null) === 'court-ratio-v2'): ?>
<strong>Proposed court allocation</strong>
<p class="mb-1"><?php echo e($application->court_plan['assigned_total']); ?> of <?php echo e($application->court_plan['inputs']['courts']); ?> full-size courts assigned · <?php echo e($application->court_plan['suggested_entries']); ?> suggested places.</p>
<p class="mb-1"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $application->court_plan['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($row['name']); ?>: <?php echo e($row['assigned_courts']); ?> courts → <?php echo e($row['limit']); ?> places. <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
<p class="mb-0">Red uses 16 players per full-size court with four mini courts; Orange, Green and Yellow use four players per court. Requested limits may be lower. Layout and schedule still require review.</p>
<?php else: ?>
<strong>Original time-based estimate</strong>
<p class="small mb-1">Historical time-based estimate, retained as originally submitted.</p>
<p class="mb-1"><?php echo e($application->court_plan['inputs']['courts']); ?> courts × <?php echo e(number_format($application->court_plan['inputs']['hours_hundredths'] / 100, 2)); ?> hours; <?php echo e($application->court_plan['inputs']['slot_minutes']); ?>-minute average slots and <?php echo e($application->court_plan['inputs']['contingency_percent']); ?>% contingency.</p>
<p class="mb-0"><?php echo e($application->court_plan['suggested_entries']); ?> suggested places, <?php echo e($application->court_plan['planned_matches']); ?> planned matches within <?php echo e($application->court_plan['usable_minutes']); ?> usable court-minutes. Four-player groups plus group-winner knockouts; final schedule, rest and layouts still need review. Requested limits may be lower than this estimate.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($application->court_plan['financial_projection'])): ?>
  <?php echo $__env->make('frontend.host-events.financial-projection', ['projection' => $application->court_plan['financial_projection']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
<p class="small text-muted">No court-capacity estimate was recorded for this earlier proposal.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\host-events\court-plan.blade.php ENDPATH**/ ?>