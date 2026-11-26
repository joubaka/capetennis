<?php
  $move = $pathway['move'] ?? null;
  $moveOptions = $pathway['moveOptions'] ?? [];
  $ballLabels = \App\Services\Performance\BallRatingModel::LABELS;
  $canRecordMove = $moveOptions && auth()->user()?->hasRole('super-user');
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($move || $canRecordMove): ?>
  <section class="pathway-next-step mt-3" id="pathway-ball-move" aria-labelledby="pathway-ball-move-heading">
    <h3 class="h6" id="pathway-ball-move-heading">Ball-level move</h3>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('pathway_move_status')): ?><p class="small" role="status"><?php echo e(session('pathway_move_status')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($move): ?>
      <p class="mb-2"><strong><?php echo e($ballLabels[$move['from']]); ?> → <?php echo e($ballLabels[$move['to']]); ?></strong> · Started <?php echo e($move['started_on']); ?></p>
      <p class="small mb-2">Suggested starting group <strong>10–20</strong> for early matches. This is placement guidance.</p>
      <p class="small mb-2">
        <?php echo e($ballLabels[$move['to']]); ?> CTR
        <strong><?php echo e($move['score'] === null ? 'Pending' : number_format($move['score'], 1)); ?></strong>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($move['local']): ?> · Within-group only <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($move['score'] === null): ?> · Awaiting a saved rating based on eligible matches from the new level. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </p>
      <p class="small mb-2"><strong><?php echo e($move['count'] ?? 'Unavailable'); ?></strong> eligible published singles matches in the saved rating window, from <?php echo e($move['started_on']); ?> inclusive.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($move['matches']->isNotEmpty()): ?>
        <h4 class="small fw-semibold">First recorded matches in this window</h4>
        <ol class="small ps-3 mb-2">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $move['matches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e($match['date']); ?> · <?php echo e($match['event']); ?> · <?php echo e($match['won'] ? 'Win' : 'Loss'); ?></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ol>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecordMove): ?>
      <form class="d-flex flex-wrap align-items-end gap-2" method="post" action="<?php echo e(route('my.tennis.pathway.move', $ratingPlayer)); ?>">
        <?php echo csrf_field(); ?>
        <label class="small">Move to the next ball level
          <select class="form-select form-select-sm" name="move_from_stage" required>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $moveOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $from => $to): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($from); ?>" <?php if(old('move_from_stage') === $from): echo 'selected'; endif; ?>><?php echo e($ballLabels[$from]); ?> → <?php echo e($ballLabels[$to]); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </label>
        <label class="small">Start date
          <input class="form-control form-control-sm" type="date" name="move_started_on" value="<?php echo e(old('move_started_on', now('Africa/Johannesburg')->toDateString())); ?>" max="<?php echo e(now('Africa/Johannesburg')->toDateString()); ?>" required>
        </label>
        <button class="btn btn-sm btn-primary" type="submit">Record move</button>
      </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['move_from_stage', 'move_started_on']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="small text-danger mt-2 mb-0" role="alert"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-move.blade.php ENDPATH**/ ?>