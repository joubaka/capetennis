


<?php $__env->startSection('title', 'Rankings — ' . $series->name); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .rank-card .card-header {
    background:#e9ecef;
    border-bottom:1px solid #dee2e6
  }
  .rank-card .card {
    border-radius:.75rem;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.05)
  }
  .rank-card table th {
    text-transform:uppercase;
    letter-spacing:.04em;
    font-size:.75rem;
    color:#6c757d
  }
  .rank-card .total {
    font-weight:700
  }
  .rank-card small.event-points {
    color:#6c757d;
    font-size:.75rem;
    display:block
  }

  /* ✅ limit width of Events & Points column (th + td) */

</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="container-xxl py-3">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $series->ranking_lists->chunk(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chunk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="row g-4">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $chunk; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $list): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
     <div class="<?php echo e($series->id == 16 ? 'col-md-9' : 'col-md-6'); ?>">

          <div class="card rank-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4 class="mb-0">
                <span class="badge rounded-pill bg-primary">
                  <?php echo e($list->name ?? ($list->category->name ?? 'Ranking List')); ?>

                </span>
              </h4>
              <small class="text-muted"><?php echo e($list->category->name ?? 'No category'); ?></small>
            </div>

            <div class="card-body p-0">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($list->ranking_scores->count()): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px">#</th>
                        <th>Player</th>
                        <th class="legs-col">Events & Points</th>
                        <th style="width:100px" class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $rank = 1; ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($list->ranking_scores ?? collect())->sortByDesc('total_points'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $score): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                          <td><?php echo e($rank++); ?></td>
                          <td>
                            <?php echo e($score->player?->fullName ?? 'Unknown'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($score->primarySchool): ?>
                              <span class="badge bg-info ms-1">U/13</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          </td>

                          <td class="legs-col">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $score->legs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                              <span class="badge bg-label-primary me-1 mb-1">
                                <?php echo e($leg->event_name); ?>: <?php echo e($leg->points); ?>

                                <small class="text-muted">(<?php echo e($leg->position); ?>)</small>
                              </span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          </td>

                          <td class="text-end total"><?php echo e($score->total_points); ?></td>
                        </tr>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="p-3 text-muted">No scores yet.</div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="alert alert-info">No ranking lists yet.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\ranking\results.blade.php ENDPATH**/ ?>