

<?php $__env->startSection('title', 'Series'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Series</h4>
    <a href="<?php echo e(route('series.create')); ?>" class="btn btn-primary">
      Create Series
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Events</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $series; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td>
                <strong><?php echo e($s->name); ?></strong>
              </td>
              <td>
                <?php echo e($s->events_count); ?>

              </td>
              <td>
                <span class="badge bg-<?php echo e($s->active ? 'success' : 'secondary'); ?>">
                  <?php echo e($s->active ? 'Active' : 'Inactive'); ?>

                </span>
                <?php ($rankingStatus = $s->ranking_status); ?>
                <span class="badge bg-label-<?php echo e(match($rankingStatus) {
                  'published' => $s->leaderboard_published ? 'success' : 'secondary',
                  'reviewed' => 'info',
                  'calculated' => 'warning',
                  default => 'secondary'
                }); ?> ms-1">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingStatus === 'published' && !$s->leaderboard_published): ?>
                    Rankings hidden
                  <?php else: ?>
                    Rankings <?php echo e($rankingStatus ? ucfirst($rankingStatus) : 'not built'); ?>

                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
              </td>
              <td class="text-end">
                <a href="<?php echo e(route('series.show', $s)); ?>"
                   class="btn btn-sm btn-outline-primary">
                  View
                </a>
                <a href="<?php echo e(route('series.events', $s)); ?>"
                   class="btn btn-sm btn-outline-secondary">
                  Events
                </a>
                <a href="<?php echo e(route('ranking.series.list', $s)); ?>"
                   class="btn btn-sm btn-outline-primary ms-1">
                  Rankings
                </a>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $s)): ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingStatus === 'calculated'): ?>
                    <button type="button"
                            class="btn btn-sm btn-info ms-1 ranking-action"
                            data-url="<?php echo e(route('ranking.series.ranking.review', $s)); ?>"
                            data-confirm="Mark the calculated rankings for <?php echo e($s->name); ?> as reviewed?">
                      Mark Reviewed
                    </button>
                  <?php elseif($rankingStatus === 'reviewed'): ?>
                    <button type="button"
                            class="btn btn-sm btn-success ms-1 ranking-action"
                            data-url="<?php echo e(route('ranking.series.ranking.publish', $s)); ?>"
                            data-confirm="Publish the reviewed rankings for <?php echo e($s->name); ?>?">
                      Publish Rankings
                    </button>
                  <?php elseif($rankingStatus === 'published'): ?>
                    <button type="button"
                            class="btn btn-sm <?php echo e($s->leaderboard_published ? 'btn-outline-warning' : 'btn-outline-success'); ?> ms-1 ranking-action"
                            data-url="<?php echo e(route('ranking.series.update', $s)); ?>"
                            data-payload='<?php echo json_encode(["best_num_of_scores" => $s->best_num_of_scores, "leaderboard_published" => $s->leaderboard_published ? 0 : 1], 512) ?>'
                            data-confirm="<?php echo e($s->leaderboard_published ? 'Hide' : 'Show'); ?> the published rankings for <?php echo e($s->name); ?> on the public website?">
                      <?php echo e($s->leaderboard_published ? 'Hide Rankings' : 'Show Rankings'); ?>

                    </button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-3">
                No series created yet
              </td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
document.querySelectorAll('.ranking-action').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!window.confirm(btn.dataset.confirm)) return;

    btn.disabled = true;

    try {
      const payload = btn.dataset.payload ? JSON.parse(btn.dataset.payload) : null;
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
        },
        body: payload ? JSON.stringify(payload) : null
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'Ranking action failed');

      if (window.toastr) {
        toastr.success(data.message || 'Ranking status updated');
      }
      window.location.reload();
    } catch (e) {
      console.error('Ranking action failed', e);
      if (window.toastr) {
        toastr.error(e.message || 'Ranking action failed');
      } else {
        window.alert(e.message || 'Ranking action failed');
      }
    } finally {
      btn.disabled = false;
    }
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\index.blade.php ENDPATH**/ ?>