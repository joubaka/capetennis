<!doctype html><html lang="en"><body>
<h1><?php echo e($notification->revision > 1 ? 'Corrected match result' : 'A new match result has been entered'); ?></h1>
<p>Event: <?php echo e($notification->snapshot['event']); ?><br>Draw: <?php echo e($notification->snapshot['draw']); ?></p>
<p><?php echo e(implode(' / ', $notification->snapshot['home_names'])); ?> versus <?php echo e(implode(' / ', $notification->snapshot['away_names'])); ?></p>
<p>Result: <?php echo e(collect($notification->snapshot['sets'])->map(fn ($set) => implode('–', $set))->implode(', ')); ?></p>
<p><a href="<?php echo e(route('frontend.fixtures.index', $notification->snapshot['draw_id'])); ?>">View the match result</a></p>
<p>Please check that this result is correct. If anything is wrong, contact the convener at the court.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replyTo): ?>
<p>You can also reply to this email to contact the event administrators. The reply addresses are <?php echo e(implode(', ', $replyTo)); ?>; please check that your email app includes all of them before sending.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body></html>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\match-result.blade.php ENDPATH**/ ?>