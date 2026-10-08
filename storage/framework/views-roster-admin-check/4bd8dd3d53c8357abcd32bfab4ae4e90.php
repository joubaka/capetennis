<div class="modal fade" id="scoring-guide" tabindex="-1" aria-labelledby="scoring-guide-title" aria-hidden="true"
     data-automatic="<?php echo e($scoringGuideAutomatic ? '1' : '0'); ?>">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 440px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scoring-guide-title">How to score</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close scoring guide"></button>
      </div>
      <div class="modal-body">
        <p class="small">Your courtside guide. Reopen it anytime from your profile menu.</p>
        <ol class="small ps-3 mb-3">
          <li class="mb-2">Open your event below. Choose your <strong>venue and draw</strong> to find the correct matches.</li>
          <li class="mb-2">Add your name under <strong>Scoring as</strong>, especially when sharing an account.</li>
          <li class="mb-2">Use <strong>Next on court</strong> to find upcoming matches. When the players start, tap <strong>Mark as on court</strong>.</li>
          <li class="mb-2">Pressed it by mistake, or an unfinished match has stopped? Tap <strong>Mark off court</strong>. It returns to <strong>Awaiting court</strong>; this does not record a result.</li>
          <li class="mb-2">When the match finishes, tap <strong>Enter score</strong>, enter the result and <strong>Save score</strong>. The match automatically becomes <strong>Completed</strong>. You do not need to mark it off court separately.</li>
          <li>Use <strong>Completed</strong> to find finished matches and review or correct a result.</li>
        </ol>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scoringGuideAssignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a class="btn btn-outline-primary w-100 mb-2" style="min-height: 44px; white-space: normal"
             href="<?php echo e(route('frontend.scoring.workspace', array_filter(['event' => $assignment->event_id, 'venue' => $assignment->role === 'score-keeper' ? $assignment->venue_id : null]))); ?>">Open scoring: <?php echo e($assignment->event->name); ?></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" style="min-height: 44px" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script src="<?php echo e(asset('js/scoring-guide.js')); ?>?v=<?php echo e(filemtime(public_path('js/scoring-guide.js'))); ?>" defer></script>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\scoring\guide.blade.php ENDPATH**/ ?>